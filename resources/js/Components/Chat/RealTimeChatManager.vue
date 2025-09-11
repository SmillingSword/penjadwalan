<template>
  <div class="chat-manager">
    <!-- Chat Sidebar -->
    <ChatSidebar
      :conversations="conversations"
      :online-users="onlineUsers"
      @open-chat="openChat"
      @start-chat="startChat"
      @status-updated="handleStatusUpdate"
    />

    <!-- Floating Chat Boxes -->
    <div class="floating-chats">
      <FloatingChatBox
        v-for="(chat, index) in openChats"
        :key="chat.id"
        ref="chatBoxes"
        :conversation="chat"
        :position="index"
        :messages="getMessagesForChat(chat.id)"
        @close="closeChat"
        @minimize="handleMinimize"
        @send-message="sendMessage"
        @load-more-messages="loadMoreMessages"
        @start-typing="handleStartTyping"
        @stop-typing="handleStopTyping"
        @video-call="handleVideoCall"
        @phone-call="handlePhoneCall"
        @upload-files="handleFileUpload"
      />
    </div>

    <!-- New Message Sound -->
    <audio ref="messageSound" preload="auto">
      <source src="/sounds/message.mp3" type="audio/mpeg">
      <source src="/sounds/message.ogg" type="audio/ogg">
    </audio>

    <!-- Typing Sound -->
    <audio ref="typingSound" preload="auto">
      <source src="/sounds/typing.mp3" type="audio/mpeg">
      <source src="/sounds/typing.ogg" type="audio/ogg">
    </audio>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Echo from '@/echo'
import ChatSidebar from './ChatSidebar.vue'
import FloatingChatBox from './FloatingChatBox.vue'

// Props
const props = defineProps({
  initialConversations: {
    type: Array,
    default: () => []
  },
  initialOnlineUsers: {
    type: Array,
    default: () => []
  }
})

// Reactive data
const conversations = ref([...props.initialConversations])
const allUsers = ref([...props.initialOnlineUsers]) // renamed from onlineUsers to allUsers to hold all users
const presenceUsers = ref([]) // users currently online from presence channel
const openChats = ref([])
const messages = reactive({}) // Store messages by conversation ID
const typingUsers = reactive({}) // Store typing users by conversation ID
const currentUser = computed(() => usePage().props.auth?.user || {})
const pollingIntervals = reactive({}) // Store polling intervals by conversation ID
const lastMessageIds = reactive({}) // Store last message IDs for polling

// Refs
const messageSound = ref(null)
const typingSound = ref(null)
const chatBoxes = ref([])

// Maximum number of open chats and polling interval
const MAX_OPEN_CHATS = 3
const POLLING_INTERVAL = 2000 // Poll every 2 seconds

// Methods
const openChat = (conversation) => {
  // Check if chat is already open
  const existingIndex = openChats.value.findIndex(chat => chat.id === conversation.id)
  
  if (existingIndex !== -1) {
    // Chat is already open, just focus it (bring to front)
    const existingChat = openChats.value[existingIndex]
    openChats.value.splice(existingIndex, 1)
    openChats.value.unshift(existingChat)
    return
  }

  // If we have max chats open, close the oldest one
  if (openChats.value.length >= MAX_OPEN_CHATS) {
    const closedChat = openChats.value.pop()
    stopPolling(closedChat.id)
  }

  // Add new chat to the beginning
  openChats.value.unshift(conversation)

  // Load messages for this conversation
  loadMessages(conversation.id)

  // Subscribe to real-time updates for this conversation
  subscribeToConversationChannel(conversation.id)

  // Mark conversation as read
  markConversationAsRead(conversation.id)
}

const startChat = async (user) => {
  try {
    // Create or get existing private conversation
    const response = await fetch('/api/realtime-chat/conversations/private', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        user_id: user.id
      })
    })

    if (response.ok) {
      const data = await response.json()
      openChat(data.conversation)
    }
  } catch (error) {
    console.error('Error starting chat:', error)
  }
}

const closeChat = (conversation) => {
  const index = openChats.value.findIndex(chat => chat.id === conversation.id)
  if (index !== -1) {
    openChats.value.splice(index, 1)
  }

  // Unsubscribe from real-time updates for this conversation
  unsubscribeFromConversationChannel(conversation.id)
  
  // Stop polling for this conversation
  stopPollingForConversation(conversation.id)

  // Clean up messages and typing indicators
  delete messages[conversation.id]
  delete typingUsers[conversation.id]
  delete lastMessageIds[conversation.id]
}

const handleMinimize = ({ conversation, minimized }) => {
  const chat = openChats.value.find(c => c.id === conversation.id)
  if (chat) {
    chat.minimized = minimized
  }
}

const sendMessage = async (messageData) => {
  try {
    console.log('📤 Sending message:', messageData)
    
    const response = await fetch(`/api/realtime-chat/conversations/${messageData.conversation_id}/messages`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        content: messageData.content,
        reply_to_id: messageData.reply_to_id,
        attachments: messageData.attachments
      })
    })

    if (response.ok) {
      const data = await response.json()
      console.log('✅ Message sent successfully:', data.message)
      
      // Add message to local state immediately for instant feedback
      if (!messages[messageData.conversation_id]) {
        messages[messageData.conversation_id] = []
      }
      
      // Check if message already exists to avoid duplicates
      const existingMessage = messages[messageData.conversation_id].find(m => m.id === data.message.id)
      if (!existingMessage) {
        messages[messageData.conversation_id].push(data.message)
        console.log('📝 Added message to local state')
      }

      // Update last message ID for polling
      lastMessageIds[messageData.conversation_id] = data.message.id

      // Update conversation's last message
      updateConversationLastMessage(messageData.conversation_id, data.message)
      
      // Start polling as fallback if real-time fails
      startPollingForConversation(messageData.conversation_id)
    } else {
      console.error('❌ Failed to send message:', response.status, response.statusText)
    }
  } catch (error) {
    console.error('❌ Error sending message:', error)
  }
}

const loadMessages = async (conversationId, page = 1) => {
  try {
    const response = await fetch(`/api/realtime-chat/conversations/${conversationId}/messages?page=${page}`, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      credentials: 'same-origin'
    })
    
    if (response.ok) {
      const data = await response.json()
      
      if (page === 1) {
        messages[conversationId] = data.messages
        // Set last message ID for polling
        if (data.messages.length > 0) {
          lastMessageIds[conversationId] = data.messages[data.messages.length - 1].id
        }
      } else {
        // Prepend older messages
        messages[conversationId] = [...data.messages, ...(messages[conversationId] || [])]
      }
    }
  } catch (error) {
    console.error('Error loading messages:', error)
  }
}

const loadMoreMessages = (data) => {
  // Handle both old format (just conversationId) and new format (object with conversationId and beforeMessageId)
  const conversationId = typeof data === 'object' ? data.conversationId : data
  const beforeMessageId = typeof data === 'object' ? data.beforeMessageId : null
  
  console.log('📥 Loading more messages for conversation:', conversationId, 'before message:', beforeMessageId)
  
  // Prevent multiple simultaneous requests
  if (loadingMoreMessages[conversationId]) {
    console.log('⏳ Already loading messages for conversation:', conversationId)
    return
  }
  
  loadingMoreMessages[conversationId] = true
  
  // Load older messages
  loadOlderMessages(conversationId, beforeMessageId)
}

// Add tracking for loading states
const loadingMoreMessages = reactive({})

const loadOlderMessages = async (conversationId, beforeMessageId = null) => {
  try {
    let url = `/api/realtime-chat/conversations/${conversationId}/messages`
    
    if (beforeMessageId) {
      // Use the new endpoint for loading messages before a specific message ID
      url += `?before=${beforeMessageId}&limit=20`
    } else {
      // Fallback to pagination
      const currentMessages = messages[conversationId] || []
      const page = Math.ceil(currentMessages.length / 20) + 1
      url += `?page=${page}`
    }
    
    console.log('📡 Fetching older messages from:', url)
    
    const response = await fetch(url, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      credentials: 'same-origin'
    })
    
    if (response.ok) {
      const data = await response.json()
      console.log('📬 Received older messages:', data.messages?.length || 0)
      
      if (data.messages && data.messages.length > 0) {
        if (!messages[conversationId]) {
          messages[conversationId] = []
        }
        
        // Filter out duplicates and prepend older messages
        const existingIds = new Set(messages[conversationId].map(m => m.id))
        const newMessages = data.messages.filter(m => !existingIds.has(m.id))
        
        if (newMessages.length > 0) {
          messages[conversationId] = [...newMessages, ...messages[conversationId]]
          console.log('✅ Added', newMessages.length, 'new older messages')
        } else {
          console.log('🔄 No new messages (all were duplicates)')
        }
      } else {
        console.log('📭 No more older messages available')
      }
    } else {
      console.error('❌ Failed to load older messages:', response.status, response.statusText)
    }
  } catch (error) {
    console.error('❌ Error loading older messages:', error)
  } finally {
    // Reset loading state
    loadingMoreMessages[conversationId] = false
  }
}

const getMessagesForChat = (conversationId) => {
  return messages[conversationId] || []
}

// Enhanced real-time event handling with fallback polling
const subscribeToConversationChannel = (conversationId) => {
  console.log('🔔 Subscribing to conversation channel:', conversationId)
  
  // Always start polling as fallback
  startPollingForConversation(conversationId)
  
  // Check if Echo is properly initialized (not mock)
  if (!window.Echo || !window.Echo.connector || !window.Echo.connector.pusher) {
    console.log('📴 Real-time disabled, using polling only')
    return
  }

  try {
    const channelName = `private-chat.conversation.${conversationId}`
    console.log('📡 Subscribing to channel:', channelName)

    // Unsubscribe if already subscribed
    if (window.Echo.private(channelName)) {
      window.Echo.leave(channelName)
    }

    // Subscribe to optimized message channel
    window.Echo.private(channelName)
      .listen('.message.sent', (event) => {
        console.log('📨 Real-time message received:', event)
        const startTime = performance.now()
        const message = event.message
        
        if (!messages[conversationId]) {
          messages[conversationId] = []
        }
        
        // Avoid duplicate messages using delivery_id
        const exists = messages[conversationId].find(m => 
          m.id === message.id || 
          (message.delivery_id && m.delivery_id === message.delivery_id)
        )
        
        if (!exists) {
          messages[conversationId].push(message)
          lastMessageIds[conversationId] = message.id
          updateConversationLastMessage(conversationId, message)
          
          // Calculate delivery time
          const deliveryTime = performance.now() - startTime
          console.log(`✅ Real-time message delivered in ${deliveryTime.toFixed(2)}ms`)
          
          // Trigger scroll to bottom for new real-time messages
          console.log('📜 New real-time message received, will auto-scroll to bottom')
          
          if (message.sender.id !== currentUser.value.id) {
            playMessageSound()
            
            // Show notification if window is not focused
            if (document.hidden) {
              showMessageNotification(message, conversationId)
            }
          }
        } else {
          console.log('🔄 Duplicate message ignored')
        }
      })
      .error((error) => {
        console.error('❌ Real-time subscription error:', error)
        console.log('🔄 Falling back to polling only')
      })

    console.log('✅ Real-time subscription successful')
  } catch (error) {
    console.warn('⚠️ Failed to subscribe to conversation channel:', error.message)
    console.log('🔄 Using polling as fallback')
  }
}

const unsubscribeFromConversationChannel = (conversationId) => {
  if (!window.Echo) return
  const channelName = `private-chat.conversation.${conversationId}`
  window.Echo.leave(channelName)
}

// Polling fallback for when real-time fails
const startPollingForConversation = (conversationId) => {
  // Don't start multiple polling intervals for the same conversation
  if (pollingIntervals[conversationId]) {
    return
  }
  
  console.log('🔄 Starting polling for conversation:', conversationId)
  
  pollingIntervals[conversationId] = setInterval(async () => {
    try {
      const lastMessageId = lastMessageIds[conversationId] || 0
      const response = await fetch(`/api/realtime-chat/conversations/${conversationId}/messages/since/${lastMessageId}`, {
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        },
        credentials: 'same-origin'
      })
      
      if (response.ok) {
        const data = await response.json()
        
        if (data.messages && data.messages.length > 0) {
          console.log(`📬 Polling found ${data.messages.length} new messages`)
          
          if (!messages[conversationId]) {
            messages[conversationId] = []
          }
          
          // Add new messages
          data.messages.forEach(message => {
            const exists = messages[conversationId].find(m => m.id === message.id)
            if (!exists) {
              messages[conversationId].push(message)
              lastMessageIds[conversationId] = message.id
              updateConversationLastMessage(conversationId, message)
              
              // Trigger scroll to bottom for new polling messages
              console.log('📜 New polling message received, will auto-scroll to bottom')
              
              // Play sound for messages from others
              if (message.sender.id !== currentUser.value.id) {
                playMessageSound()
              }
            }
          })
        }
      }
    } catch (error) {
      console.error('❌ Polling error:', error)
    }
  }, 2000) // Poll every 2 seconds
}

const stopPollingForConversation = (conversationId) => {
  if (pollingIntervals[conversationId]) {
    console.log('⏹️ Stopping polling for conversation:', conversationId)
    clearInterval(pollingIntervals[conversationId])
    delete pollingIntervals[conversationId]
  }
}

// Enhanced typing indicators with API endpoints and auto-cleanup
const handleStartTyping = async (conversationId) => {
  try {
    // Use optimized API endpoint
    await fetch(`/api/realtime-chat/conversations/${conversationId}/typing/start`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      credentials: 'same-origin'
    })
  } catch (error) {
    console.error('Error starting typing indicator:', error)
  }
}

const handleStopTyping = async (conversationId) => {
  try {
    // Use optimized API endpoint
    await fetch(`/api/realtime-chat/conversations/${conversationId}/typing/stop`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      credentials: 'same-origin'
    })
  } catch (error) {
    console.error('Error stopping typing indicator:', error)
  }
}

const handleVideoCall = (conversation) => {
  console.log('Starting video call with:', conversation)
}

const handlePhoneCall = (conversation) => {
  console.log('Starting phone call with:', conversation)
}

const handleFileUpload = async ({ files, conversation_id }) => {
  try {
    const formData = new FormData()
    formData.append('conversation_id', conversation_id)
    
    files.forEach((file, index) => {
      formData.append(`files[${index}]`, file)
    })

    const response = await fetch('/api/chat/messages/upload', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      credentials: 'same-origin',
      body: formData
    })

    if (response.ok) {
      const data = await response.json()
      
      // Add message to local state
      if (!messages[conversation_id]) {
        messages[conversation_id] = []
      }
      messages[conversation_id].push(data.message)

      // Update conversation's last message
      updateConversationLastMessage(conversation_id, data.message)
    }
  } catch (error) {
    console.error('Error uploading files:', error)
  }
}

const handleStatusUpdate = (status) => {
  currentUser.value.status = status
}

const markConversationAsRead = async (conversationId) => {
  try {
    await fetch(`/api/realtime-chat/conversations/${conversationId}/read`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      credentials: 'same-origin'
    })

    // Update local conversation state
    const conversation = conversations.value.find(c => c.id === conversationId)
    if (conversation) {
      conversation.unread_count = 0
    }
  } catch (error) {
    console.error('Error marking conversation as read:', error)
  }
}

const updateConversationLastMessage = (conversationId, message) => {
  const conversation = conversations.value.find(c => c.id === conversationId)
  if (conversation) {
    conversation.last_message = message
    conversation.last_message_at = message.created_at
    
    // Move conversation to top of list
    const index = conversations.value.indexOf(conversation)
    conversations.value.splice(index, 1)
    conversations.value.unshift(conversation)
  }
}

const playMessageSound = () => {
  if (messageSound.value) {
    messageSound.value.currentTime = 0
    messageSound.value.play().catch(() => {
      // Ignore autoplay restrictions
    })
  }
}

const playTypingSound = () => {
  if (typingSound.value) {
    typingSound.value.currentTime = 0
    typingSound.value.volume = 0.3
    typingSound.value.play().catch(() => {
      // Ignore autoplay restrictions
    })
  }
}

const fetchConversations = async () => {
  try {
    const response = await fetch('/api/realtime-chat/conversations', {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      credentials: 'same-origin'
    })
    if (response.ok) {
      const data = await response.json()
      // Ensure each conversation has proper other_user structure
      conversations.value = data.conversations.map(conv => {
        if (!conv.other_user && conv.participants) {
          const otherParticipant = conv.participants.find(p => p.id !== currentUser.value.id)
          if (otherParticipant) {
            conv.other_user = {
              id: otherParticipant.id,
              name: otherParticipant.name,
              avatar: otherParticipant.avatar,
              online_status: otherParticipant.online_status || 'offline',
              is_online: otherParticipant.is_online || false,
              last_seen_at: otherParticipant.last_seen_at
            }
          }
        }
        return conv
      })
    }
  } catch (error) {
    console.error('Error fetching conversations:', error)
  }
}


const fetchAllUsers = async () => {
  try {
    const response = await fetch('/api/realtime-chat/users', {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      credentials: 'same-origin'
    })
    
    if (response.ok) {
      const contentType = response.headers.get('content-type')
      if (contentType && contentType.includes('application/json')) {
        const data = await response.json()
        console.log('✅ Fetched users successfully:', data.users?.length || 0)
        allUsers.value = data.users || []
        mergePresenceIntoAllUsers()
      } else {
        console.error('❌ API returned non-JSON response:', contentType)
        const text = await response.text()
        console.error('Response body:', text.substring(0, 200) + '...')
      }
    } else {
      console.error('❌ Failed to fetch users:', response.status, response.statusText)
      const text = await response.text()
      console.error('Error response:', text.substring(0, 200) + '...')
    }
  } catch (error) {
    console.error('❌ Error fetching users:', error)
    console.log('📋 Using initial users from props as fallback')
    // Fallback: keep existing users if fetch fails
    if (props.initialOnlineUsers && props.initialOnlineUsers.length > 0) {
      console.log('📋 Fallback: Using initial users:', props.initialOnlineUsers.length)
      allUsers.value = [...props.initialOnlineUsers]
      mergePresenceIntoAllUsers()
    }
  }
}

// Enhanced presence system with heartbeat
const updateUserOnlineStatus = async (isOnline = true, status = 'available') => {
  try {
    await fetch('/api/realtime-chat/online-status', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        status: isOnline ? status : 'offline',
        is_online: isOnline,
        status_message: isOnline ? 'Online' : null
      })
    })
  } catch (error) {
    console.error('Error updating online status:', error)
  }
}

// Heartbeat system for accurate presence
const sendHeartbeat = async () => {
  try {
    await fetch('/api/realtime-chat/heartbeat', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      credentials: 'same-origin'
    })
  } catch (error) {
    console.error('Error sending heartbeat:', error)
  }
}

// Show message notification for background messages
const showMessageNotification = (message, conversationId) => {
  const conversation = conversations.value.find(c => c.id === conversationId)
  const senderName = message.sender?.name || 'Someone'
  const conversationTitle = conversation?.title || 'Chat'
  
  // Use browser notification if available
  if ('Notification' in window && Notification.permission === 'granted') {
    const notification = new Notification(`${senderName} in ${conversationTitle}`, {
      body: message.content,
      icon: message.sender?.avatar || '/default-avatar.png',
      tag: `chat-${conversationId}`,
      requireInteraction: false
    })
    
    notification.onclick = () => {
      window.focus()
      // Open the chat if not already open
      if (!openChats.value.find(chat => chat.id === conversationId)) {
        openChat(conversation)
      }
      notification.close()
    }
    
    // Auto-close after 5 seconds
    setTimeout(() => notification.close(), 5000)
  }
}

// Lifecycle
onMounted(() => {
  console.log('🚀 RealTimeChatManager mounted')
  console.log('📊 Initial users from props:', props.initialOnlineUsers?.length || 0)

  // Ensure we have initial users FIRST
  if (props.initialOnlineUsers && props.initialOnlineUsers.length > 0) {
    allUsers.value = [...props.initialOnlineUsers]
    console.log('✅ Using initial users:', allUsers.value.length)
    console.log('👥 Initial users:', allUsers.value.map(u => `${u.name} (${u.is_online ? 'online' : 'offline'})`))
  } else {
    console.log('⚠️ No initial users provided from props')
  }

  // Set user as online
  updateUserOnlineStatus(true)

  // Force refresh conversations and online users on mount to ensure up-to-date data
  console.log('📞 Fetching conversations...')
  fetchConversations()

  console.log('👥 Fetching all users from API...')
  fetchAllUsers()

  // Handle page visibility changes
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      // User switched away from tab
    } else {
      // User returned to tab - mark open chats as read
      openChats.value.forEach(chat => {
        markConversationAsRead(chat.id)
      })
    }
  })

  // Handle beforeunload to set user offline
  window.addEventListener('beforeunload', () => {
    updateUserOnlineStatus(false)
  })

  // Check if Echo is properly initialized (not mock)
  if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
    try {
      // Listen for user status updates on presence channel
      window.Echo.join('presence-chat.online-users')
        .here((users) => {
          console.log('Currently online users:', users)
          presenceUsers.value = users.filter(u => u.id !== currentUser.value.id)
          mergePresenceIntoAllUsers()
        })
        .joining((user) => {
          console.log('User joined:', user)
          if (user.id !== currentUser.value.id) {
            const existingIndex = presenceUsers.value.findIndex(u => u.id === user.id)
            if (existingIndex === -1) {
              presenceUsers.value.push(user)
            }
          }
          mergePresenceIntoAllUsers()
          updateUserInConversations(user)
        })
        .leaving((user) => {
          console.log('User left:', user)
          presenceUsers.value = presenceUsers.value.filter(u => u.id !== user.id)
          mergePresenceIntoAllUsers()
          updateUserInConversations({ ...user, is_online: false, online_status: 'offline' })
        })
        .listen('.user.status.updated', (event) => {
          console.log('User status updated:', event)
          updateUserInConversations(event.user)
        })

      // Subscribe to personal user channel
      window.Echo.private(`user.${currentUser.value.id}`)
        .listen('.user.status.updated', (event) => {
          console.log('Personal status updated:', event)
          if (event.user.id === currentUser.value.id) {
            Object.assign(currentUser.value, event.user)
          }
        })

      // Debug presence channel subscription
      console.log('✅ Subscribed to real-time channels')
    } catch (error) {
      console.warn('⚠️ Real-time subscription failed:', error.message)
    }
  } else {
    console.log('📴 Real-time features disabled (Pusher not configured)')
  }

// Enhanced heartbeat system (every 30 seconds)
setInterval(() => {
  sendHeartbeat()
  updateUserOnlineStatus(true)
}, 30000)

// More frequent heartbeat for active chat users (every 10 seconds)
setInterval(() => {
  if (openChats.value.length > 0) {
    sendHeartbeat()
  }
}, 10000)

// Periodic user list refresh (fallback for presence channel)
setInterval(() => {
  fetchAllUsers()
}, 30000) // Every 30 seconds
})

onUnmounted(() => {
  // Set user as offline
  updateUserOnlineStatus(false)

  // Clean up all Pusher subscriptions
  openChats.value.forEach(chat => {
    unsubscribeFromConversationChannel(chat.id)
  })
})

// Helper function to update user status in conversations

const updateUserInConversations = (user) => {
  console.log('🔄 Updating user in conversations:', user.name, user.is_online ? 'online' : 'offline')
  
  // Update user status in the allUsers list
  const userIndex = allUsers.value.findIndex(u => u.id === user.id)
  if (userIndex !== -1) {
    allUsers.value[userIndex] = { 
      ...allUsers.value[userIndex], 
      ...user,
      // Ensure consistent status fields
      status: user.status || (user.is_online ? 'available' : 'offline')
    }
  } else if (user.is_online) {
    allUsers.value.push({
      ...user,
      status: user.status || 'available'
    })
  }
  
  // Update conversation user status if it's the other user in any open chat
  openChats.value.forEach(chat => {
    if (chat.other_user && chat.other_user.id === user.id) {
      chat.other_user = { 
        ...chat.other_user, 
        ...user,
        status: user.status || (user.is_online ? 'available' : 'offline')
      }
      console.log('✅ Updated chat other_user:', chat.other_user.name, chat.other_user.is_online ? 'online' : 'offline')
    }
  })
  
  // Update conversations list
  conversations.value.forEach(conversation => {
    if (conversation.other_user && conversation.other_user.id === user.id) {
      conversation.other_user = { 
        ...conversation.other_user, 
        ...user,
        status: user.status || (user.is_online ? 'available' : 'offline')
      }
      console.log('✅ Updated conversation other_user:', conversation.other_user.name, conversation.other_user.is_online ? 'online' : 'offline')
    }
  })
}

// Computed property for online users to pass to ChatSidebar
const onlineUsers = computed(() => {
  console.log('🔄 Computing onlineUsers, allUsers count:', allUsers.value.length)
  return allUsers.value
})

// Merge presenceUsers into allUsers to update online status
const mergePresenceIntoAllUsers = () => {
  const presenceMap = new Map(presenceUsers.value.map(user => [user.id, user]))
  allUsers.value = allUsers.value.map(user => {
    if (presenceMap.has(user.id)) {
      const presenceUser = presenceMap.get(user.id)
      return { 
        ...user, 
        is_online: true, 
        status: presenceUser.status || 'available',
        last_seen_at: new Date().toISOString()
      }
    } else {
      // DON'T override is_online from API data if presence is not available
      // Keep the original is_online value from the API
      console.log('🔍 User not in presence channel, keeping API data:', user.name, 'is_online:', user.is_online)
      return user
    }
  })
  console.log('🔄 Merged presence data, total users:', allUsers.value.length)
  console.log('👥 Online users after merge:', allUsers.value.filter(u => u.is_online).map(u => `${u.name} (${u.status})`))
}

// Expose methods for external use
defineExpose({
  openChat,
  startChat,
  closeChat,
  sendMessage,
  updateUserOnlineStatus,
  sendHeartbeat
})
</script>

<style scoped>
.floating-chats {
  position: fixed;
  bottom: 0;
  right: 0;
  z-index: 30;
  pointer-events: none;
}

.floating-chats > * {
  pointer-events: auto;
}
</style>

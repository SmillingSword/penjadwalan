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
const onlineUsers = ref([...props.initialOnlineUsers])
const openChats = ref([])
const messages = reactive({}) // Store messages by conversation ID
const typingUsers = reactive({}) // Store typing users by conversation ID
const currentUser = computed(() => usePage().props.auth?.user || {})
const pollingIntervals = reactive({}) // Store polling intervals by conversation ID
const lastMessageIds = reactive({}) // Store last message IDs for polling

// Refs
const messageSound = ref(null)
const typingSound = ref(null)

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
      
      // Add message to local state
      if (!messages[messageData.conversation_id]) {
        messages[messageData.conversation_id] = []
      }
      messages[messageData.conversation_id].push(data.message)

      // Update last message ID for polling
      lastMessageIds[messageData.conversation_id] = data.message.id

      // Update conversation's last message
      updateConversationLastMessage(messageData.conversation_id, data.message)
    }
  } catch (error) {
    console.error('Error sending message:', error)
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

const loadMoreMessages = (conversationId) => {
  const currentMessages = messages[conversationId] || []
  const page = Math.ceil(currentMessages.length / 20) + 1
  loadMessages(conversationId, page)
}

const getMessagesForChat = (conversationId) => {
  return messages[conversationId] || []
}

// Enhanced real-time event handling with optimized Pusher channels
const subscribeToConversationChannel = (conversationId) => {
  if (!window.Echo) return

  const channelName = `private-chat.conversation.${conversationId}`

  // Unsubscribe if already subscribed
  if (window.Echo.private(channelName)) {
    window.Echo.leave(channelName)
  }

  // Subscribe to optimized message channel
  window.Echo.private(channelName)
    .listen('.message.sent', (event) => {
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
        console.log(`Message delivered in ${deliveryTime.toFixed(2)}ms`)
        
        if (message.sender.id !== currentUser.value.id) {
          playMessageSound()
          
          // Show notification if window is not focused
          if (document.hidden) {
            showMessageNotification(message, conversationId)
          }
        }
      }
    })

  // Subscribe to optimized typing indicator channel
  const typingChannelName = `private-chat.conversation.${conversationId}.typing`
  window.Echo.private(typingChannelName)
    .listen('.typing.indicator', (event) => {
      const { user, is_typing, expires_at } = event
      
      if (user.id === currentUser.value.id) return // Ignore own typing
      
      if (is_typing) {
        typingUsers[conversationId] = typingUsers[conversationId] || []
        if (!typingUsers[conversationId].some(u => u.id === user.id)) {
          typingUsers[conversationId].push(user)
          playTypingSound()
        }
        
        // Auto-cleanup based on expires_at (3 seconds)
        if (expires_at) {
          const expiryTime = new Date(expires_at).getTime()
          const now = Date.now()
          const timeoutMs = Math.max(0, expiryTime - now)
          
          setTimeout(() => {
            if (typingUsers[conversationId]) {
              typingUsers[conversationId] = typingUsers[conversationId].filter(u => u.id !== user.id)
            }
          }, timeoutMs)
        }
      } else {
        if (typingUsers[conversationId]) {
          typingUsers[conversationId] = typingUsers[conversationId].filter(u => u.id !== user.id)
        }
      }
    })
}

const unsubscribeFromConversationChannel = (conversationId) => {
  if (!window.Echo) return
  const channelName = `private-chat.conversation.${conversationId}`
  window.Echo.leave(channelName)
}

const startPolling = (conversationId) => {
  // Remove polling, replaced by Pusher events
}

const stopPolling = (conversationId) => {
  // Remove polling, replaced by Pusher events
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
        'Content-Type': 'application/json'
      },
      credentials: 'same-origin'
    })
    if (response.ok) {
      const data = await response.json()
      onlineUsers.value = data.users
    }
  } catch (error) {
    console.error('Error fetching users:', error)
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
        status_message: isOnline ? 'Active in chat' : null
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
  console.log('RealTimeChatManager mounted')

  // Set user as online
  updateUserOnlineStatus(true)

  // Fetch initial data if not provided
  if (conversations.value.length === 0) {
    console.log('Fetching conversations...')
    fetchConversations()
  }
  
  if (onlineUsers.value.length === 0) {
    console.log('Fetching all users...')
    fetchAllUsers()
  }

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

  // Subscribe to optimized presence channels
  if (window.Echo) {
    // Listen for user status updates on presence channel
    window.Echo.join('presence-chat.online-users')
      .here((users) => {
        console.log('Currently online users:', users)
        onlineUsers.value = users.filter(u => u.id !== currentUser.value.id)
      })
      .joining((user) => {
        console.log('User joined:', user)
        if (user.id !== currentUser.value.id) {
          const existingIndex = onlineUsers.value.findIndex(u => u.id === user.id)
          if (existingIndex === -1) {
            onlineUsers.value.push(user)
          }
        }
        updateUserInConversations(user)
      })
      .leaving((user) => {
        console.log('User left:', user)
        onlineUsers.value = onlineUsers.value.filter(u => u.id !== user.id)
        updateUserInConversations({ ...user, is_online: false, online_status: 'offline' })
      })
      .listen('.user.status.updated', (event) => {
        console.log('User status updated:', event)
        updateUserInConversations(event.user)
      })

    // Also listen on private user channel for personal updates
    window.Echo.private(`user.${currentUser.value.id}`)
      .listen('.user.status.updated', (event) => {
        console.log('Personal status updated:', event)
        if (event.user.id === currentUser.value.id) {
          Object.assign(currentUser.value, event.user)
        }
      })
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

  // Periodic user list refresh (less frequent now that we have real-time updates)
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
  // Update user status in the online users list
  const userIndex = onlineUsers.value.findIndex(u => u.id === user.id)
  if (userIndex !== -1) {
    onlineUsers.value[userIndex] = { ...onlineUsers.value[userIndex], ...user }
  } else if (user.is_online) {
    onlineUsers.value.push(user)
  }
  
  // Update conversation user status if it's the other user in any open chat
  openChats.value.forEach(chat => {
    if (chat.other_user && chat.other_user.id === user.id) {
      chat.other_user = { ...chat.other_user, ...user }
    }
  })
  
  // Update conversations list
  conversations.value.forEach(conversation => {
    if (conversation.other_user && conversation.other_user.id === user.id) {
      conversation.other_user = { ...conversation.other_user, ...user }
    }
  })
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

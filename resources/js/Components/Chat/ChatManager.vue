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

// Refs
const messageSound = ref(null)
const typingSound = ref(null)

// Maximum number of open chats
const MAX_OPEN_CHATS = 3

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
    openChats.value.pop()
  }

  // Add new chat to the beginning
  openChats.value.unshift(conversation)

  // Load messages for this conversation
  loadMessages(conversation.id)

  // Mark conversation as read
  markConversationAsRead(conversation.id)
}

const startChat = async (user) => {
  try {
    // Create or get existing private conversation
    const response = await fetch('/api/chat/conversations/private', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
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

  // Clean up messages and typing indicators
  delete messages[conversation.id]
  delete typingUsers[conversation.id]
}

const handleMinimize = ({ conversation, minimized }) => {
  const chat = openChats.value.find(c => c.id === conversation.id)
  if (chat) {
    chat.minimized = minimized
  }
}

const sendMessage = async (messageData) => {
  try {
    const response = await fetch(`/api/chat/conversations/${messageData.conversation_id}/messages`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
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

      // Update conversation's last message
      updateConversationLastMessage(messageData.conversation_id, data.message)

      // Broadcast via Echo if available
      if (window.Echo) {
        window.Echo.private(`conversation.${messageData.conversation_id}`)
          .whisper('message-sent', {
            message: data.message,
            sender: currentUser.value
          })
      }
    }
  } catch (error) {
    console.error('Error sending message:', error)
  }
}

const loadMessages = async (conversationId, page = 1) => {
  try {
    const response = await fetch(`/api/chat/conversations/${conversationId}/messages?page=${page}`)
    
    if (response.ok) {
      const data = await response.json()
      
      if (page === 1) {
        messages[conversationId] = data.messages
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

const handleStartTyping = (conversationId) => {
  // Broadcast typing indicator
  if (window.Echo) {
    window.Echo.private(`conversation.${conversationId}`)
      .whisper('typing-start', {
        user: currentUser.value
      })
  }
}

const handleStopTyping = (conversationId) => {
  // Broadcast stop typing
  if (window.Echo) {
    window.Echo.private(`conversation.${conversationId}`)
      .whisper('typing-stop', {
        user: currentUser.value
      })
  }
}

const handleVideoCall = (conversation) => {
  // Implement video call functionality
  console.log('Starting video call with:', conversation)
  // You can integrate with WebRTC, Jitsi, or other video calling services
}

const handlePhoneCall = (conversation) => {
  // Implement phone call functionality
  console.log('Starting phone call with:', conversation)
  // You can integrate with VoIP services
}

const handleFileUpload = async ({ files, conversation_id }) => {
  try {
    const formData = new FormData()
    formData.append('conversation_id', conversation_id)
    
    files.forEach((file, index) => {
      formData.append(`files[${index}]`, file)
    })

    const response = await fetch('/api/messages/upload', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: formData
    })

    if (response.ok) {
      const message = await response.json()
      
      // Add message to local state
      if (!messages[conversation_id]) {
        messages[conversation_id] = []
      }
      messages[conversation_id].push(message)

      // Update conversation's last message
      updateConversationLastMessage(conversation_id, message)
    }
  } catch (error) {
    console.error('Error uploading files:', error)
  }
}

const handleStatusUpdate = (status) => {
  // Update current user status
  currentUser.value.status = status
}

const markConversationAsRead = async (conversationId) => {
  try {
    await fetch(`/api/chat/conversations/${conversationId}/read`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      }
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

const setupRealTimeListeners = () => {
  if (!window.Echo) return

  // Listen for new messages
  window.Echo.private(`user.${currentUser.value.id}`)
    .listen('MessageSent', (e) => {
      const { message, conversation } = e
      
      // Add message to local state
      if (!messages[conversation.id]) {
        messages[conversation.id] = []
      }
      messages[conversation.id].push(message)

      // Update conversation
      updateConversationLastMessage(conversation.id, message)

      // Play sound if chat is not open or not focused
      const isOpen = openChats.value.some(chat => chat.id === conversation.id)
      if (!isOpen || document.hidden) {
        playMessageSound()
      }

      // Show browser notification if supported
      if ('Notification' in window && Notification.permission === 'granted') {
        new Notification(`New message from ${message.sender.name}`, {
          body: message.content,
          icon: message.sender.avatar || '/default-avatar.png',
          tag: `message-${message.id}`
        })
      }
    })

  // Listen for typing indicators
  openChats.value.forEach(chat => {
    window.Echo.private(`conversation.${chat.id}`)
      .listenForWhisper('typing-start', (e) => {
        if (e.user.id !== currentUser.value.id) {
          if (!typingUsers[chat.id]) {
            typingUsers[chat.id] = []
          }
          
          const existingUser = typingUsers[chat.id].find(u => u.id === e.user.id)
          if (!existingUser) {
            typingUsers[chat.id].push(e.user)
            playTypingSound()
          }
        }
      })
      .listenForWhisper('typing-stop', (e) => {
        if (typingUsers[chat.id]) {
          typingUsers[chat.id] = typingUsers[chat.id].filter(u => u.id !== e.user.id)
        }
      })
  })

  // Listen for user status updates
  window.Echo.channel('online-users')
    .listen('UserStatusUpdated', (e) => {
      const user = onlineUsers.value.find(u => u.id === e.user.id)
      if (user) {
        Object.assign(user, e.user)
      }
    })
}

const fetchConversations = async () => {
  try {
    const response = await fetch('/api/chat/conversations', {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      }
    })
    if (response.ok) {
      const data = await response.json()
      conversations.value = data.conversations || []
    } else {
      console.error('Failed to fetch conversations:', response.status, response.statusText)
    }
  } catch (error) {
    console.error('Error fetching conversations:', error)
  }
}

const fetchAllUsers = async () => {
  try {
    const response = await fetch('/api/chat/users', {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    
    if (response.ok) {
      const data = await response.json()
      onlineUsers.value = data.users || []
    } else {
      console.error('Failed to fetch users:', response.status, response.statusText)
    }
  } catch (error) {
    console.error('Error fetching users:', error)
  }
}

const fetchOnlineUsers = async () => {
  try {
    const response = await fetch('/api/chat/online-users')
    if (response.ok) {
      const data = await response.json()
      onlineUsers.value = data.online_users
    }
  } catch (error) {
    console.error('Error fetching online users:', error)
  }
}

const updateUserOnlineStatus = async (isOnline = true) => {
  try {
    await fetch('/api/chat/online-status', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify({ is_online: isOnline })
    })
  } catch (error) {
    console.error('Error updating online status:', error)
  }
}

// Lifecycle
onMounted(() => {
  // Set user as online
  updateUserOnlineStatus(true)

  // Setup real-time listeners
  setupRealTimeListeners()

  // Fetch initial data if not provided
  if (conversations.value.length === 0) {
    fetchConversations()
  }
  
  if (onlineUsers.value.length === 0) {
    fetchAllUsers() // Fetch all users (online and offline)
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

  // Periodic online status update
  setInterval(() => {
    updateUserOnlineStatus(true)
  }, 30000) // Every 30 seconds
})

onUnmounted(() => {
  // Set user as offline
  updateUserOnlineStatus(false)

  // Clean up Echo listeners
  if (window.Echo) {
    window.Echo.leaveChannel(`user.${currentUser.value.id}`)
    openChats.value.forEach(chat => {
      window.Echo.leaveChannel(`conversation.${chat.id}`)
    })
  }
})

// Expose methods for external use
defineExpose({
  openChat,
  startChat,
  closeChat,
  sendMessage
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

<template>
  <div class="floating-chat-box">
    <!-- Chat Window -->
    <div 
      class="fixed bottom-0 bg-white rounded-t-xl shadow-2xl border border-gray-200 flex flex-col transition-all duration-300 ease-in-out z-40"
      :class="[
        isMinimized ? 'h-12' : 'h-96',
        { 'animate-bounce-in': isNewMessage }
      ]"
      :style="{ width: '320px', right: `${position * 340 + 20}px` }"
    >
      <!-- Header -->
      <div 
        @click="toggleMinimize"
        class="flex items-center justify-between p-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-t-xl cursor-pointer hover:from-blue-700 hover:to-blue-800 transition-all duration-200"
      >
        <div class="flex items-center space-x-3 flex-1 min-w-0">
          <!-- Avatar -->
          <div class="relative flex-shrink-0">
            <img 
              :src="conversation.avatar || '/default-avatar.png'" 
              :alt="conversation.title"
              class="w-8 h-8 rounded-full object-cover ring-2 ring-white/30"
            >
            <!-- Online Status -->
            <div 
              v-if="conversation.other_user?.is_online"
              class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-green-400 border-2 border-white rounded-full"
            ></div>
          </div>

          <!-- Title and Status -->
          <div class="flex-1 min-w-0">
            <h3 class="text-sm font-semibold truncate">{{ conversation.title }}</h3>
            <p class="text-xs text-blue-100 truncate">
              <span v-if="isTyping" class="text-green-200">{{ typingText }}</span>
              <span v-else-if="conversation.other_user?.is_online" class="text-green-200">Active now</span>
              <span v-else>{{ getLastSeenText() }}</span>
            </p>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center space-x-1">
          <!-- Minimize/Maximize -->
          <button 
            @click.stop="toggleMinimize"
            class="p-1.5 hover:bg-white/20 rounded-full transition-colors"
            :title="isMinimized ? 'Maximize' : 'Minimize'"
          >
            <svg v-if="isMinimized" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
            </svg>
            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
          </button>

          <!-- Close -->
          <button 
            @click.stop="closeChat"
            class="p-1.5 hover:bg-white/20 rounded-full transition-colors"
            title="Close"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      </div>

      <!-- Messages Area -->
      <div 
        v-if="!isMinimized"
        ref="messagesContainer"
        class="flex-1 overflow-y-auto p-4 bg-gradient-to-b from-gray-50 to-white"
        @scroll="handleScroll"
      >
        <!-- Loading More Messages -->
        <div v-if="loadingMore" class="text-center py-2 mb-4">
          <div class="inline-flex items-center space-x-2 text-gray-500">
            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
            <span class="text-sm">Loading more messages...</span>
          </div>
        </div>

        <!-- Messages -->
        <div class="space-y-4">
          <div 
            v-for="message in messages" 
            :key="message.id"
            class="message-item"
          >
            <!-- Own Messages (Right Side) -->
            <div 
              v-if="message.sender_id === currentUser.id"
              class="flex justify-end"
            >
              <div class="max-w-xs lg:max-w-md">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-4 py-2 rounded-2xl rounded-br-md shadow-lg">
                  <!-- Reply To -->
                  <div 
                    v-if="message.reply_to"
                    class="bg-blue-400 bg-opacity-30 p-2 rounded mb-2 text-xs border-l-2 border-blue-200"
                  >
                    <div class="font-medium">{{ message.reply_to.sender?.name }}</div>
                    <div class="opacity-90">{{ message.reply_to.content }}</div>
                  </div>
                  
                  <!-- Message Content -->
                  <div class="text-sm">{{ message.content }}</div>
                  
                  <!-- Timestamp -->
                  <div class="text-xs opacity-75 mt-1 text-right">
                    {{ formatTime(message.created_at) }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Other Users' Messages (Left Side) -->
            <div 
              v-else
              class="flex justify-start"
            >
              <!-- Avatar -->
              <img 
                :src="message.sender?.avatar || '/default-avatar.png'" 
                :alt="message.sender?.name"
                class="w-8 h-8 rounded-full object-cover flex-shrink-0 mr-3 mt-1"
              >
              
              <div class="max-w-xs lg:max-w-md">
                <!-- Sender Name -->
                <div class="text-xs text-gray-600 mb-1 font-medium">
                  {{ message.sender?.name }}
                </div>
                
                <div 
                  class="px-4 py-2 rounded-2xl rounded-bl-md shadow-lg text-white"
                  :class="getUserMessageStyle(message.sender_id)"
                >
                  <!-- Reply To -->
                  <div 
                    v-if="message.reply_to"
                    class="mb-2 p-2 rounded-lg opacity-80 text-xs border-l-2 border-white/30"
                  >
                    <div class="font-medium">{{ message.reply_to.sender?.name }}</div>
                    <div>{{ message.reply_to.content }}</div>
                  </div>

                  <!-- Message Text -->
                  <div class="text-sm">{{ message.content }}</div>

                  <!-- Timestamp -->
                  <div class="text-xs opacity-75 mt-1">
                    {{ formatTime(message.created_at) }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Typing Indicator -->
        <div v-if="isTyping" class="flex items-center space-x-2 mt-4">
          <img 
            :src="conversation.other_user?.avatar || '/default-avatar.png'" 
            :alt="conversation.other_user?.name"
            class="w-6 h-6 rounded-full object-cover"
          >
          <div class="bg-white rounded-2xl rounded-bl-md px-4 py-2 shadow-sm border border-gray-200">
            <div class="typing-indicator">
              <span></span>
              <span></span>
              <span></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Input Area -->
      <div 
        v-if="!isMinimized"
        class="border-t border-gray-200 p-3 bg-white rounded-b-xl"
      >
        <!-- Reply Preview -->
        <div 
          v-if="replyingTo"
          class="mb-2 p-2 bg-gray-50 rounded-lg border-l-4 border-blue-600"
        >
          <div class="flex items-center justify-between">
            <div class="flex-1 min-w-0">
              <p class="text-xs font-medium text-gray-900">Replying to {{ replyingTo.sender?.name }}</p>
              <p class="text-xs text-gray-600 truncate">{{ replyingTo.content }}</p>
            </div>
            <button 
              @click="cancelReply"
              class="p-1 text-gray-400 hover:text-gray-600 transition-colors"
            >
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
        </div>

        <!-- Input Row -->
        <div class="flex items-end space-x-2">
          <!-- Message Input -->
          <div class="flex-1 relative">
            <textarea
              ref="messageInput"
              v-model="newMessage"
              @keydown="handleKeyDown"
              @input="handleTyping"
              placeholder="Type a message..."
              rows="1"
              class="w-full px-4 py-2 border border-gray-300 rounded-full focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none text-sm max-h-20 overflow-y-auto"
            ></textarea>
          </div>

          <!-- Send Button -->
          <button 
            v-if="newMessage.trim()"
            @click="sendMessage"
            :disabled="sending"
            class="p-2 bg-blue-600 text-white rounded-full hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            title="Send message"
          >
            <svg v-if="!sending" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
            </svg>
            <div v-else class="animate-spin rounded-full h-5 w-5 border-b-2 border-white"></div>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

// Props
const props = defineProps({
  conversation: {
    type: Object,
    required: true
  },
  position: {
    type: Number,
    default: 0
  },
  messages: {
    type: Array,
    default: () => []
  }
})

// Emits
const emit = defineEmits([
  'close', 
  'minimize', 
  'send-message', 
  'load-more-messages',
  'start-typing',
  'stop-typing'
])

// Reactive data
const isMinimized = ref(false)
const newMessage = ref('')
const sending = ref(false)
const isTyping = ref(false)
const typingTimeout = ref(null)
const replyingTo = ref(null)
const loadingMore = ref(false)
const isNewMessage = ref(false)

// User color mapping for different message styles
const userColors = ref({})
const availableColors = [
  'bg-gradient-to-r from-purple-500 to-purple-600',
  'bg-gradient-to-r from-green-500 to-green-600',
  'bg-gradient-to-r from-red-500 to-red-600',
  'bg-gradient-to-r from-yellow-500 to-yellow-600',
  'bg-gradient-to-r from-indigo-500 to-indigo-600',
  'bg-gradient-to-r from-pink-500 to-pink-600',
  'bg-gradient-to-r from-teal-500 to-teal-600',
  'bg-gradient-to-r from-orange-500 to-orange-600'
]

// Refs
const messagesContainer = ref(null)
const messageInput = ref(null)

// Get current user
const currentUser = computed(() => usePage().props.auth?.user || {})

// Computed properties
const typingText = computed(() => {
  return `${props.conversation.other_user?.name} is typing...`
})

// Methods
const toggleMinimize = () => {
  isMinimized.value = !isMinimized.value
  emit('minimize', { conversation: props.conversation, minimized: isMinimized.value })
  
  if (!isMinimized.value) {
    nextTick(() => {
      scrollToBottom()
      messageInput.value?.focus()
    })
  }
}

const closeChat = () => {
  emit('close', props.conversation)
}

const sendMessage = async () => {
  if (!newMessage.value.trim() || sending.value) return

  const messageData = {
    content: newMessage.value.trim(),
    conversation_id: props.conversation.id,
    reply_to_id: replyingTo.value?.id || null
  }

  sending.value = true
  newMessage.value = ''
  replyingTo.value = null

  try {
    emit('send-message', messageData)
  } finally {
    sending.value = false
    nextTick(() => {
      scrollToBottom()
      messageInput.value?.focus()
    })
  }
}

const handleKeyDown = (event) => {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault()
    sendMessage()
  }
}

const handleTyping = () => {
  if (!isTyping.value) {
    isTyping.value = true
    emit('start-typing', props.conversation.id)
  }

  // Clear existing timeout
  if (typingTimeout.value) {
    clearTimeout(typingTimeout.value)
  }

  // Set new timeout
  typingTimeout.value = setTimeout(() => {
    isTyping.value = false
    emit('stop-typing', props.conversation.id)
  }, 1000)
}

const scrollToBottom = () => {
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
  }
}

const handleScroll = () => {
  if (messagesContainer.value.scrollTop === 0 && !loadingMore.value) {
    loadingMore.value = true
    emit('load-more-messages', props.conversation.id)
    
    // Reset loading state after a delay
    setTimeout(() => {
      loadingMore.value = false
    }, 1000)
  }
}

const formatTime = (timestamp) => {
  const date = new Date(timestamp)
  const now = new Date()
  const diffInMinutes = Math.floor((now - date) / (1000 * 60))
  
  if (diffInMinutes < 1) return 'now'
  if (diffInMinutes < 60) return `${diffInMinutes}m`
  if (diffInMinutes < 1440) return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

const getLastSeenText = () => {
  if (!props.conversation.other_user?.last_seen_at) return 'Offline'
  
  const lastSeen = new Date(props.conversation.other_user.last_seen_at)
  const now = new Date()
  const diffInMinutes = Math.floor((now - lastSeen) / (1000 * 60))
  
  if (diffInMinutes < 5) return 'Active recently'
  if (diffInMinutes < 60) return `Active ${diffInMinutes}m ago`
  if (diffInMinutes < 1440) return `Active ${Math.floor(diffInMinutes / 60)}h ago`
  return `Active ${Math.floor(diffInMinutes / 1440)}d ago`
}

const cancelReply = () => {
  replyingTo.value = null
}

const getUserMessageStyle = (senderId) => {
  // Assign consistent color to each user
  if (!userColors.value[senderId]) {
    const colorIndex = Object.keys(userColors.value).length % availableColors.length
    userColors.value[senderId] = availableColors[colorIndex]
  }
  return userColors.value[senderId]
}

// Watch for new messages to scroll to bottom
watch(() => props.messages, (newMessages) => {
  nextTick(() => {
    scrollToBottom()
  })
}, { deep: true })

// Lifecycle
onMounted(() => {
  nextTick(() => {
    scrollToBottom()
  })
})

onUnmounted(() => {
  if (typingTimeout.value) {
    clearTimeout(typingTimeout.value)
  }
})
</script>

<style scoped>
.typing-indicator {
  display: flex;
  align-items: center;
  gap: 2px;
}

.typing-indicator span {
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background-color: #3b82f6;
  animation: typing 1.4s infinite ease-in-out;
}

.typing-indicator span:nth-child(1) {
  animation-delay: -0.32s;
}

.typing-indicator span:nth-child(2) {
  animation-delay: -0.16s;
}

@keyframes typing {
  0%, 80%, 100% {
    transform: scale(0.8);
    opacity: 0.5;
  }
  40% {
    transform: scale(1);
    opacity: 1;
  }
}

@keyframes bounce-in {
  0% {
    transform: scale(0.95);
  }
  50% {
    transform: scale(1.02);
  }
  100% {
    transform: scale(1);
  }
}

.animate-bounce-in {
  animation: bounce-in 0.3s ease-out;
}

/* Ensure new messages appear at the bottom */
.space-y-4 > * + * {
  margin-top: 1rem;
}
</style>

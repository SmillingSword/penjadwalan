<template>
  <div class="chat-sidebar">
    <!-- Chat Sidebar Toggle Button -->
    <button 
      @click="toggleSidebar"
      class="chat-toggle-btn fixed right-4 top-1/2 transform -translate-y-1/2 z-40 bg-indigo-600 hover:bg-indigo-700 text-white p-3 rounded-full shadow-lg transition-all duration-300"
      :class="{ 'right-80': sidebarOpen }"
    >
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
              d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.013 8.013 0 01-7-4L5 20l4-1a8.013 8.013 0 01-7-4c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
      </svg>
    </button>

    <!-- Chat Sidebar Panel -->
    <Transition
      enter-active-class="transition-all duration-300 ease-out"
      enter-from-class="transform translate-x-full opacity-0"
      enter-to-class="transform translate-x-0 opacity-100"
      leave-active-class="transition-all duration-300 ease-in"
      leave-from-class="transform translate-x-0 opacity-100"
      leave-to-class="transform translate-x-full opacity-0"
    >
      <div 
        v-if="sidebarOpen"
        class="fixed right-0 top-0 h-full w-80 bg-white shadow-2xl border-l border-gray-200 z-30 flex flex-col"
      >
        <!-- Header -->
        <div class="p-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Chat</h3>
            <button 
              @click="closeSidebar"
              class="text-gray-400 hover:text-gray-600 transition-colors p-1 rounded-lg hover:bg-gray-100"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          
          <!-- Search Bar -->
          <div class="mt-3 relative">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search conversations..."
              class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm"
            >
            <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
          </div>
        </div>

        <!-- Tabs -->
        <div class="flex border-b border-gray-200">
          <button 
            @click="activeTab = 'conversations'"
            class="flex-1 py-3 px-4 text-sm font-medium transition-colors"
            :class="activeTab === 'conversations' 
              ? 'text-indigo-600 border-b-2 border-indigo-600 bg-indigo-50' 
              : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
          >
            Chats
            <span v-if="unreadCount > 0" class="ml-2 bg-red-500 text-white text-xs rounded-full px-2 py-0.5">
              {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
          </button>
          <button 
            @click="activeTab = 'contacts'"
            class="flex-1 py-3 px-4 text-sm font-medium transition-colors"
            :class="activeTab === 'contacts' 
              ? 'text-indigo-600 border-b-2 border-indigo-600 bg-indigo-50' 
              : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
          >
            Users
            <span v-if="totalUsersCount > 0" class="ml-2 bg-blue-500 text-white text-xs rounded-full px-2 py-0.5">
              {{ totalUsersCount }}
            </span>
            <span v-if="onlineCount > 0" class="ml-1 bg-green-500 text-white text-xs rounded-full px-1.5 py-0.5">
              {{ onlineCount }}
            </span>
          </button>
        </div>

        <!-- Content Area -->
        <div class="flex-1 overflow-y-auto">
          <!-- Conversations Tab -->
          <div v-if="activeTab === 'conversations'" class="p-2">
            <div v-if="filteredConversations.length === 0" class="text-center py-8 text-gray-500">
              <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.013 8.013 0 01-7-4L5 20l4-1a8.013 8.013 0 01-7-4c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
              </svg>
              <p class="text-sm">No conversations yet</p>
              <p class="text-xs text-gray-400 mt-1">Start chatting with your team!</p>
            </div>

            <div 
              v-for="conversation in filteredConversations" 
              :key="conversation.id"
              @click="openChat(conversation)"
              class="conversation-item flex items-center p-3 rounded-lg hover:bg-gray-50 cursor-pointer transition-all duration-200 group"
              :class="{ 'bg-indigo-50 border border-indigo-200': conversation.unread_count > 0 }"
            >
              <!-- Avatar -->
              <div class="relative flex-shrink-0">
                <div class="w-12 h-12 rounded-full overflow-hidden ring-2 ring-white shadow-sm">
                  <img v-if="getUserAvatar(conversation.other_user)" 
                       :src="getUserAvatar(conversation.other_user)" 
                       :alt="conversation.title"
                       class="w-full h-full object-cover"
                  />
                  <div v-else class="w-full h-full bg-gradient-to-r from-indigo-500 to-purple-500 flex items-center justify-center">
                    <span class="text-white text-sm font-bold">{{ getUserInitials(conversation.other_user || { name: conversation.title }) }}</span>
                  </div>
                </div>
                <!-- Online Status -->
                <div 
                  v-if="conversation.other_user?.is_online"
                  class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 border-2 border-white rounded-full"
                ></div>
                <!-- Google Badge -->
                <div v-if="isGoogleUser(conversation.other_user) && getUserAvatar(conversation.other_user)" 
                     class="absolute -top-1 -right-1 w-4 h-4 bg-white rounded-full flex items-center justify-center shadow-sm">
                  <svg class="w-2.5 h-2.5" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                  </svg>
                </div>
              </div>

              <!-- Content -->
              <div class="ml-3 flex-1 min-w-0">
                <div class="flex items-center justify-between">
                  <h4 class="text-sm font-medium text-gray-900 truncate group-hover:text-indigo-600 transition-colors">
                    {{ conversation.title }}
                  </h4>
                  <div class="flex items-center space-x-2">
                    <span v-if="conversation.unread_count > 0" 
                          class="bg-indigo-600 text-white text-xs rounded-full px-2 py-0.5 font-medium">
                      {{ conversation.unread_count > 99 ? '99+' : conversation.unread_count }}
                    </span>
                    <span class="text-xs text-gray-500">
                      {{ formatTime(conversation.last_message_at) }}
                    </span>
                  </div>
                </div>
                
                <p class="text-sm text-gray-600 truncate mt-1">
                  <span v-if="conversation.last_message?.sender_id === currentUser.id" class="text-gray-500">You: </span>
                  {{ conversation.last_message?.content || 'No messages yet' }}
                </p>

                <!-- Typing Indicator -->
                <div v-if="conversation.typing_users?.length > 0" class="flex items-center mt-1">
                  <div class="typing-indicator">
                    <span></span>
                    <span></span>
                    <span></span>
                  </div>
                  <span class="text-xs text-indigo-600 ml-2">
                    {{ getTypingText(conversation.typing_users) }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Users Tab -->
          <div v-if="activeTab === 'contacts'" class="p-2">
            <div v-if="filteredOnlineUsers.length === 0" class="text-center py-8 text-gray-500">
              <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
              </svg>
              <p class="text-sm">No users found</p>
            </div>

            <div 
              v-for="user in filteredOnlineUsers" 
              :key="user.id"
              @click="startChat(user)"
              class="contact-item flex items-center p-3 rounded-lg hover:bg-gray-50 cursor-pointer transition-all duration-200 group"
              :class="{ 'bg-green-50 border border-green-200': isUserTrulyOnline(user) }"
            >
              <!-- Avatar -->
              <div class="relative flex-shrink-0">
                <div class="w-10 h-10 rounded-full overflow-hidden ring-2 ring-white shadow-sm">
                  <img v-if="getUserAvatar(user)" 
                       :src="getUserAvatar(user)" 
                       :alt="user.name"
                       class="w-full h-full object-cover"
                  />
                  <div v-else class="w-full h-full bg-gradient-to-r from-blue-500 to-purple-500 flex items-center justify-center">
                    <span class="text-white text-xs font-bold">{{ getUserInitials(user) }}</span>
                  </div>
                </div>
                <!-- Status Indicator -->
                <div 
                  class="absolute -bottom-1 -right-1 w-3 h-3 border-2 border-white rounded-full"
                  :class="getStatusIndicatorClass(user)"
                ></div>
                <!-- Google Badge -->
                <div v-if="isGoogleUser(user) && getUserAvatar(user)" 
                     class="absolute -top-0.5 -right-0.5 w-3 h-3 bg-white rounded-full flex items-center justify-center shadow-sm">
                  <svg class="w-2 h-2" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                  </svg>
                </div>
              </div>

              <!-- Content -->
              <div class="ml-3 flex-1 min-w-0">
                <div class="flex items-center justify-between">
                  <h4 class="text-sm font-medium text-gray-900 truncate group-hover:text-indigo-600 transition-colors">
                    {{ user.name }}
                  </h4>
                  <span v-if="isUserTrulyOnline(user)" class="text-xs text-green-600 font-medium">Online</span>
                  <span v-else class="text-xs text-gray-400">{{ formatLastSeen(user.last_seen_at) }}</span>
                </div>
                <p class="text-xs text-gray-500 truncate">
                  {{ user.status_message || getStatusText(isUserTrulyOnline(user) ? (user.status || 'available') : 'offline') }}
                </p>
              </div>

              <!-- Quick Actions -->
              <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                <button 
                  @click.stop="startChat(user)"
                  class="p-1 text-gray-400 hover:text-indigo-600 transition-colors"
                  title="Start chat"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.013 8.013 0 01-7-4L5 20l4-1a8.013 8.013 0 01-7-4c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="p-3 border-t border-gray-200 bg-gray-50">
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
              <div class="relative">
                <div class="w-8 h-8 rounded-full overflow-hidden">
                  <img v-if="getUserAvatar(currentUser)" 
                       :src="getUserAvatar(currentUser)" 
                       :alt="currentUser.name"
                       class="w-full h-full object-cover"
                  />
                  <div v-else class="w-full h-full bg-gradient-to-r from-indigo-500 to-purple-500 flex items-center justify-center">
                    <span class="text-white text-xs font-bold">{{ getUserInitials(currentUser) }}</span>
                  </div>
                </div>
                <div 
                  class="absolute -bottom-1 -right-1 w-3 h-3 border-2 border-white rounded-full"
                  :class="getStatusColor(currentUser.status)"
                ></div>
                <!-- Google Badge for current user -->
                <div v-if="isGoogleUser(currentUser) && getUserAvatar(currentUser)" 
                     class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 bg-white rounded-full flex items-center justify-center shadow-sm">
                  <svg class="w-1.5 h-1.5" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                  </svg>
                </div>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-900">{{ currentUser.name }}</p>
                <p class="text-xs text-gray-500">{{ getStatusText(currentUser.status) }}</p>
              </div>
            </div>
            
            <!-- Status Dropdown -->
            <div class="relative">
              <button 
                @click="showStatusMenu = !showStatusMenu"
                class="p-1 text-gray-400 hover:text-gray-600 transition-colors"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                </svg>
              </button>

              <Transition
                enter-active-class="transition ease-out duration-100"
                enter-from-class="transform opacity-0 scale-95"
                enter-to-class="transform opacity-100 scale-100"
                leave-active-class="transition ease-in duration-75"
                leave-from-class="transform opacity-100 scale-100"
                leave-to-class="transform opacity-0 scale-95"
              >
                <div 
                  v-if="showStatusMenu"
                  class="absolute bottom-full right-0 mb-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50"
                >
                  <button 
                    v-for="status in statusOptions" 
                    :key="status.value"
                    @click="updateStatus(status.value)"
                    class="w-full flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors"
                  >
                    <div :class="status.color" class="w-3 h-3 rounded-full mr-3"></div>
                    {{ status.label }}
                  </button>
                </div>
              </Transition>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Backdrop -->
    <Transition
      enter-active-class="transition-opacity duration-300"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-300"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div 
        v-if="sidebarOpen"
        @click="closeSidebar"
        class="fixed inset-0 bg-black bg-opacity-25 z-20"
      ></div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'

// Props
const props = defineProps({
  conversations: {
    type: Array,
    default: () => []
  },
  onlineUsers: {
    type: Array,
    default: () => []
  }
})

// Emits
const emit = defineEmits(['open-chat', 'start-chat', 'status-updated'])

// Reactive data
const sidebarOpen = ref(false)
const activeTab = ref('conversations')
const searchQuery = ref('')
const showStatusMenu = ref(false)

// Get current user
const currentUser = computed(() => usePage().props.auth?.user || {})

// Helper function to get user avatar
const getUserAvatar = (user) => {
  if (user.avatar) {
    // If it's a Google avatar (starts with http), use it directly
    if (user.avatar.startsWith('http')) {
      return user.avatar
    }
    // Otherwise, it's a local file in storage
    return `/storage/${user.avatar}`
  }
  return null
}

// Helper function to get user initials
const getUserInitials = (user) => {
  return user.name
    .split(' ')
    .map(name => name.charAt(0))
    .join('')
    .toUpperCase()
    .slice(0, 2)
}

// Helper function to check if user has Google account
const isGoogleUser = (user) => {
  return user.google_id !== null
}

// Status options
const statusOptions = [
  { value: 'available', label: 'Available', color: 'bg-green-500' },
  { value: 'busy', label: 'Busy', color: 'bg-red-500' },
  { value: 'away', label: 'Away', color: 'bg-yellow-500' },
  { value: 'invisible', label: 'Invisible', color: 'bg-gray-400' },
  { value: 'offline', label: 'Offline', color: 'bg-gray-400' }
]

// Computed properties
const filteredConversations = computed(() => {
  if (!searchQuery.value) return props.conversations
  
  return props.conversations.filter(conversation =>
    conversation.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
    conversation.last_message?.content.toLowerCase().includes(searchQuery.value.toLowerCase())
  )
})

const filteredOnlineUsers = computed(() => {
  console.log('🔍 ChatSidebar - Computing filteredOnlineUsers')
  console.log('📊 props.onlineUsers:', props.onlineUsers?.length || 0)
  console.log('👥 Users data:', props.onlineUsers?.map(u => `${u.name} (is_online: ${u.is_online}, type: ${typeof u.is_online})`))
  
  if (!searchQuery.value) return props.onlineUsers || []
  
  return (props.onlineUsers || []).filter(user =>
    user.name.toLowerCase().includes(searchQuery.value.toLowerCase())
  )
})

// Helper function to determine if user is truly online
const isUserTrulyOnline = (user) => {
  // Only check is_online field - true = green, false = gray
  console.log('🔍 Checking user online status:', user.name, 'is_online:', user.is_online, 'type:', typeof user.is_online)
  return user.is_online === true
}

// Helper function to get status indicator color class
const getStatusIndicatorClass = (user) => {
  const isOnline = user.is_online === true
  console.log('🎨 Status indicator for', user.name, '- is_online:', user.is_online, 'returning:', isOnline ? 'bg-green-500' : 'bg-gray-400')
  return isOnline ? 'bg-green-500' : 'bg-gray-400'
}

const unreadCount = computed(() => {
  return props.conversations.reduce((total, conv) => total + (conv.unread_count || 0), 0)
})

const onlineCount = computed(() => {
  return props.onlineUsers.filter(user => isUserTrulyOnline(user)).length
})

const totalUsersCount = computed(() => {
  return props.onlineUsers.length
})

// Methods
const toggleSidebar = () => {
  sidebarOpen.value = !sidebarOpen.value
}

const closeSidebar = () => {
  sidebarOpen.value = false
  showStatusMenu.value = false
}

const openChat = (conversation) => {
  emit('open-chat', conversation)
}

const startChat = (user) => {
  emit('start-chat', user)
}

const updateStatus = async (status) => {
  try {
    await fetch('/api/realtime-chat/online-status', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        status: status,
        is_online: status !== 'offline',
        status_message: status === 'available' ? 'Active in chat' : null
      })
    })

    emit('status-updated', status)
    showStatusMenu.value = false
  } catch (error) {
    console.error('Error updating status:', error)
  }
}

const getStatusColor = (status) => {
  const colors = {
    available: 'bg-green-500',
    online: 'bg-green-500',
    busy: 'bg-red-500',
    away: 'bg-yellow-500',
    invisible: 'bg-gray-400',
    offline: 'bg-gray-400'
  }
  return colors[status] || 'bg-green-500' // Default to green for online users
}

const getStatusText = (status) => {
  const texts = {
    available: 'Available',
    online: 'Online',
    busy: 'Busy',
    away: 'Away',
    invisible: 'Invisible',
    offline: 'Offline'
  }
  return texts[status] || 'Available'
}

const formatTime = (timestamp) => {
  if (!timestamp) return ''
  
  const date = new Date(timestamp)
  const now = new Date()
  const diffInMinutes = Math.floor((now - date) / (1000 * 60))
  
  if (diffInMinutes < 1) return 'now'
  if (diffInMinutes < 60) return `${diffInMinutes}m`
  if (diffInMinutes < 1440) return `${Math.floor(diffInMinutes / 60)}h`
  return `${Math.floor(diffInMinutes / 1440)}d`
}

const formatLastSeen = (timestamp) => {
  if (!timestamp) return 'Never'
  
  const date = new Date(timestamp)
  const now = new Date()
  const diffInMinutes = Math.floor((now - date) / (1000 * 60))
  
  if (diffInMinutes < 1) return 'Just now'
  if (diffInMinutes < 60) return `${diffInMinutes}m ago`
  if (diffInMinutes < 1440) return `${Math.floor(diffInMinutes / 60)}h ago`
  return `${Math.floor(diffInMinutes / 1440)}d ago`
}

const getTypingText = (typingUsers) => {
  if (typingUsers.length === 1) {
    return `${typingUsers[0].name} is typing...`
  } else if (typingUsers.length === 2) {
    return `${typingUsers[0].name} and ${typingUsers[1].name} are typing...`
  } else {
    return `${typingUsers.length} people are typing...`
  }
}

// Lifecycle
onMounted(() => {
  // Close sidebar when clicking outside
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.chat-sidebar')) {
      showStatusMenu.value = false
    }
  })
})

onUnmounted(() => {
  // Cleanup event listeners
})
</script>

<style scoped>
.conversation-item:hover {
  transform: translateX(-2px);
}

.contact-item:hover {
  transform: translateX(-2px);
}

.typing-indicator {
  display: flex;
  align-items: center;
  gap: 2px;
}

.typing-indicator span {
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background-color: #6366f1;
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

.chat-toggle-btn {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.chat-toggle-btn:hover {
  transform: translateY(-50%) scale(1.1);
  box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
}
</style>

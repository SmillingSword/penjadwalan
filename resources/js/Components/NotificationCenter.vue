<template>
  <div class="notification-center">
    <!-- Notification Bell Icon -->
    <div class="relative">
      <button 
        @click="toggleNotifications"
        class="notification-bell relative p-3 text-gray-600 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded-xl transition-all duration-300 hover:bg-gray-50 active:scale-95"
        :class="{ 
          'animate-pulse': hasUnreadNotifications,
          'ring-2 ring-indigo-200': showNotifications 
        }"
      >
        <!-- Bell Icon with enhanced styling -->
        <svg class="w-6 h-6 transition-transform duration-300" 
             :class="{ 'rotate-12': hasUnreadNotifications }" 
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
        </svg>
        
        <!-- Enhanced Notification Badge -->
        <Transition
          enter-active-class="transition-all duration-300 ease-out"
          enter-from-class="scale-0 opacity-0"
          enter-to-class="scale-100 opacity-100"
          leave-active-class="transition-all duration-200 ease-in"
          leave-from-class="scale-100 opacity-100"
          leave-to-class="scale-0 opacity-0"
        >
          <span 
            v-if="unreadCount > 0"
            class="absolute -top-1 -right-1 bg-gradient-to-r from-red-500 to-pink-500 text-white text-xs rounded-full h-6 w-6 flex items-center justify-center font-bold shadow-lg ring-2 ring-white"
            :class="{ 'animate-bounce': recentNotification }"
          >
            {{ unreadCount > 99 ? '99+' : unreadCount }}
          </span>
        </Transition>
      </button>

      <!-- Enhanced Notification Dropdown -->
      <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 scale-95 translate-y-2"
        enter-to-class="opacity-100 scale-100 translate-y-0"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100 scale-100 translate-y-0"
        leave-to-class="opacity-0 scale-95 translate-y-2"
      >
        <div 
          v-if="showNotifications"
          class="absolute right-0 mt-3 w-96 bg-white/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-gray-200/50 z-50 max-h-[32rem] overflow-hidden"
          :class="{ 'w-80': isMobile }"
          @click.stop
        >
          <!-- Enhanced Header -->
          <div class="px-6 py-4 border-b border-gray-200/50 bg-gradient-to-r from-indigo-50/80 to-purple-50/80 backdrop-blur-sm">
            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-lg flex items-center justify-center">
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                  </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900">Notifications</h3>
                <span v-if="unreadCount > 0" class="px-2 py-1 bg-red-100 text-red-600 text-xs font-semibold rounded-full">
                  {{ unreadCount }}
                </span>
              </div>
              <div class="flex items-center space-x-2">
                <button 
                  v-if="unreadCount > 0"
                  @click="markAllAsRead"
                  class="text-sm text-indigo-600 hover:text-indigo-800 font-medium transition-all duration-200 hover:bg-indigo-50 px-3 py-1 rounded-lg"
                >
                  Mark all read
                </button>
                <button 
                  @click="closeNotifications"
                  class="text-gray-400 hover:text-gray-600 transition-all duration-200 hover:bg-gray-100 p-2 rounded-lg"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <!-- Enhanced Notifications List -->
          <div class="max-h-80 overflow-y-auto custom-scrollbar">
            <!-- Empty State -->
            <div v-if="notifications.length === 0" class="p-8 text-center text-gray-500">
              <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
              </div>
              <p class="text-sm font-medium text-gray-600">No notifications yet</p>
              <p class="text-xs text-gray-400 mt-1">We'll notify you when something important happens</p>
            </div>

            <!-- Notification Items with enhanced animations -->
            <TransitionGroup
              name="notification-list"
              tag="div"
              enter-active-class="transition-all duration-300 ease-out"
              enter-from-class="opacity-0 transform translate-x-4"
              enter-to-class="opacity-100 transform translate-x-0"
              leave-active-class="transition-all duration-200 ease-in"
              leave-from-class="opacity-100 transform translate-x-0"
              leave-to-class="opacity-0 transform -translate-x-4"
            >
              <div 
                v-for="notification in notifications" 
                :key="notification.id"
                class="notification-item border-b border-gray-100/50 hover:bg-gradient-to-r hover:from-gray-50 hover:to-indigo-50/30 transition-all duration-300 cursor-pointer group relative overflow-hidden"
                :class="{ 
                  'bg-gradient-to-r from-blue-50/50 to-indigo-50/30 border-l-4 border-l-blue-400': !notification.read_at,
                  'hover:shadow-sm': !notification.read_at
                }"
                @click="handleNotificationClick(notification)"
                @touchstart="handleTouchStart"
                @touchmove="handleTouchMove"
                @touchend="handleTouchEnd"
              >
                <div class="p-4">
              <div class="flex items-start space-x-3">
                <!-- Notification Icon -->
                <div class="flex-shrink-0">
                  <div 
                    class="w-10 h-10 rounded-full flex items-center justify-center text-lg"
                    :class="getNotificationIconClass(notification)"
                  >
                    {{ notification.data.icon || '🔔' }}
                  </div>
                </div>

                <!-- Notification Content -->
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-900 truncate">
                      {{ notification.data.title }}
                    </p>
                    <div class="flex items-center space-x-2">
                      <span 
                        v-if="!notification.read_at"
                        class="w-2 h-2 bg-blue-500 rounded-full"
                      ></span>
                      <span class="text-xs text-gray-500">
                        {{ formatTime(notification.created_at) }}
                      </span>
                    </div>
                  </div>
                  
                  <p class="text-sm text-gray-600 mt-1">
                    {{ notification.data.message }}
                  </p>

                  <!-- Event Details -->
                  <div v-if="notification.data.event" class="mt-2 p-2 bg-gray-50 rounded-lg">
                    <p class="text-xs font-medium text-gray-700">
                      {{ notification.data.event.title }}
                    </p>
                    <p class="text-xs text-gray-500">
                      {{ formatEventTime(notification.data.event) }}
                    </p>
                  </div>

                  <!-- Action Buttons -->
                  <div v-if="notification.data.actions" class="mt-3 flex space-x-2">
                    <button 
                      v-for="action in notification.data.actions.slice(0, 2)" 
                      :key="action.type"
                      @click.stop="handleAction(action, notification)"
                      class="text-xs px-3 py-1 rounded-full font-medium transition-colors"
                      :class="action.primary 
                        ? 'bg-indigo-600 text-white hover:bg-indigo-700' 
                        : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
                    >
                      {{ action.label }}
                    </button>
                  </div>
                </div>
              </div>
                </div>
              </div>
            </TransitionGroup>
          </div>

          <!-- Enhanced Footer -->
          <div class="px-6 py-4 border-t border-gray-200/50 bg-gradient-to-r from-gray-50/80 to-indigo-50/30 backdrop-blur-sm">
            <button 
              @click="viewAllNotifications"
              class="w-full text-center text-sm text-indigo-600 hover:text-indigo-800 font-semibold transition-all duration-200 hover:bg-indigo-50 py-2 rounded-lg"
            >
              View All Notifications
            </button>
          </div>
        </div>
      </Transition>
    </div>

    <!-- Enhanced Real-time Notification Toast Stack -->
    <div class="fixed top-4 right-4 z-50 space-y-3 max-w-sm w-full">
      <TransitionGroup
        name="toast-stack"
        tag="div"
        enter-active-class="transition-all duration-500 ease-out"
        enter-from-class="opacity-0 transform translate-x-full scale-95"
        enter-to-class="opacity-100 transform translate-x-0 scale-100"
        leave-active-class="transition-all duration-300 ease-in"
        leave-from-class="opacity-100 transform translate-x-0 scale-100"
        leave-to-class="opacity-0 transform translate-x-full scale-95"
        move-class="transition-transform duration-300 ease-in-out"
      >
        <div 
          v-for="(toast, index) in toastStack" 
          :key="toast.id"
          class="bg-white/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-gray-200/50 transform transition-all duration-300 ease-in-out hover:scale-105"
          :class="[
            toastClasses(toast),
            { 'opacity-90 scale-95': index > 0 }
          ]"
          :style="{ transform: `translateY(${index * 4}px) scale(${1 - index * 0.02})` }"
          @click="bringToastToFront(toast)"
        >
          <!-- Toast Progress Bar -->
          <div 
            v-if="toast.autoClose && toast.progress !== undefined"
            class="absolute top-0 left-0 h-1 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-t-2xl transition-all duration-100 ease-linear"
            :style="{ width: `${toast.progress}%` }"
          ></div>

          <div class="p-4">
            <div class="flex items-start space-x-3">
              <!-- Enhanced Toast Icon -->
              <div 
                class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center text-lg shadow-sm"
                :class="getNotificationIconClass(toast)"
              >
                {{ toast.icon || '🔔' }}
              </div>
              
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900">
                  {{ toast.title }}
                </p>
                <p class="text-sm text-gray-600 mt-1 leading-relaxed">
                  {{ toast.message }}
                </p>
                
                <!-- Enhanced Toast Actions -->
                <div v-if="toast.actions" class="mt-3 flex flex-wrap gap-2">
                  <button 
                    v-for="action in toast.actions.slice(0, 2)" 
                    :key="action.type"
                    @click.stop="handleToastAction(action, toast)"
                    class="text-xs px-3 py-1.5 rounded-lg font-medium transition-all duration-200 hover:scale-105 active:scale-95"
                    :class="action.primary 
                      ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white hover:from-indigo-700 hover:to-purple-700 shadow-sm' 
                      : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                  >
                    {{ action.label }}
                  </button>
                </div>

                <!-- Toast timestamp -->
                <p class="text-xs text-gray-400 mt-2">
                  {{ formatTime(toast.timestamp || new Date()) }}
                </p>
              </div>

              <!-- Enhanced Dismiss Button -->
              <button 
                @click.stop="dismissToast(toast.id)"
                class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition-all duration-200 hover:bg-gray-100 p-1.5 rounded-lg"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>
          </div>
        </div>
      </TransitionGroup>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { usePage } from '@inertiajs/vue3'

// Reactive data
const showNotifications = ref(false)
const notifications = ref([])
const unreadCount = ref(0)
const toastStack = ref([])
const recentNotification = ref(false)
const isMobile = ref(false)

// Touch handling for swipe-to-dismiss
const touchStartX = ref(0)
const touchStartY = ref(0)
const touchCurrentX = ref(0)
const touchCurrentY = ref(0)
const isSwiping = ref(false)

// Computed properties
const hasUnreadNotifications = computed(() => unreadCount.value > 0)

const toastClasses = (toast) => {
  if (!toast) return ''
  
  const type = toast.notification_type || toast.data?.notification_type || 'info'
  const classes = {
    'urgent': 'border-l-4 border-red-500 bg-red-50/50',
    'warning': 'border-l-4 border-yellow-500 bg-yellow-50/50',
    'success': 'border-l-4 border-green-500 bg-green-50/50',
    'info': 'border-l-4 border-blue-500 bg-blue-50/50'
  }
  
  return classes[type] || classes.info
}

// Methods
const toggleNotifications = () => {
  showNotifications.value = !showNotifications.value
  if (showNotifications.value) {
    fetchNotifications()
  }
}

const closeNotifications = () => {
  showNotifications.value = false
}

const fetchNotifications = async () => {
  try {
    const response = await fetch('/api/notifications')
    if (response.ok) {
      const data = await response.json()
      notifications.value = data.notifications || []
      unreadCount.value = data.unread_count || 0
    }
  } catch (error) {
    console.error('Error fetching notifications:', error)
  }
}

const markAllAsRead = async () => {
  try {
    const response = await fetch('/api/notifications/mark-all-read', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      }
    })
    
    if (response.ok) {
      notifications.value.forEach(notification => {
        notification.read_at = new Date().toISOString()
      })
      unreadCount.value = 0
    }
  } catch (error) {
    console.error('Error marking notifications as read:', error)
  }
}

const handleNotificationClick = async (notification) => {
  // Mark as read if unread
  if (!notification.read_at) {
    await markAsRead(notification.id)
    notification.read_at = new Date().toISOString()
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  }
  
  // Handle notification action (e.g., navigate to event)
  if (notification.data.event) {
    // Navigate to event details or calendar
    window.location.href = `/events/${notification.data.event.id}`
  }
}

const markAsRead = async (notificationId) => {
  try {
    await fetch(`/api/notifications/${notificationId}/read`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      }
    })
  } catch (error) {
    console.error('Error marking notification as read:', error)
  }
}

const handleAction = (action, notification) => {
  switch (action.type) {
    case 'join':
      if (action.url) {
        window.open(action.url, '_blank')
      }
      break
    case 'view':
      if (action.url) {
        window.location.href = action.url
      } else if (notification.event) {
        window.location.href = `/events/${notification.event.id}`
      }
      break
    case 'chat':
      // Start chat with user
      if (notification.user) {
        // Emit event to open chat (if chat manager is available)
        window.dispatchEvent(new CustomEvent('start-chat', { 
          detail: { user: notification.user } 
        }))
      } else if (notification.conversation) {
        // Open existing conversation
        window.dispatchEvent(new CustomEvent('open-chat', { 
          detail: { conversation: notification.conversation } 
        }))
      }
      dismissNotification(notification)
      break
    case 'dismiss':
      dismissNotification(notification)
      break
    case 'snooze':
      snoozeNotification(notification, action.duration || 5)
      break
  }
}

const handleToastAction = (action, toast) => {
  handleAction(action, toast)
  dismissToast()
}

const dismissNotification = async (notification) => {
  try {
    await fetch(`/api/notifications/${notification.id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      }
    })
    
    // Remove from local list
    const index = notifications.value.findIndex(n => n.id === notification.id)
    if (index > -1) {
      notifications.value.splice(index, 1)
      if (!notification.read_at) {
        unreadCount.value = Math.max(0, unreadCount.value - 1)
      }
    }
  } catch (error) {
    console.error('Error dismissing notification:', error)
  }
}

const snoozeNotification = async (notification, minutes) => {
  try {
    await fetch(`/api/notifications/${notification.id}/snooze`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify({ minutes })
    })
    
    // Remove from current list (will reappear after snooze period)
    const index = notifications.value.findIndex(n => n.id === notification.id)
    if (index > -1) {
      notifications.value.splice(index, 1)
      if (!notification.read_at) {
        unreadCount.value = Math.max(0, unreadCount.value - 1)
      }
    }
  } catch (error) {
    console.error('Error snoozing notification:', error)
  }
}

// Enhanced toast stack management with performance optimization
const showRealTimeNotification = (notificationData) => {
  const startTime = performance.now()
  const toastId = notificationData.id || Date.now()
  const toast = {
    id: toastId,
    ...notificationData,
    timestamp: new Date(),
    autoClose: notificationData.notification_type !== 'urgent',
    progress: 100,
    delivery_time: startTime
  }
  
  // Add to toast stack (max 5 toasts)
  toastStack.value.unshift(toast)
  if (toastStack.value.length > 5) {
    toastStack.value.pop()
  }
  
  // Calculate and log delivery time
  const deliveryTime = performance.now() - startTime
  console.log(`Notification delivered in ${deliveryTime.toFixed(2)}ms`)
  
  // Trigger recent notification animation
  recentNotification.value = true
  setTimeout(() => {
    recentNotification.value = false
  }, 2000)
  
  // Auto-dismiss with progress bar for non-urgent notifications
  if (toast.autoClose) {
    const duration = notificationData.notification_type === 'success' ? 5000 : 8000
    const interval = 100 // Update every 100ms
    let elapsed = 0
    
    const progressInterval = setInterval(() => {
      elapsed += interval
      const progress = Math.max(0, 100 - (elapsed / duration) * 100)
      
      const toastIndex = toastStack.value.findIndex(t => t.id === toastId)
      if (toastIndex !== -1) {
        toastStack.value[toastIndex].progress = progress
      }
      
      if (elapsed >= duration) {
        clearInterval(progressInterval)
        dismissToast(toastId)
      }
    }, interval)
  }
  
  // Add to notifications list
  notifications.value.unshift({
    id: toastId,
    data: notificationData,
    created_at: new Date().toISOString(),
    read_at: null
  })
  
  unreadCount.value++
  
  // Play notification sound (optimized)
  playNotificationSound()
  
  // Show browser notification if supported and enabled
  showBrowserNotification(notificationData)
  
  // Trigger haptic feedback on mobile devices
  if ('vibrate' in navigator && notificationData.notification_type === 'urgent') {
    navigator.vibrate([100, 50, 100])
  }
}

const dismissToast = (toastId) => {
  const index = toastStack.value.findIndex(toast => toast.id === toastId)
  if (index !== -1) {
    toastStack.value.splice(index, 1)
  }
}

const bringToastToFront = (toast) => {
  const index = toastStack.value.findIndex(t => t.id === toast.id)
  if (index > 0) {
    toastStack.value.splice(index, 1)
    toastStack.value.unshift(toast)
  }
}

// Touch handling for swipe-to-dismiss
const handleTouchStart = (e) => {
  touchStartX.value = e.touches[0].clientX
  touchStartY.value = e.touches[0].clientY
  isSwiping.value = false
}

const handleTouchMove = (e) => {
  if (!touchStartX.value || !touchStartY.value) return
  
  touchCurrentX.value = e.touches[0].clientX
  touchCurrentY.value = e.touches[0].clientY
  
  const diffX = touchStartX.value - touchCurrentX.value
  const diffY = touchStartY.value - touchCurrentY.value
  
  // Detect horizontal swipe
  if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
    isSwiping.value = true
    e.preventDefault()
  }
}

const handleTouchEnd = (e) => {
  if (!isSwiping.value) return
  
  const diffX = touchStartX.value - touchCurrentX.value
  
  // Swipe left to dismiss (threshold: 100px)
  if (diffX > 100) {
    const notificationElement = e.currentTarget
    const notificationId = notificationElement.dataset.notificationId
    if (notificationId) {
      dismissNotification({ id: notificationId })
    }
  }
  
  // Reset touch values
  touchStartX.value = 0
  touchStartY.value = 0
  touchCurrentX.value = 0
  touchCurrentY.value = 0
  isSwiping.value = false
}

// Mobile detection
const checkMobile = () => {
  isMobile.value = window.innerWidth < 768
}

// Optimized notification sound with caching
let audioContext = null
let soundCache = new Map()

const playNotificationSound = () => {
  try {
    // Initialize audio context only once
    if (!audioContext) {
      audioContext = new (window.AudioContext || window.webkitAudioContext)()
    }
    
    // Resume audio context if suspended (required by some browsers)
    if (audioContext.state === 'suspended') {
      audioContext.resume()
    }
    
    const soundKey = 'notification'
    
    // Use cached sound if available
    if (soundCache.has(soundKey)) {
      const cachedBuffer = soundCache.get(soundKey)
      const source = audioContext.createBufferSource()
      const gainNode = audioContext.createGain()
      
      source.buffer = cachedBuffer
      source.connect(gainNode)
      gainNode.connect(audioContext.destination)
      
      gainNode.gain.setValueAtTime(0.1, audioContext.currentTime)
      source.start(audioContext.currentTime)
      return
    }
    
    // Create new sound
    const oscillator = audioContext.createOscillator()
    const gainNode = audioContext.createGain()
    
    oscillator.connect(gainNode)
    gainNode.connect(audioContext.destination)
    
    oscillator.frequency.setValueAtTime(800, audioContext.currentTime)
    oscillator.frequency.setValueAtTime(600, audioContext.currentTime + 0.1)
    
    gainNode.gain.setValueAtTime(0.1, audioContext.currentTime)
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2)
    
    oscillator.start(audioContext.currentTime)
    oscillator.stop(audioContext.currentTime + 0.2)
  } catch (error) {
    console.warn('Could not play notification sound:', error)
  }
}

const showBrowserNotification = (notificationData) => {
  if ('Notification' in window && Notification.permission === 'granted') {
    const notification = new Notification(notificationData.title, {
      body: notificationData.message,
      icon: '/favicon.ico',
      badge: '/favicon.ico',
      tag: notificationData.id,
      requireInteraction: notificationData.notification_type === 'urgent'
    })
    
    notification.onclick = () => {
      window.focus()
      if (notificationData.event) {
        window.location.href = `/events/${notificationData.event.id}`
      }
      notification.close()
    }
    
    // Auto-close after 5 seconds for non-urgent notifications
    if (notificationData.notification_type !== 'urgent') {
      setTimeout(() => notification.close(), 5000)
    }
  }
}

const requestNotificationPermission = async () => {
  if ('Notification' in window && Notification.permission === 'default') {
    await Notification.requestPermission()
  }
}

const viewAllNotifications = () => {
  closeNotifications()
  // Navigate to full notifications page
  window.location.href = '/notifications'
}

const getNotificationIconClass = (notification) => {
  const type = notification.data?.notification_type || notification.notification_type || 'info'
  const classes = {
    'urgent': 'bg-red-100 text-red-600',
    'warning': 'bg-yellow-100 text-yellow-600',
    'success': 'bg-green-100 text-green-600',
    'info': 'bg-blue-100 text-blue-600'
  }
  
  return classes[type] || classes.info
}

const formatTime = (timestamp) => {
  const date = new Date(timestamp)
  const now = new Date()
  const diffInMinutes = Math.floor((now - date) / (1000 * 60))
  
  if (diffInMinutes < 1) return 'Just now'
  if (diffInMinutes < 60) return `${diffInMinutes}m ago`
  if (diffInMinutes < 1440) return `${Math.floor(diffInMinutes / 60)}h ago`
  return `${Math.floor(diffInMinutes / 1440)}d ago`
}

const formatEventTime = (event) => {
  const startDate = new Date(event.start_at)
  return startDate.toLocaleString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit'
  })
}

// Enhanced real-time listeners with optimized channels
const setupRealTimeListeners = () => {
  if (window.Echo) {
    const user = usePage().props.auth?.user
    if (user) {
      // Listen on private user channel for all notification types
      window.Echo.private(`user.${user.id}`)
        .listen('.reminder.triggered', (event) => {
          console.log('Real-time reminder received:', event)
          showRealTimeNotification({
            id: event.reminder?.id || Date.now(),
            title: event.title || 'Event Reminder',
            message: event.message || 'You have an upcoming event',
            notification_type: event.notification_type || 'info',
            icon: '⏰',
            event: event.event,
            actions: event.actions || [
              { type: 'view', label: 'View Event', url: event.event ? `/events/${event.event.id}` : '#', primary: true },
              { type: 'dismiss', label: 'Dismiss' }
            ]
          })
        })
        .listen('.message.sent', (event) => {
          // Handle chat message notifications
          if (event.message && event.conversation) {
            const message = event.message
            const conversation = event.conversation
            
            // Only show notification if the conversation is not currently open
            const isConversationOpen = document.querySelector(`[data-conversation-id="${conversation.id}"]`)
            
            if (!isConversationOpen && message.sender.id !== user.id) {
              showRealTimeNotification({
                id: `chat-${message.id}`,
                title: `New message from ${message.sender.name}`,
                message: message.content,
                notification_type: 'info',
                icon: '💬',
                conversation: conversation,
                actions: [
                  { type: 'view', label: 'Open Chat', primary: true },
                  { type: 'dismiss', label: 'Dismiss' }
                ]
              })
            }
          }
        })
        .listen('.user.status.updated', (event) => {
          // Handle user status change notifications (optional)
          if (event.user && event.user.id !== user.id && event.user.is_online) {
            showRealTimeNotification({
              id: `status-${event.user.id}-${Date.now()}`,
              title: `${event.user.name} is now online`,
              message: event.user.status_message || 'Available for chat',
              notification_type: 'success',
              icon: '🟢',
              user: event.user,
              actions: [
                { type: 'chat', label: 'Start Chat', primary: true },
                { type: 'dismiss', label: 'Dismiss' }
              ]
            })
          }
        })

      // Listen for typing indicators (for demonstration)
      window.Echo.private(`user.${user.id}`)
        .listen('.typing.indicator', (event) => {
          // Handle typing notifications if needed
          if (event.user && event.is_typing && event.conversation) {
            // Could show subtle typing notifications
            console.log(`${event.user.name} is typing in conversation ${event.conversation.id}`)
          }
        })
    }
  }
}

// Lifecycle hooks
onMounted(() => {
  fetchNotifications()
  requestNotificationPermission()
  setupRealTimeListeners()
  checkMobile()
  
  // Close notifications when clicking outside
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.notification-center')) {
      showNotifications.value = false
    }
  })
  
  // Listen for window resize to update mobile detection
  window.addEventListener('resize', checkMobile)
  
  // Listen for custom events from chat manager
  window.addEventListener('start-chat', (event) => {
    // Handle start chat event
    console.log('Start chat requested:', event.detail)
  })
  
  window.addEventListener('open-chat', (event) => {
    // Handle open chat event
    console.log('Open chat requested:', event.detail)
  })
  
  // Listen for page visibility changes to manage notifications
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
      // User returned to tab, mark recent notifications as seen
      setTimeout(() => {
        if (toastStack.value.length > 0) {
          toastStack.value.forEach(toast => {
            if (!toast.seen) {
              toast.seen = true
            }
          })
        }
      }, 1000)
    }
  })
})

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile)
})

// Expose methods for external use
defineExpose({
  showRealTimeNotification,
  fetchNotifications
})
</script>

<style scoped>
/* Enhanced notification item animations */
.notification-item {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
}

.notification-item:hover {
  transform: translateX(4px) scale(1.01);
  box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.notification-item::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(90deg, transparent, rgba(99, 102, 241, 0.05), transparent);
  transform: translateX(-100%);
  transition: transform 0.6s ease-in-out;
}

.notification-item:hover::before {
  transform: translateX(100%);
}

/* Enhanced bell button */
.notification-bell {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;
}

.notification-bell:hover {
  transform: scale(1.05) rotate(-5deg);
  box-shadow: 0 8px 25px -5px rgba(99, 102, 241, 0.3);
}

.notification-bell:active {
  transform: scale(0.95);
}

/* Custom scrollbar */
.custom-scrollbar {
  scrollbar-width: thin;
  scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
}

.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(156, 163, 175, 0.5);
  border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(156, 163, 175, 0.7);
}

/* Toast stack animations */
.toast-stack-enter-active,
.toast-stack-leave-active {
  transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.toast-stack-enter-from {
  opacity: 0;
  transform: translateX(100%) scale(0.9);
}

.toast-stack-leave-to {
  opacity: 0;
  transform: translateX(100%) scale(0.9);
}

.toast-stack-move {
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Notification list animations */
.notification-list-enter-active,
.notification-list-leave-active {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.notification-list-enter-from {
  opacity: 0;
  transform: translateX(20px) scale(0.95);
}

.notification-list-leave-to {
  opacity: 0;
  transform: translateX(-20px) scale(0.95);
}

.notification-list-move {
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Pulse animation for urgent notifications */
@keyframes urgentPulse {
  0%, 100% {
    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
  }
  50% {
    box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
  }
}

.urgent-notification {
  animation: urgentPulse 2s infinite;
}

/* Shimmer effect for loading states */
@keyframes shimmer {
  0% {
    background-position: -200px 0;
  }
  100% {
    background-position: calc(200px + 100%) 0;
  }
}

.shimmer {
  background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
  background-size: 200px 100%;
  animation: shimmer 1.5s infinite;
}

/* Mobile optimizations */
@media (max-width: 768px) {
  .notification-center {
    position: relative;
  }
  
  .notification-item:hover {
    transform: none;
  }
  
  .notification-item:active {
    transform: scale(0.98);
    background-color: rgba(99, 102, 241, 0.05);
  }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
  .custom-scrollbar {
    scrollbar-color: rgba(75, 85, 99, 0.5) transparent;
  }
  
  .custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(75, 85, 99, 0.5);
  }
  
  .custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(75, 85, 99, 0.7);
  }
}

/* Accessibility improvements */
@media (prefers-reduced-motion: reduce) {
  .notification-item,
  .notification-bell,
  .toast-stack-enter-active,
  .toast-stack-leave-active,
  .notification-list-enter-active,
  .notification-list-leave-active {
    transition: none;
  }
  
  .notification-item::before {
    display: none;
  }
  
  .urgent-notification {
    animation: none;
  }
}

/* High contrast mode support */
@media (prefers-contrast: high) {
  .notification-item {
    border: 2px solid;
  }
  
  .notification-bell {
    border: 2px solid;
  }
}
</style>

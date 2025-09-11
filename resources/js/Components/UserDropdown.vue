<template>
  <div class="relative" ref="dropdownRef">
    <!-- User Avatar Button -->
    <button
      @click="toggleDropdown"
      class="flex items-center space-x-3 p-2 rounded-xl hover:bg-white/10 transition-all duration-200 group"
      :class="{ 'bg-white/10': isOpen }"
    >
      <!-- Avatar with online indicator -->
      <div class="relative">
        <div class="w-10 h-10 rounded-full overflow-hidden shadow-lg group-hover:shadow-xl transition-all duration-200 group-hover:scale-105">
          <img v-if="userAvatar" 
               :src="userAvatar" 
               :alt="user.name"
               class="w-full h-full object-cover"
          />
          <div v-else class="w-full h-full bg-gradient-to-r from-purple-500 to-pink-500 flex items-center justify-center">
            <span class="text-white text-sm font-bold">{{ userInitials }}</span>
          </div>
        </div>
        <!-- Online indicator -->
        <div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-green-400 rounded-full border-2 border-white animate-pulse"></div>
        <!-- Google Badge -->
        <div v-if="isGoogleUser && userAvatar" class="absolute -top-1 -right-1 w-4 h-4 bg-white rounded-full flex items-center justify-center shadow-sm">
          <svg class="w-3 h-3" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
          </svg>
        </div>
      </div>
      
      <!-- User info (hidden on mobile) -->
      <div class="hidden md:block text-left">
        <p class="text-sm font-medium text-gray-800">{{ user.name }}</p>
        <p class="text-xs text-gray-600">{{ user.email }}</p>
      </div>
      
      <!-- Dropdown arrow -->
      <svg 
        class="w-4 h-4 text-gray-600 transition-transform duration-200"
        :class="{ 'rotate-180': isOpen }"
        fill="none" 
        stroke="currentColor" 
        viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
      </svg>
    </button>

    <!-- Dropdown Menu -->
    <transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0 scale-95 translate-y-1"
      enter-to-class="opacity-100 scale-100 translate-y-0"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100 scale-100 translate-y-0"
      leave-to-class="opacity-0 scale-95 translate-y-1"
    >
      <div
        v-if="isOpen"
        class="absolute right-0 top-full mt-2 w-80 bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden z-50"
      >
        <!-- User Info Header -->
        <div class="bg-gradient-to-r from-indigo-500 to-purple-600 p-4 text-white">
          <div class="flex items-center space-x-3">
            <div class="relative">
              <div class="w-12 h-12 rounded-full overflow-hidden">
                <img v-if="userAvatar" 
                     :src="userAvatar" 
                     :alt="user.name"
                     class="w-full h-full object-cover"
                />
                <div v-else class="w-full h-full bg-white/20 flex items-center justify-center">
                  <span class="text-lg font-bold">{{ userInitials }}</span>
                </div>
              </div>
              <!-- Google Badge for dropdown header -->
              <div v-if="isGoogleUser && userAvatar" class="absolute -top-1 -right-1 w-5 h-5 bg-white rounded-full flex items-center justify-center shadow-sm">
                <svg class="w-3 h-3" viewBox="0 0 24 24">
                  <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                  <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                  <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                  <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
              </div>
            </div>
            <div class="flex-1">
              <h3 class="font-semibold text-lg">{{ user.name }}</h3>
              <p class="text-indigo-100 text-sm">{{ user.email }}</p>
              <div class="flex items-center space-x-2 mt-1">
                <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                <span class="text-xs text-indigo-100">Online</span>
                <span v-if="isGoogleUser" class="text-xs text-indigo-200">• Google Account</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Menu Items -->
        <div class="py-2">
          <!-- Profile Section -->
          <div class="px-4 py-2">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Account</p>
          </div>
          
          <a
            href="/profile"
            class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-all duration-200 group"
          >
            <div class="w-8 h-8 bg-blue-100 group-hover:bg-blue-200 rounded-lg flex items-center justify-center transition-colors">
              <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
              </svg>
            </div>
            <div class="flex-1">
              <p class="font-medium">Profile Settings</p>
              <p class="text-xs text-gray-500">Manage your account</p>
            </div>
            <svg class="w-4 h-4 text-gray-400 group-hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
          </a>

          <button
            @click="openPreferences"
            class="w-full flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-green-50 hover:text-green-700 transition-all duration-200 group"
          >
            <div class="w-8 h-8 bg-green-100 group-hover:bg-green-200 rounded-lg flex items-center justify-center transition-colors">
              <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
              </svg>
            </div>
            <div class="flex-1 text-left">
              <p class="font-medium">Preferences</p>
              <p class="text-xs text-gray-500">Customize your experience</p>
            </div>
            <svg class="w-4 h-4 text-gray-400 group-hover:text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
          </button>

          <!-- Divider -->
          <div class="border-t border-gray-100 my-2"></div>

          <!-- Quick Actions -->
          <div class="px-4 py-2">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Quick Actions</p>
          </div>

          <button
            @click="openHelp"
            class="w-full flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-purple-50 hover:text-purple-700 transition-all duration-200 group"
          >
            <div class="w-8 h-8 bg-purple-100 group-hover:bg-purple-200 rounded-lg flex items-center justify-center transition-colors">
              <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
            <div class="flex-1 text-left">
              <p class="font-medium">Help & Support</p>
              <p class="text-xs text-gray-500">Get assistance</p>
            </div>
          </button>

          <button
            @click="openKeyboardShortcuts"
            class="w-full flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-yellow-50 hover:text-yellow-700 transition-all duration-200 group"
          >
            <div class="w-8 h-8 bg-yellow-100 group-hover:bg-yellow-200 rounded-lg flex items-center justify-center transition-colors">
              <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
              </svg>
            </div>
            <div class="flex-1 text-left">
              <p class="font-medium">Keyboard Shortcuts</p>
              <p class="text-xs text-gray-500">View shortcuts</p>
            </div>
            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded font-mono">⌘K</span>
          </button>

          <!-- Divider -->
          <div class="border-t border-gray-100 my-2"></div>

          <!-- Logout -->
          <button
            @click="logout"
            class="w-full flex items-center space-x-3 px-4 py-3 text-red-600 hover:bg-red-50 transition-all duration-200 group"
          >
            <div class="w-8 h-8 bg-red-100 group-hover:bg-red-200 rounded-lg flex items-center justify-center transition-colors">
              <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
              </svg>
            </div>
            <div class="flex-1 text-left">
              <p class="font-medium">Sign Out</p>
              <p class="text-xs text-red-400">Logout from your account</p>
            </div>
          </button>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 px-4 py-3 border-t border-gray-100">
          <div class="flex items-center justify-between text-xs text-gray-500">
            <span>CalendarPro v2.0</span>
            <div class="flex items-center space-x-2">
              <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
              <span>All systems operational</span>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  user: {
    type: Object,
    required: true
  }
})

const isOpen = ref(false)
const dropdownRef = ref(null)

const userInitials = computed(() => {
  return props.user.name
    .split(' ')
    .map(name => name.charAt(0))
    .join('')
    .toUpperCase()
    .slice(0, 2)
})

const userAvatar = computed(() => {
  if (props.user.avatar) {
    // If it's a Google avatar (starts with http), use it directly
    if (props.user.avatar.startsWith('http')) {
      return props.user.avatar
    }
    // Otherwise, it's a local file in storage
    return `/storage/${props.user.avatar}`
  }
  return null
})

const isGoogleUser = computed(() => {
  return props.user.google_id !== null
})

const toggleDropdown = () => {
  isOpen.value = !isOpen.value
}

const closeDropdown = () => {
  isOpen.value = false
}

const handleClickOutside = (event) => {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
    closeDropdown()
  }
}

const logout = () => {
  closeDropdown()
  
  // Show confirmation toast
  if (window.toast) {
    window.toast.info('Signing out...', 'See you soon!')
  }
  
  // Redirect to logout
  router.post('/logout')
}

const openPreferences = () => {
  closeDropdown()
  // Navigate to profile page with preferences tab active
  router.visit('/profile', {
    data: { tab: 'preferences' },
    preserveState: false
  })
}

const openHelp = () => {
  closeDropdown()
  if (window.toast) {
    window.toast.info('Help Center', 'Opening help documentation...')
  }
}

const openKeyboardShortcuts = () => {
  closeDropdown()
  if (window.toast) {
    window.toast.info('Keyboard Shortcuts', 'Press ⌘K to open quick actions.')
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>
/* Custom animations */
@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.dropdown-enter-active {
  animation: slideDown 0.2s ease-out;
}
</style>

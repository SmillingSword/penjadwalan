<template>
  <teleport to="body">
    <div class="fixed top-4 right-4 z-50 space-y-2 max-w-md">
      <transition-group
        enter-active-class="transform ease-out duration-300 transition"
        enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
        enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
        leave-active-class="transition ease-in duration-100"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-for="toast in toasts"
          :key="toast.id"
          :class="[
            'w-full min-w-80 bg-white shadow-lg rounded-2xl pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden',
            'transform transition-all duration-300 hover:scale-105'
          ]"
        >
          <div class="p-4">
            <div class="flex items-start">
              <div class="flex-shrink-0">
                <!-- Success Icon -->
                <div
                  v-if="toast.type === 'success'"
                  class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center"
                >
                  <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                  </svg>
                </div>
                
                <!-- Error Icon -->
                <div
                  v-else-if="toast.type === 'error'"
                  class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center"
                >
                  <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                  </svg>
                </div>
                
                <!-- Warning Icon -->
                <div
                  v-else-if="toast.type === 'warning'"
                  class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center"
                >
                  <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                  </svg>
                </div>
                
                <!-- Info Icon -->
                <div
                  v-else
                  class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center"
                >
                  <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
              </div>
              
              <div class="ml-3 flex-1 pt-0.5 min-w-0">
                <p class="text-sm font-medium text-gray-900 break-words">
                  {{ toast.title }}
                </p>
                <p v-if="toast.message" class="mt-1 text-sm text-gray-500 break-words">
                  {{ toast.message }}
                </p>
              </div>
              
              <div class="ml-4 flex-shrink-0 flex">
                <button
                  @click="removeToast(toast.id)"
                  class="bg-white rounded-md inline-flex text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  <span class="sr-only">Close</span>
                  <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                  </svg>
                </button>
              </div>
            </div>
          </div>
          
          <!-- Progress bar -->
          <div
            v-if="toast.duration"
            class="h-1 bg-gray-200"
          >
            <div
              :class="[
                'h-full transition-all ease-linear',
                {
                  'bg-green-500': toast.type === 'success',
                  'bg-red-500': toast.type === 'error',
                  'bg-yellow-500': toast.type === 'warning',
                  'bg-blue-500': toast.type === 'info'
                }
              ]"
              :style="{ width: `${toast.progress}%` }"
            ></div>
          </div>
        </div>
      </transition-group>
    </div>
  </teleport>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const toasts = ref([])
let toastId = 0

const addToast = (toast) => {
  const id = ++toastId
  const newToast = {
    id,
    type: toast.type || 'info',
    title: toast.title,
    message: toast.message,
    duration: toast.duration || 5000,
    progress: 100
  }
  
  toasts.value.push(newToast)
  
  if (newToast.duration > 0) {
    const startTime = Date.now()
    const interval = setInterval(() => {
      const elapsed = Date.now() - startTime
      const progress = Math.max(0, 100 - (elapsed / newToast.duration) * 100)
      
      newToast.progress = progress
      
      if (progress <= 0) {
        clearInterval(interval)
        removeToast(id)
      }
    }, 50)
  }
  
  return id
}

const removeToast = (id) => {
  const index = toasts.value.findIndex(toast => toast.id === id)
  if (index > -1) {
    toasts.value.splice(index, 1)
  }
}

// Global toast methods
window.toast = {
  success: (title, message, duration) => addToast({ type: 'success', title, message, duration }),
  error: (title, message, duration) => addToast({ type: 'error', title, message, duration }),
  warning: (title, message, duration) => addToast({ type: 'warning', title, message, duration }),
  info: (title, message, duration) => addToast({ type: 'info', title, message, duration })
}

defineExpose({
  addToast,
  removeToast
})
</script>

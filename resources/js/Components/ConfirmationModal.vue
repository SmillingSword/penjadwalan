<template>
  <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
    <!-- Backdrop with blur effect -->
    <div 
      class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-all duration-300"
      :class="show ? 'opacity-100' : 'opacity-0'"
      @click="cancel"
    ></div>
    
    <!-- Modal Container -->
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
      <div 
        class="inline-block w-full max-w-md p-0 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl"
        :class="show ? 'animate-modal-enter' : 'animate-modal-exit'"
      >
        <!-- Header with Icon -->
        <div class="bg-gradient-to-r from-red-500 to-pink-500 px-6 py-4 relative overflow-hidden">
          <!-- Animated background elements -->
          <div class="absolute inset-0 opacity-20">
            <div class="absolute top-2 right-4 w-8 h-8 bg-white rounded-full animate-pulse"></div>
            <div class="absolute bottom-2 left-6 w-6 h-6 bg-white rounded-full animate-bounce"></div>
          </div>
          
          <div class="relative z-10 flex items-center space-x-3">
            <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center animate-pulse">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
              </svg>
            </div>
            <div>
              <h3 class="text-lg font-bold text-white">{{ title }}</h3>
              <p class="text-red-100 text-sm">This action cannot be undone</p>
            </div>
          </div>
        </div>

        <!-- Content -->
        <div class="px-6 py-6">
          <div class="mb-6">
            <p class="text-gray-700 text-base leading-relaxed">
              {{ message }}
            </p>
            
            <!-- Additional details if provided -->
            <div v-if="details" class="mt-4 p-4 bg-gray-50 rounded-xl border-l-4 border-orange-400">
              <div class="flex items-start space-x-3">
                <svg class="w-5 h-5 text-orange-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
                <div>
                  <p class="text-sm font-medium text-orange-800">Important Details</p>
                  <p class="text-sm text-orange-700 mt-1">{{ details }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex justify-end space-x-3">
            <button 
              @click="cancel"
              class="px-6 py-3 text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 transition-all duration-200 font-medium hover:scale-105 focus:outline-none focus:ring-2 focus:ring-gray-300"
            >
              {{ cancelText }}
            </button>
            <button 
              @click="confirm"
              :class="[
                'px-6 py-3 rounded-xl font-medium transition-all duration-200 hover:scale-105 focus:outline-none focus:ring-2 shadow-lg hover:shadow-xl',
                type === 'danger' 
                  ? 'bg-gradient-to-r from-red-600 to-pink-600 text-white hover:from-red-700 hover:to-pink-700 focus:ring-red-300' 
                  : 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:from-blue-700 hover:to-indigo-700 focus:ring-blue-300'
              ]"
            >
              <span class="flex items-center space-x-2">
                <svg v-if="type === 'danger'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>{{ confirmText }}</span>
              </span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue'

const emit = defineEmits(['confirm', 'cancel'])

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    default: 'Confirm Action'
  },
  message: {
    type: String,
    default: 'Are you sure you want to proceed?'
  },
  details: {
    type: String,
    default: null
  },
  confirmText: {
    type: String,
    default: 'Confirm'
  },
  cancelText: {
    type: String,
    default: 'Cancel'
  },
  type: {
    type: String,
    default: 'danger', // 'danger' or 'info'
    validator: (value) => ['danger', 'info'].includes(value)
  }
})

const confirm = () => {
  emit('confirm')
}

const cancel = () => {
  emit('cancel')
}

const handleEscape = (e) => {
  if (e.key === 'Escape' && props.show) {
    cancel()
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleEscape)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleEscape)
})
</script>

<style scoped>
@keyframes modal-enter {
  from {
    opacity: 0;
    transform: scale(0.95) translateY(-20px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

@keyframes modal-exit {
  from {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
  to {
    opacity: 0;
    transform: scale(0.95) translateY(-20px);
  }
}

.animate-modal-enter {
  animation: modal-enter 0.3s ease-out;
}

.animate-modal-exit {
  animation: modal-exit 0.2s ease-in;
}

/* Prevent body scroll when modal is open */
.modal-open {
  overflow: hidden;
}
</style>

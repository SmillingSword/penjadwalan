<template>
  <Head title="Dashboard - CalendarPro" />

  <AuthenticatedLayout>
    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Welcome Section with Animation -->
      <div class="mb-8 animate-fade-in-up">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl p-8 text-white relative overflow-hidden">
          <!-- Background Pattern -->
          <div class="absolute inset-0 opacity-10">
            <div class="absolute -top-4 -right-4 w-24 h-24 bg-white rounded-full animate-pulse"></div>
            <div class="absolute -bottom-8 -left-8 w-32 h-32 bg-white rounded-full animate-bounce"></div>
          </div>
          
          <div class="relative z-10">
            <h2 class="text-3xl font-bold mb-2">
              Welcome back, {{ user.name }}! 👋
            </h2>
            <p class="text-blue-100 mb-6">
              Ready to manage your schedule like a pro? Let's make today productive!
            </p>
            
            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4">
                <div class="flex items-center space-x-3">
                  <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                      <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                  </div>
                  <div>
                    <p class="text-2xl font-bold">{{ stats.todayEvents }}</p>
                    <p class="text-blue-100 text-sm">Today's Events</p>
                  </div>
                </div>
              </div>
              
              <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4">
                <div class="flex items-center space-x-3">
                  <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                      <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"></path>
                    </svg>
                  </div>
                  <div>
                    <p class="text-2xl font-bold">{{ stats.weekEvents }}</p>
                    <p class="text-blue-100 text-sm">This Week</p>
                  </div>
                </div>
              </div>
              
              <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4">
                <div class="flex items-center space-x-3">
                  <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                    </svg>
                  </div>
                  <div>
                    <p class="text-2xl font-bold">{{ stats.productivity }}%</p>
                    <p class="text-blue-100 text-sm">Productivity</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Dashboard Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Calendar Section -->
        <div class="lg:col-span-3">
          <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
            <!-- Calendar Header -->
            <div class="bg-gradient-to-r from-gray-50 to-blue-50 px-6 py-4 border-b border-gray-100">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="text-xl font-bold text-gray-900">Calendar</h3>
                  <p class="text-gray-600 text-sm">Manage your schedule efficiently</p>
                </div>
                
                <!-- Calendar Controls -->
                <div class="flex items-center space-x-3">
                  <div class="flex bg-white rounded-lg p-1 shadow-sm">
                    <button 
                      v-for="view in ['month', 'week', 'day']" 
                      :key="view"
                      @click="currentView = view"
                      :class="[
                        'px-3 py-1 text-sm font-medium rounded-md transition-all duration-200',
                        currentView === view 
                          ? 'bg-blue-600 text-white shadow-sm' 
                          : 'text-gray-600 hover:text-blue-600 hover:bg-blue-50'
                      ]"
                    >
                      {{ view.charAt(0).toUpperCase() + view.slice(1) }}
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Calendar Component -->
            <div class="p-6">
              <Calendar />
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Quick Actions -->
          <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6">
            <h4 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h4>
            <div class="space-y-3">
              <button 
                @click="showEventModal = true"
                class="w-full flex items-center space-x-3 p-3 text-left hover:bg-blue-50 rounded-xl transition-all duration-200 group"
              >
                <div class="w-10 h-10 bg-blue-100 group-hover:bg-blue-200 rounded-lg flex items-center justify-center transition-colors">
                  <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                  </svg>
                </div>
                <div>
                  <p class="font-medium text-gray-900">Create Event</p>
                  <p class="text-sm text-gray-500">Schedule a new meeting</p>
                </div>
              </button>

              <button class="w-full flex items-center space-x-3 p-3 text-left hover:bg-green-50 rounded-xl transition-all duration-200 group">
                <div class="w-10 h-10 bg-green-100 group-hover:bg-green-200 rounded-lg flex items-center justify-center transition-colors">
                  <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                  </svg>
                </div>
                <div>
                  <p class="font-medium text-gray-900">Invite Team</p>
                  <p class="text-sm text-gray-500">Add team members</p>
                </div>
              </button>

              <button class="w-full flex items-center space-x-3 p-3 text-left hover:bg-purple-50 rounded-xl transition-all duration-200 group">
                <div class="w-10 h-10 bg-purple-100 group-hover:bg-purple-200 rounded-lg flex items-center justify-center transition-colors">
                  <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                  </svg>
                </div>
                <div>
                  <p class="font-medium text-gray-900">Analytics</p>
                  <p class="text-sm text-gray-500">View insights</p>
                </div>
              </button>
            </div>
          </div>

          <!-- Upcoming Events -->
          <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6">
            <h4 class="text-lg font-semibold text-gray-900 mb-4">Upcoming Events</h4>
            <div class="space-y-4">
              <div v-for="event in upcomingEvents" :key="event.id" class="flex items-start space-x-3 p-3 hover:bg-gray-50 rounded-xl transition-colors">
                <div :class="['w-3 h-3 rounded-full mt-2']" :style="{ backgroundColor: event.color }"></div>
                <div class="flex-1 min-w-0">
                  <p class="font-medium text-gray-900 truncate">{{ event.title }}</p>
                  <p class="text-sm text-gray-500">{{ event.date }} at {{ event.time }}</p>
                  <p class="text-xs text-gray-400">{{ event.location }}</p>
                </div>
              </div>
              <div v-if="upcomingEvents.length === 0" class="text-center py-4 text-gray-500">
                No upcoming events
              </div>
            </div>
          </div>

          <!-- Weather Widget -->
          <div class="bg-gradient-to-br from-blue-400 to-blue-600 rounded-2xl shadow-xl p-6 text-white" v-if="weather">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h4 class="text-lg font-semibold">Today's Weather</h4>
                <p class="text-blue-100 text-sm">{{ weather.location }}</p>
              </div>
              <div class="text-right">
                <p class="text-3xl font-bold">{{ weather.temperature }}°C</p>
                <p class="text-blue-100 text-sm">{{ weather.description }}</p>
              </div>
            </div>
            <div class="flex items-center space-x-4 text-sm">
              <div class="flex items-center space-x-1">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M5.5 16a3.5 3.5 0 01-.369-6.98 4 4 0 117.753-1.977A4.5 4.5 0 1113.5 16h-8z"></path>
                </svg>
                <span>{{ weather.humidity }}%</span>
              </div>
              <div class="flex items-center space-x-1">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                </svg>
                <span>{{ weather.wind_speed }} km/h</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Event Modal -->
    <div v-if="showEventModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="showEventModal = false"></div>
        
        <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-2xl">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Create New Event</h3>
            <button @click="showEventModal = false" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          
          <form @submit.prevent="createEvent" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Event Title</label>
              <input 
                v-model="newEvent.title"
                type="text" 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Enter event title"
                required
              />
            </div>
            
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input 
                  v-model="newEvent.start_date"
                  type="datetime-local" 
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  required
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input 
                  v-model="newEvent.end_date"
                  type="datetime-local" 
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  required
                />
              </div>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea 
                v-model="newEvent.description"
                rows="3" 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Event description (optional)"
              ></textarea>
            </div>
            
            <div class="flex justify-end space-x-3 pt-4">
              <button 
                type="button" 
                @click="showEventModal = false"
                class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
              >
                Cancel
              </button>
              <button 
                type="submit"
                class="px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg hover:from-blue-700 hover:to-indigo-700 transition-all duration-200 shadow-lg hover:shadow-xl"
              >
                Create Event
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Toast Notifications -->
    <Toast />
  </AuthenticatedLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, computed, onMounted, reactive } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Calendar from '@/Components/Calendar.vue'
import Toast from '@/Components/Toast.vue'

// Props from backend
const props = defineProps({
  stats: {
    type: Object,
    default: () => ({
      todayEvents: 0,
      weekEvents: 0,
      monthEvents: 0,
      upcomingEvents: 0,
      completedEvents: 0,
      productivity: 0,
      calendarsCount: 0
    })
  },
  upcomingEvents: {
    type: Array,
    default: () => []
  },
  weather: {
    type: Object,
    default: null
  },
  user: {
    type: Object,
    default: () => ({
      name: 'User',
      email: '',
      location: 'Jakarta, Indonesia'
    })
  }
})

// Reactive data
const currentView = ref('month')
const showEventModal = ref(false)

const newEvent = reactive({
  title: '',
  start_date: '',
  end_date: '',
  description: ''
})

// Methods
const createEvent = async () => {
  try {
    // Validate required fields
    if (!newEvent.title || !newEvent.start_date || !newEvent.end_date) {
      if (window.toast) {
        window.toast.error('Validation Error', 'Please fill in all required fields.')
      }
      return
    }

    // Show loading toast
    if (window.toast) {
      window.toast.info('Creating Event...', 'Please wait while we save your event.')
    }

    // Send data to backend API
    const response = await fetch('/api/dashboard/events', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        title: newEvent.title,
        start_date: newEvent.start_date,
        end_date: newEvent.end_date,
        description: newEvent.description || null,
        all_day: false,
        status: 'confirmed'
      })
    })

    const result = await response.json()

    if (response.ok) {
      const eventTitle = newEvent.title
      
      // Reset form and close modal
      Object.assign(newEvent, {
        title: '',
        start_date: '',
        end_date: '',
        description: ''
      })
      showEventModal.value = false
      
      // Show success toast
      if (window.toast) {
        window.toast.success(
          'Event Created Successfully!', 
          `"${eventTitle}" has been saved to your calendar.`
        )
      }

      // Refresh data using Inertia instead of page reload
      setTimeout(() => {
        window.location.href = window.location.href
      }, 1000)
      
    } else {
      // Handle validation errors
      if (result.errors) {
        const errorMessages = Object.values(result.errors).flat().join(', ')
        if (window.toast) {
          window.toast.error('Validation Error', errorMessages)
        }
      } else {
        if (window.toast) {
          window.toast.error('Error', result.message || 'Failed to create event.')
        }
      }
    }
  } catch (error) {
    console.error('Error creating event:', error)
    if (window.toast) {
      window.toast.error('Network Error', 'Failed to connect to server. Please try again.')
    }
  }
}

// Animation classes
onMounted(() => {
  // Add entrance animations
  const elements = document.querySelectorAll('.animate-fade-in-up')
  elements.forEach((el, index) => {
    el.style.animationDelay = `${index * 0.1}s`
  })
})
</script>

<style scoped>
@keyframes fade-in-up {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fade-in-up {
  animation: fade-in-up 0.6s ease-out forwards;
}

/* Custom scrollbar */
::-webkit-scrollbar {
  width: 6px;
}

::-webkit-scrollbar-track {
  background: #f1f5f9;
}

::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>

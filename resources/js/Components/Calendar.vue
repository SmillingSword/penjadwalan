<template>
  <div class="modern-calendar-container">
    <!-- Calendar Header with Animations -->
    <div class="calendar-header bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 rounded-2xl p-6 mb-6 text-white relative overflow-hidden">
      <!-- Animated Background Elements -->
      <div class="absolute inset-0 opacity-20">
        <div class="floating-circle absolute top-4 right-8 w-16 h-16 bg-white rounded-full animate-float"></div>
        <div class="floating-circle absolute bottom-6 left-12 w-12 h-12 bg-white rounded-full animate-float-delayed"></div>
        <div class="floating-circle absolute top-1/2 right-1/4 w-8 h-8 bg-white rounded-full animate-pulse"></div>
      </div>
      
      <div class="relative z-10">
        <div class="flex items-center justify-between mb-4">
          <div class="animate-slide-in-left">
            <h2 class="text-2xl font-bold mb-1">{{ currentMonthYear }}</h2>
            <p class="text-indigo-100">Manage your schedule like a pro</p>
          </div>
          
          <!-- Navigation Controls -->
          <div class="flex items-center space-x-3 animate-slide-in-right">
            <button 
              @click="previousMonth"
              class="nav-btn bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-xl p-3 transition-all duration-300 hover:scale-110"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
              </svg>
            </button>
            
            <button 
              @click="goToToday"
              class="nav-btn bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-xl px-4 py-3 text-sm font-medium transition-all duration-300 hover:scale-105"
            >
              Today
            </button>
            
            <button 
              @click="nextMonth"
              class="nav-btn bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-xl p-3 transition-all duration-300 hover:scale-110"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
              </svg>
            </button>
          </div>
        </div>
        
        <!-- View Toggle -->
        <div class="flex items-center space-x-2 animate-fade-in-up">
          <div class="bg-white/10 backdrop-blur-sm rounded-xl p-1 flex">
            <button 
              v-for="view in ['month', 'week', 'day']" 
              :key="view"
              @click="changeView(view)"
              :class="[
                'px-4 py-2 text-sm font-medium rounded-lg transition-all duration-300',
                currentView === view 
                  ? 'bg-white text-indigo-600 shadow-lg transform scale-105' 
                  : 'text-white hover:bg-white/20'
              ]"
            >
              {{ view.charAt(0).toUpperCase() + view.slice(1) }}
            </button>
          </div>
          
          <div class="flex items-center space-x-2 ml-4">
            <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
            <span class="text-sm text-indigo-100">{{ eventsCount }} events this month</span>
          </div>
          
          <!-- Swipe instruction for mobile -->
          <div class="swipe-instruction md:hidden">
            ← Swipe to navigate months →
          </div>
        </div>
      </div>
    </div>

    <!-- Main Calendar Grid -->
    <div 
      class="calendar-grid bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden"
      @touchstart="handleTouchStart"
      @touchmove="handleTouchMove"
      @touchend="handleTouchEnd"
    >
      <!-- Days of Week Header -->
      <div class="days-header bg-gradient-to-r from-gray-50 to-blue-50 grid grid-cols-7 border-b border-gray-200">
        <div 
          v-for="day in daysOfWeek" 
          :key="day"
          class="day-header p-4 text-center font-semibold text-gray-700 border-r border-gray-100 last:border-r-0"
        >
          <div class="animate-bounce-in">{{ day }}</div>
        </div>
      </div>

      <!-- Calendar Days -->
      <div class="calendar-body grid grid-cols-7">
        <div 
          v-for="(day, index) in calendarDays" 
          :key="index"
          :class="[
            'calendar-day relative h-24 md:h-32 border-r border-b border-gray-100 last:border-r-0 transition-all duration-300 hover:bg-blue-50 cursor-pointer group flex flex-col',
            {
              'bg-gray-50 text-gray-400': !day.isCurrentMonth,
              'bg-blue-100 ring-2 ring-blue-500': day.isToday,
              'hover:shadow-lg hover:scale-105 hover:z-10': day.isCurrentMonth
            }
          ]"
          @click="selectDate(day)"
          :style="{ animationDelay: `${index * 20}ms` }"
          class="animate-fade-in-scale"
        >
          <!-- Day Number -->
          <div class="day-number p-3">
            <span 
              :class="[
                'inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-medium transition-all duration-300',
                {
                  'bg-indigo-600 text-white shadow-lg': day.isToday,
                  'text-gray-900 group-hover:bg-indigo-100': day.isCurrentMonth && !day.isToday,
                  'text-gray-400': !day.isCurrentMonth
                }
              ]"
            >
              {{ day.date }}
            </span>
          </div>

          <!-- Events for this day -->
          <div class="events-container flex-1 px-2 pb-2 space-y-1 flex flex-col justify-between">
            <!-- Desktop view: Show individual events -->
            <div class="hidden md:block flex-1">
              <div 
                v-for="event in getEventsForDay(day)" 
                :key="event.id"
                :class="[
                  'event-item px-2 py-1 rounded-md text-xs font-medium truncate transition-all duration-300 hover:scale-105 cursor-pointer',
                  'animate-slide-in-up'
                ]"
                :style="{ 
                  backgroundColor: event.color + '20',
                  borderLeft: `3px solid ${event.color}`,
                  color: event.color
                }"
                @click.stop="openEventDetails(event)"
              >
                <div class="flex items-center space-x-1">
                  <div class="w-2 h-2 rounded-full animate-pulse" :style="{ backgroundColor: event.color }"></div>
                  <span>{{ event.title }}</span>
                </div>
              </div>
              
              <!-- More events indicator for desktop -->
              <div 
                v-if="getEventsForDay(day).length > 3"
                class="more-events text-xs text-gray-500 font-medium hover:text-indigo-600 cursor-pointer animate-bounce"
                @click.stop="showMoreEvents(day)"
              >
                +{{ getEventsForDay(day).length - 3 }} more
              </div>
            </div>

            <!-- Mobile view: Show event count only -->
            <div class="md:hidden flex-1 flex items-end justify-end">
              <div 
                v-if="getAllEventsForDay(day).length > 0"
                class="event-count-mobile bg-indigo-600 text-white w-5 h-5 rounded-full text-xs font-bold cursor-pointer hover:bg-indigo-700 transition-all duration-200 flex items-center justify-center shadow-sm"
                @click.stop="showDayEvents(day)"
              >
                {{ getAllEventsForDay(day).length }}
              </div>
            </div>
          </div>

          <!-- Add Event Button (appears on hover) -->
          <div class="add-event-btn absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-all duration-300">
            <button 
              @click.stop="createEvent(day)"
              class="w-6 h-6 bg-indigo-600 text-white rounded-full flex items-center justify-center hover:bg-indigo-700 hover:scale-110 transition-all duration-200 shadow-lg"
            >
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Stats -->
    <div class="quick-stats mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="stat-card bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-4 text-white animate-slide-in-up">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-blue-100 text-sm">Today's Events</p>
            <p class="text-2xl font-bold">{{ todayEventsCount }}</p>
          </div>
          <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
              <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
          </div>
        </div>
      </div>

      <div class="stat-card bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-4 text-white animate-slide-in-up" style="animation-delay: 100ms">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-green-100 text-sm">This Week</p>
            <p class="text-2xl font-bold">{{ weekEventsCount }}</p>
          </div>
          <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
              <path d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1z"></path>
            </svg>
          </div>
        </div>
      </div>

      <div class="stat-card bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-4 text-white animate-slide-in-up" style="animation-delay: 200ms">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-purple-100 text-sm">Upcoming</p>
            <p class="text-2xl font-bold">{{ upcomingEventsCount }}</p>
          </div>
          <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
            </svg>
          </div>
        </div>
      </div>

      <div class="stat-card bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-4 text-white animate-slide-in-up" style="animation-delay: 300ms">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-orange-100 text-sm">Completed</p>
            <p class="text-2xl font-bold">{{ completedEventsCount }}</p>
          </div>
          <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Event Details Modal -->
    <div v-if="showEventModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-75 backdrop-blur-sm" @click="closeEventModal"></div>
        
        <div class="inline-block w-full max-w-lg p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl animate-modal-appear">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-gray-900">{{ selectedEvent?.title || 'Event Details' }}</h3>
            <button @click="closeEventModal" class="text-gray-400 hover:text-gray-600 hover:scale-110 transition-all duration-200">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          
          <div v-if="selectedEvent" class="space-y-4">
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-4">
              <div class="flex items-center space-x-3">
                <div class="w-4 h-4 rounded-full animate-pulse" :style="{ backgroundColor: selectedEvent.color }"></div>
                <div>
                  <p class="font-medium text-gray-900">{{ selectedEvent.title }}</p>
                  <p class="text-sm text-gray-600">{{ formatEventTime(selectedEvent) }}</p>
                </div>
              </div>
            </div>
            
            <div v-if="selectedEvent.description" class="text-gray-700">
              <h4 class="font-medium mb-2">Description</h4>
              <p class="text-sm bg-gray-50 rounded-lg p-3">{{ selectedEvent.description }}</p>
            </div>
            
            <div class="flex justify-end space-x-3 pt-4">
              <button 
                @click="editEvent"
                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-all duration-200 hover:scale-105 shadow-lg"
              >
                Edit Event
              </button>
              <button 
                @click="deleteEvent"
                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all duration-200 hover:scale-105 shadow-lg"
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Event Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-75 backdrop-blur-sm" @click="closeCreateModal"></div>
        
        <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl animate-modal-appear">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-gray-900">{{ newEvent.id ? 'Edit Event' : 'Create New Event' }}</h3>
            <button @click="closeCreateModal" class="text-gray-400 hover:text-gray-600 hover:scale-110 transition-all duration-200">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          
          <form @submit.prevent="saveEvent" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Event Title</label>
              <input 
                v-model="newEvent.title"
                type="text" 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200"
                placeholder="Enter event title"
                required
              />
            </div>
            
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                <input 
                  v-model="newEvent.start_time"
                  type="time" 
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                <input 
                  v-model="newEvent.end_time"
                  type="time" 
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200"
                />
              </div>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea 
                v-model="newEvent.description"
                rows="3" 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200"
                placeholder="Event description (optional)"
              ></textarea>
            </div>
            
            <div class="flex justify-end space-x-3 pt-4">
              <button 
                type="button" 
                @click="closeCreateModal"
                class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all duration-200"
              >
                Cancel
              </button>
              <button 
                type="submit"
                class="px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-lg hover:from-indigo-700 hover:to-purple-700 transition-all duration-200 shadow-lg hover:shadow-xl hover:scale-105"
              >
                {{ newEvent.id ? 'Update Event' : 'Create Event' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Confirmation Modal -->
    <ConfirmationModal
      :show="showConfirmModal"
      title="Delete Event"
      :message="`Are you sure you want to delete '${eventToDelete?.title}'?`"
      details="This action cannot be undone and the event will be permanently removed from your calendar."
      confirm-text="Delete Event"
      cancel-text="Keep Event"
      type="danger"
      @confirm="confirmDelete"
      @cancel="cancelDelete"
    />

    <!-- Day Events Modal (for mobile) -->
    <div v-if="showDayEventsModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-75 backdrop-blur-sm" @click="closeDayEventsModal"></div>
        
        <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl animate-modal-appear">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-gray-900">
              Events for {{ selectedDayForEvents?.fullDate ? formatDate(selectedDayForEvents.fullDate) : '' }}
            </h3>
            <button @click="closeDayEventsModal" class="text-gray-400 hover:text-gray-600 hover:scale-110 transition-all duration-200">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          
          <div class="space-y-3 max-h-96 overflow-y-auto">
            <div 
              v-for="event in getAllEventsForDay(selectedDayForEvents)" 
              :key="event.id"
              class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-4 hover:shadow-md transition-all duration-200 cursor-pointer"
              @click="openEventFromDayModal(event)"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <div class="flex items-center space-x-2 mb-2">
                    <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: event.color }"></div>
                    <h4 class="font-medium text-gray-900">{{ event.title }}</h4>
                  </div>
                  <p class="text-sm text-gray-600 mb-1">{{ formatEventTime(event) }}</p>
                  <p v-if="event.description" class="text-sm text-gray-500 truncate">{{ event.description }}</p>
                </div>
                <div class="flex space-x-2 ml-4">
                  <button 
                    @click.stop="editEventFromDayModal(event)"
                    class="p-2 text-indigo-600 hover:bg-indigo-100 rounded-lg transition-colors"
                    title="Edit Event"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                  </button>
                  <button 
                    @click.stop="deleteEventFromDayModal(event)"
                    class="p-2 text-red-600 hover:bg-red-100 rounded-lg transition-colors"
                    title="Delete Event"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                  </button>
                </div>
              </div>
            </div>
            
            <div v-if="getAllEventsForDay(selectedDayForEvents).length === 0" class="text-center py-8 text-gray-500">
              No events for this day
            </div>
          </div>
          
          <div class="mt-6 pt-4 border-t border-gray-200">
            <button 
              @click="createEventFromDayModal"
              class="w-full px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-lg hover:from-indigo-700 hover:to-purple-700 transition-all duration-200 shadow-lg hover:shadow-xl"
            >
              Add New Event
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Toast Notifications -->
    <Toast />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive, watch } from 'vue'
import ConfirmationModal from '@/Components/ConfirmationModal.vue'
import Toast from '@/Components/Toast.vue'

// Reactive data
const currentDate = ref(new Date())
const currentView = ref('month')
const showEventModal = ref(false)
const showCreateModal = ref(false)
const showConfirmModal = ref(false)
const showDayEventsModal = ref(false)
const selectedEvent = ref(null)
const selectedDate = ref(null)
const selectedDayForEvents = ref(null)
const eventToDelete = ref(null)

const newEvent = reactive({
  title: '',
  start_time: '',
  end_time: '',
  description: '',
  date: ''
})

// Touch/Swipe handling for mobile
const touchStartX = ref(0)
const touchStartY = ref(0)
const touchEndX = ref(0)
const touchEndY = ref(0)
const minSwipeDistance = 50
const maxVerticalDistance = 100

// Events data from API
const events = ref([])
const isLoading = ref(false)

// Computed properties
const daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

const currentMonthYear = computed(() => {
  return currentDate.value.toLocaleDateString('en-US', { 
    month: 'long', 
    year: 'numeric' 
  })
})

const calendarDays = computed(() => {
  const year = currentDate.value.getFullYear()
  const month = currentDate.value.getMonth()
  
  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)
  const startDate = new Date(firstDay)
  startDate.setDate(startDate.getDate() - firstDay.getDay())
  
  const days = []
  const today = new Date()
  
  for (let i = 0; i < 42; i++) {
    const date = new Date(startDate)
    date.setDate(startDate.getDate() + i)
    
    days.push({
      date: date.getDate(),
      fullDate: date.toISOString().split('T')[0],
      isCurrentMonth: date.getMonth() === month,
      isToday: date.toDateString() === today.toDateString(),
      dateObj: new Date(date)
    })
  }
  
  return days
})

const eventsCount = computed(() => events.value.length)
const todayEventsCount = computed(() => {
  const today = new Date().toISOString().split('T')[0]
  return events.value.filter(event => event.date === today).length
})

const weekEventsCount = computed(() => {
  const today = new Date()
  const weekStart = new Date(today.setDate(today.getDate() - today.getDay()))
  const weekEnd = new Date(today.setDate(today.getDate() - today.getDay() + 6))
  
  return events.value.filter(event => {
    const eventDate = new Date(event.date)
    return eventDate >= weekStart && eventDate <= weekEnd
  }).length
})

const upcomingEventsCount = computed(() => {
  const today = new Date().toISOString().split('T')[0]
  return events.value.filter(event => event.date > today).length
})

const completedEventsCount = computed(() => {
  const today = new Date().toISOString().split('T')[0]
  return events.value.filter(event => event.date < today).length
})

// Methods
const getEventsForDay = (day) => {
  return events.value.filter(event => event.date === day.fullDate).slice(0, 3)
}

const getAllEventsForDay = (day) => {
  if (!day) return []
  return events.value.filter(event => event.date === day.fullDate)
}

const selectDate = (day) => {
  selectedDate.value = day
  console.log('Date selected:', day.fullDate)
  
  // Show create event modal when clicking on a date
  if (day.isCurrentMonth) {
    createEvent(day)
  }
}

const createEvent = (day) => {
  selectedDate.value = day
  newEvent.date = day.fullDate
  showCreateModal.value = true
  console.log('Creating event for:', day.fullDate)
}

const openEventDetails = (event) => {
  selectedEvent.value = event
  showEventModal.value = true
}

const closeEventModal = () => {
  showEventModal.value = false
  selectedEvent.value = null
}

const closeCreateModal = () => {
  showCreateModal.value = false
  Object.assign(newEvent, {
    title: '',
    start_time: '',
    end_time: '',
    description: '',
    date: '',
    id: null // Reset ID when closing modal
  })
}

const saveEvent = async () => {
  try {
    // Validate required fields
    if (!newEvent.title || !newEvent.date) {
      if (window.toast) {
        window.toast.error('Validation Error', 'Please fill in all required fields.')
      }
      return
    }

    const isEditing = !!newEvent.id
    const actionText = isEditing ? 'Updating' : 'Creating'
    const successText = isEditing ? 'Updated' : 'Created'

    // Show loading toast
    if (window.toast) {
      window.toast.info(`${actionText} Event...`, `Please wait while we save your event.`)
    }

    // Prepare event data for API
    let startDateTime = newEvent.start_time 
      ? `${newEvent.date} ${newEvent.start_time}:00`
      : `${newEvent.date} 09:00:00`
    
    let endDateTime = newEvent.end_time 
      ? `${newEvent.date} ${newEvent.end_time}:00`
      : `${newEvent.date} 10:00:00`

    // Validate that end time is after start time
    const startTime = newEvent.start_time || '09:00'
    const endTime = newEvent.end_time || '10:00'
    
    if (startTime >= endTime) {
      // If end time is earlier than start time, assume it's next day
      const nextDay = new Date(newEvent.date)
      nextDay.setDate(nextDay.getDate() + 1)
      const nextDayStr = nextDay.toISOString().split('T')[0]
      endDateTime = `${nextDayStr} ${endTime}:00`
    }

    // Determine URL and method based on whether we're editing
    const url = isEditing 
      ? `/api/dashboard/events/${newEvent.id}`
      : '/api/dashboard/events'
    const method = isEditing ? 'PUT' : 'POST'

    // Send data to backend API
    const response = await fetch(url, {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        title: newEvent.title,
        start_date: startDateTime,
        end_date: endDateTime,
        description: newEvent.description || null,
        all_day: false,
        status: 'confirmed'
      })
    })

    const result = await response.json()

    if (response.ok) {
      const eventTitle = newEvent.title
      
      // Show success toast
      if (window.toast) {
        window.toast.success(
          `Event ${successText} Successfully!`, 
          `"${eventTitle}" has been saved to your calendar.`
        )
      }

      // Close modal and refresh events
      closeCreateModal()
      
      // Refresh events from API
      await fetchEvents()
      
    } else {
      // Handle validation errors
      if (result.errors) {
        const errorMessages = Object.values(result.errors).flat().join(', ')
        if (window.toast) {
          window.toast.error('Validation Error', errorMessages)
        }
      } else {
        if (window.toast) {
          window.toast.error('Error', result.message || `Failed to ${actionText.toLowerCase()} event.`)
        }
      }
    }
  } catch (error) {
    console.error('Error saving event:', error)
    if (window.toast) {
      window.toast.error('Network Error', 'Failed to connect to server. Please try again.')
    }
  }
}

const editEvent = () => {
  // Pre-fill the edit form with current event data
  if (selectedEvent.value) {
    // Convert the event data to the format expected by the form
    let eventDate = selectedEvent.value.date || selectedEvent.value.start_date?.split(' ')[0]
    let startTime = selectedEvent.value.time || selectedEvent.value.start_date?.split(' ')[1]?.substring(0, 5)
    let endTime = selectedEvent.value.end_time || selectedEvent.value.end_date?.split(' ')[1]?.substring(0, 5)
    
    // Handle datetime parsing from API response
    if (selectedEvent.value.start_date && !startTime) {
      const startDate = new Date(selectedEvent.value.start_date)
      startTime = startDate.toTimeString().substring(0, 5)
      eventDate = startDate.toISOString().split('T')[0]
    }
    
    if (selectedEvent.value.end_date && !endTime) {
      const endDate = new Date(selectedEvent.value.end_date)
      endTime = endDate.toTimeString().substring(0, 5)
    }
    
    Object.assign(newEvent, {
      title: selectedEvent.value.title,
      start_time: startTime || '09:00',
      end_time: endTime || '10:00',
      description: selectedEvent.value.description || '',
      date: eventDate,
      id: selectedEvent.value.id // Store the ID for updating
    })
    
    closeEventModal()
    showCreateModal.value = true
  }
}

const deleteEvent = () => {
  eventToDelete.value = selectedEvent.value
  showConfirmModal.value = true
}

const confirmDelete = async () => {
  if (eventToDelete.value) {
    try {
      // Show loading toast
      if (window.toast) {
        window.toast.info('Deleting Event...', 'Please wait while we delete your event.')
      }

      // Send delete request to API
      const response = await fetch(`/api/dashboard/events/${eventToDelete.value.id}`, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          'Accept': 'application/json'
        }
      })

      const result = await response.json()

      if (response.ok) {
        // Show success toast
        if (window.toast) {
          window.toast.success(
            'Event Deleted!', 
            `"${eventToDelete.value.title}" has been successfully deleted.`
          )
        }
        
        // Refresh events from API
        await fetchEvents()
        
        closeEventModal()
        showConfirmModal.value = false
        eventToDelete.value = null
      } else {
        if (window.toast) {
          window.toast.error('Error', result.message || 'Failed to delete event.')
        }
      }
    } catch (error) {
      console.error('Error deleting event:', error)
      if (window.toast) {
        window.toast.error('Network Error', 'Failed to connect to server. Please try again.')
      }
    }
  }
}

const cancelDelete = () => {
  showConfirmModal.value = false
  eventToDelete.value = null
}

const previousMonth = () => {
  currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1, 1)
}

const nextMonth = () => {
  currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 1)
}

const goToToday = () => {
  currentDate.value = new Date()
}

const changeView = (view) => {
  currentView.value = view
}

const formatEventTime = (event) => {
  return `${event.date} at ${event.time}`
}

const showMoreEvents = (day) => {
  // Show all events for this day in a modal
  selectedDayForEvents.value = day
  showDayEventsModal.value = true
}

const showDayEvents = (day) => {
  selectedDayForEvents.value = day
  showDayEventsModal.value = true
}

const closeDayEventsModal = () => {
  showDayEventsModal.value = false
  selectedDayForEvents.value = null
}

const openEventFromDayModal = (event) => {
  selectedEvent.value = event
  closeDayEventsModal()
  showEventModal.value = true
}

const editEventFromDayModal = (event) => {
  // Pre-fill the edit form with current event data
  let eventDate = event.date || event.start_date?.split(' ')[0]
  let startTime = event.time || event.start_date?.split(' ')[1]?.substring(0, 5)
  let endTime = event.end_time || event.end_date?.split(' ')[1]?.substring(0, 5)
  
  // Handle datetime parsing from API response
  if (event.start_date && !startTime) {
    const startDate = new Date(event.start_date)
    startTime = startDate.toTimeString().substring(0, 5)
    eventDate = startDate.toISOString().split('T')[0]
  }
  
  if (event.end_date && !endTime) {
    const endDate = new Date(event.end_date)
    endTime = endDate.toTimeString().substring(0, 5)
  }
  
  Object.assign(newEvent, {
    title: event.title,
    start_time: startTime || '09:00',
    end_time: endTime || '10:00',
    description: event.description || '',
    date: eventDate,
    id: event.id // Store the ID for updating
  })
  
  closeDayEventsModal()
  showCreateModal.value = true
}

const deleteEventFromDayModal = (event) => {
  eventToDelete.value = event
  closeDayEventsModal()
  showConfirmModal.value = true
}

const createEventFromDayModal = () => {
  if (selectedDayForEvents.value) {
    newEvent.date = selectedDayForEvents.value.fullDate
    closeDayEventsModal()
    showCreateModal.value = true
  }
}

const formatDate = (dateString) => {
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', { 
    weekday: 'long', 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric' 
  })
}

// Touch/Swipe handling methods
const handleTouchStart = (event) => {
  const touch = event.touches[0]
  touchStartX.value = touch.clientX
  touchStartY.value = touch.clientY
}

const handleTouchMove = (event) => {
  // Prevent default scrolling behavior during swipe
  if (Math.abs(event.touches[0].clientX - touchStartX.value) > 10) {
    event.preventDefault()
  }
}

const handleTouchEnd = (event) => {
  const touch = event.changedTouches[0]
  touchEndX.value = touch.clientX
  touchEndY.value = touch.clientY
  
  handleSwipeGesture()
}

const handleSwipeGesture = () => {
  const deltaX = touchEndX.value - touchStartX.value
  const deltaY = touchEndY.value - touchStartY.value
  
  // Check if it's a horizontal swipe (not vertical scroll)
  if (Math.abs(deltaY) > maxVerticalDistance) {
    return // Too much vertical movement, probably a scroll
  }
  
  // Check if swipe distance is sufficient
  if (Math.abs(deltaX) < minSwipeDistance) {
    return // Swipe distance too small
  }
  
  // Determine swipe direction and navigate
  if (deltaX > 0) {
    // Swipe right - go to previous month
    previousMonth()
    
    // Show feedback toast
    if (window.toast) {
      window.toast.info('Previous Month', 'Swiped to previous month')
    }
  } else {
    // Swipe left - go to next month
    nextMonth()
    
    // Show feedback toast
    if (window.toast) {
      window.toast.info('Next Month', 'Swiped to next month')
    }
  }
}

// API functions
const fetchEvents = async () => {
  isLoading.value = true
  try {
    const response = await fetch(`/api/dashboard/events?month=${currentDate.value.getMonth() + 1}&year=${currentDate.value.getFullYear()}`)
    if (response.ok) {
      const data = await response.json()
      events.value = data
    }
  } catch (error) {
    console.error('Error fetching events:', error)
  } finally {
    isLoading.value = false
  }
}

const saveEventToAPI = async (eventData) => {
  try {
    const response = await fetch('/api/events', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      },
      body: JSON.stringify(eventData)
    })
    
    if (response.ok) {
      const newEvent = await response.json()
      events.value.push(newEvent)
      return newEvent
    }
  } catch (error) {
    console.error('Error saving event:', error)
    throw error
  }
}

onMounted(() => {
  // Initialize calendar and fetch events
  fetchEvents()
})

// Watch for date changes to refetch events
watch(currentDate, () => {
  fetchEvents()
})
</script>

<style scoped>
.modern-calendar-container {
  width: 100%;
  max-width: 100%;
}

/* Animations */
@keyframes float {
  0%, 100% { transform: translateY(0px); }
  50% { transform: translateY(-20px); }
}

@keyframes float-delayed {
  0%, 100% { transform: translateY(0px); }
  50% { transform: translateY(-15px); }
}

@keyframes slide-in-left {
  from {
    opacity: 0;
    transform: translateX(-30px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes slide-in-right {
  from {
    opacity: 0;
    transform: translateX(30px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes slide-in-up {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes fade-in-up {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes fade-in-scale {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

@keyframes bounce-in {
  0% {
    opacity: 0;
    transform: scale(0.3);
  }
  50% {
    opacity: 1;
    transform: scale(1.05);
  }
  70% {
    transform: scale(0.9);
  }
  100% {
    opacity: 1;
    transform: scale(1);
  }
}

@keyframes modal-appear {
  from {
    opacity: 0;
    transform: scale(0.95) translateY(-10px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

.animate-float {
  animation: float 6s ease-in-out infinite;
}

.animate-float-delayed {
  animation: float-delayed 8s ease-in-out infinite;
}

.animate-slide-in-left {
  animation: slide-in-left 0.6s ease-out;
}

.animate-slide-in-right {
  animation: slide-in-right 0.6s ease-out;
}

.animate-slide-in-up {
  animation: slide-in-up 0.4s ease-out;
}

.animate-fade-in-up {
  animation: fade-in-up 0.5s ease-out;
}

.animate-fade-in-scale {
  animation: fade-in-scale 0.3s ease-out;
}

.animate-bounce-in {
  animation: bounce-in 0.6s ease-out;
}

.animate-modal-appear {
  animation: modal-appear 0.3s ease-out;
}

/* Custom styles */
.calendar-day:hover .add-event-btn {
  transform: scale(1.1);
}

.event-item:hover {
  transform: translateX(2px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.nav-btn:hover {
  box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3);
}

.stat-card {
  transform: translateY(0);
  transition: all 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

/* Responsive design */
@media (max-width: 768px) {
  .modern-calendar-container {
    padding: 0.5rem;
  }
  
  .calendar-header {
    margin-bottom: 1rem;
    padding: 1rem;
    border-radius: 1rem;
  }
  
  .calendar-header h2 {
    font-size: 1.25rem;
    margin-bottom: 0.25rem;
  }
  
  .calendar-header p {
    font-size: 0.875rem;
  }
  
  .calendar-header .flex:first-of-type {
    flex-direction: column;
    gap: 0.75rem;
    align-items: flex-start;
  }
  
  .calendar-header .flex:last-of-type {
    flex-direction: row;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
  }
  
  .calendar-header .nav-btn {
    padding: 0.5rem;
    font-size: 0.875rem;
  }
  
  .calendar-grid {
    border-radius: 1rem;
  }
  
  .calendar-day {
    min-height: 5rem;
    padding: 0.25rem;
    cursor: pointer;
    position: relative;
  }
  
  .day-header {
    padding: 0.75rem 0.5rem;
    font-size: 0.75rem;
  }
  
  .day-number {
    padding: 0.5rem;
  }
  
  .day-number span {
    width: 1.75rem;
    height: 1.75rem;
    font-size: 0.75rem;
  }
  
  .event-item {
    font-size: 0.625rem;
    padding: 0.125rem 0.375rem;
    margin-bottom: 0.125rem;
    border-radius: 0.25rem;
  }
  
  .events-container {
    padding: 0 0.375rem 0.375rem;
  }
  
  .add-event-btn {
    display: none;
  }
  
  .quick-stats {
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
    margin-top: 1rem;
  }
  
  .stat-card {
    padding: 0.75rem;
  }
  
  .stat-card p:first-child {
    font-size: 0.75rem;
  }
  
  .stat-card p:last-child {
    font-size: 1.25rem;
  }
}

@media (max-width: 480px) {
  .modern-calendar-container {
    padding: 0.25rem;
  }
  
  .calendar-header {
    padding: 0.75rem;
  }
  
  .calendar-header h2 {
    font-size: 1.125rem;
  }
  
  .calendar-day {
    min-height: 4rem;
    cursor: pointer;
  }
  
  .day-number {
    padding: 0.25rem;
  }
  
  .day-number span {
    width: 1.5rem;
    height: 1.5rem;
    font-size: 0.625rem;
  }
  
  .event-item {
    font-size: 0.5rem;
    padding: 0.125rem 0.25rem;
  }
  
  .events-container {
    padding: 0 0.25rem 0.25rem;
  }
  
  .events-container::after {
    content: attr(data-event-count);
    font-size: 0.5rem;
    color: #6B7280;
    display: block;
    text-align: center;
    margin-top: 0.125rem;
  }
  
  .quick-stats {
    grid-template-columns: 1fr;
    gap: 0.5rem;
  }
  
  .stat-card {
    padding: 0.5rem;
  }
  
  .stat-card .flex {
    flex-direction: row;
    align-items: center;
    gap: 0.5rem;
  }
  
  .stat-card .w-12 {
    width: 2rem;
    height: 2rem;
  }
}

/* Touch/Swipe enhancements */
.calendar-grid {
  touch-action: pan-y; /* Allow vertical scrolling but handle horizontal swipes */
  user-select: none; /* Prevent text selection during swipe */
}

.calendar-grid.swiping {
  transition: transform 0.3s ease-out;
}

/* Swipe indicator */
.swipe-indicator {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  background: rgba(99, 102, 241, 0.9);
  color: white;
  padding: 0.5rem 1rem;
  border-radius: 1rem;
  font-size: 0.875rem;
  font-weight: 500;
  z-index: 10;
  opacity: 0;
  transition: opacity 0.2s ease;
  pointer-events: none;
}

.swipe-indicator.left {
  left: 1rem;
}

.swipe-indicator.right {
  right: 1rem;
}

.swipe-indicator.show {
  opacity: 1;
}

/* Mobile-specific improvements */
@media (max-width: 768px) {
  .calendar-grid {
    position: relative;
    overflow: hidden;
  }
  
  /* Add subtle hint for swipe gesture */
  .calendar-grid::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, rgba(99, 102, 241, 0.3), transparent);
    z-index: 1;
    animation: swipe-hint 3s ease-in-out infinite;
  }
  
  .calendar-header {
    position: relative;
  }
  
  /* Add swipe instruction for first-time users */
  .swipe-instruction {
    position: absolute;
    bottom: -2rem;
    left: 50%;
    transform: translateX(-50%);
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.7);
    text-align: center;
    animation: fade-in-out 4s ease-in-out infinite;
  }
}

@keyframes swipe-hint {
  0%, 100% { opacity: 0; transform: translateX(-100%); }
  50% { opacity: 1; transform: translateX(100%); }
}

@keyframes fade-in-out {
  0%, 70%, 100% { opacity: 0; }
  10%, 60% { opacity: 1; }
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

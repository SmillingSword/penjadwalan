<template>
  <div class="calendar-container">
    <div class="flex gap-6">
      <!-- Calendar Sidebar -->
      <div class="w-80 flex-shrink-0">
        <CalendarPicker
          :calendars="calendars"
          :selected-calendars="selectedCalendars"
          @calendar-toggled="toggleCalendar"
          @calendar-created="handleCalendarCreated"
          @calendar-updated="handleCalendarUpdated"
          @calendar-deleted="handleCalendarDeleted"
        />
      </div>

      <!-- Main Calendar -->
      <div class="flex-1">
        <FullCalendar
          ref="calendarRef"
          :options="calendarOptions"
        />
      </div>
    </div>

    <!-- Event Modal -->
    <EventModal
      :show="showEventModal"
      :event="selectedEvent"
      :calendars="calendars"
      :selected-date="selectedDate"
      @close="closeEventModal"
      @saved="handleEventSaved"
    />

    <!-- Event Details Modal -->
    <Modal :show="showEventDetails" @close="closeEventDetails" max-width="lg">
      <div class="p-6" v-if="selectedEvent">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-xl font-semibold text-gray-900">{{ selectedEvent.title }}</h2>
          <div class="flex items-center space-x-2">
            <button
              @click="editEvent"
              class="p-2 text-gray-400 hover:text-gray-600"
              title="Edit event"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
              </svg>
            </button>
            <button
              @click="deleteEvent"
              class="p-2 text-gray-400 hover:text-red-600"
              title="Delete event"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
              </svg>
            </button>
            <button @click="closeEventDetails" class="p-2 text-gray-400 hover:text-gray-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
        </div>

        <div class="space-y-4">
          <div v-if="selectedEvent.description_md" class="text-gray-700">
            <h3 class="font-medium mb-2">Description</h3>
            <div class="prose prose-sm" v-html="formatMarkdown(selectedEvent.description_md)"></div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <h3 class="font-medium text-gray-900 mb-1">Date & Time</h3>
              <p class="text-gray-700">
                {{ formatEventDateTime(selectedEvent) }}
              </p>
            </div>

            <div v-if="selectedEvent.location">
              <h3 class="font-medium text-gray-900 mb-1">Location</h3>
              <p class="text-gray-700">{{ selectedEvent.location }}</p>
            </div>

            <div v-if="selectedEvent.meeting_link">
              <h3 class="font-medium text-gray-900 mb-1">Meeting Link</h3>
              <a :href="selectedEvent.meeting_link" target="_blank" class="text-indigo-600 hover:text-indigo-800">
                Join Meeting
              </a>
            </div>

            <div>
              <h3 class="font-medium text-gray-900 mb-1">Calendar</h3>
              <div class="flex items-center space-x-2">
                <div
                  class="w-3 h-3 rounded"
                  :style="{ backgroundColor: getCalendarColor(selectedEvent.calendar_id) }"
                ></div>
                <span class="text-gray-700">{{ getCalendarName(selectedEvent.calendar_id) }}</span>
              </div>
            </div>
          </div>

          <div v-if="selectedEvent.is_recurring" class="bg-blue-50 p-3 rounded-md">
            <h3 class="font-medium text-blue-900 mb-1">Recurring Event</h3>
            <p class="text-blue-700 text-sm">{{ selectedEvent.recurrence_description || 'This event repeats' }}</p>
          </div>
        </div>
      </div>
    </Modal>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import rrulePlugin from '@fullcalendar/rrule'
import { DateTime } from 'luxon'
import axios from 'axios'

import CalendarPicker from '@/Components/CalendarPicker.vue'
import EventModal from '@/Components/EventModal.vue'
import Modal from '@/Components/Modal.vue'

const calendarRef = ref(null)
const calendars = ref([])
const selectedCalendars = ref([])
const events = ref([])
const showEventModal = ref(false)
const showEventDetails = ref(false)
const selectedEvent = ref(null)
const selectedDate = ref('')

const calendarOptions = reactive({
  plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, rrulePlugin],
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'dayGridMonth,timeGridWeek,timeGridDay'
  },
  initialView: 'dayGridMonth',
  editable: true,
  selectable: true,
  selectMirror: true,
  dayMaxEvents: true,
  weekends: true,
  timeZone: 'local',
  height: 'auto',
  events: computed(() => filteredEvents.value),
  select: handleDateSelect,
  eventClick: handleEventClick,
  eventDrop: handleEventDrop,
  eventResize: handleEventResize,
  eventDidMount: handleEventDidMount,
})

const filteredEvents = computed(() => {
  return events.value.filter(event => {
    return selectedCalendars.value.includes(event.extendedProps?.calendar_id || event.calendar_id)
  })
})

onMounted(async () => {
  await loadCalendars()
  await loadEvents()
})

// Watch for calendar selection changes
watch(selectedCalendars, () => {
  // FullCalendar will automatically update due to computed events
}, { deep: true })

async function loadCalendars() {
  try {
    const response = await axios.get('/api/calendars')
    calendars.value = response.data.data || response.data
    
    // Select all calendars by default
    selectedCalendars.value = calendars.value.map(cal => cal.id)
  } catch (error) {
    console.error('Error loading calendars:', error)
  }
}

async function loadEvents() {
  try {
    const calendarApi = calendarRef.value?.getApi()
    if (!calendarApi) return

    const view = calendarApi.view
    const start = view.activeStart
    const end = view.activeEnd

    const response = await axios.get('/api/events', {
      params: {
        start_date: DateTime.fromJSDate(start).toISODate(),
        end_date: DateTime.fromJSDate(end).toISODate(),
        expand: true,
        timezone: DateTime.local().zoneName,
      }
    })

    const eventsData = response.data.data || response.data
    events.value = eventsData.map(event => ({
      id: event.id,
      title: event.title,
      start: event.start_at,
      end: event.end_at,
      allDay: event.all_day,
      backgroundColor: getCalendarColor(event.calendar_id),
      borderColor: getCalendarColor(event.calendar_id),
      textColor: getContrastColor(getCalendarColor(event.calendar_id)),
      extendedProps: {
        ...event,
        calendar_id: event.calendar_id,
      }
    }))
  } catch (error) {
    console.error('Error loading events:', error)
  }
}

function handleDateSelect(selectInfo) {
  selectedDate.value = selectInfo.startStr
  selectedEvent.value = null
  showEventModal.value = true
}

function handleEventClick(clickInfo) {
  selectedEvent.value = clickInfo.event.extendedProps
  showEventDetails.value = true
}

async function handleEventDrop(dropInfo) {
  const event = dropInfo.event
  const eventData = event.extendedProps

  try {
    await axios.put(`/api/events/${eventData.id}`, {
      start_at: event.start.toISOString(),
      end_at: event.end?.toISOString(),
      timezone: DateTime.local().zoneName,
    })
  } catch (error) {
    console.error('Error updating event:', error)
    dropInfo.revert()
  }
}

async function handleEventResize(resizeInfo) {
  const event = resizeInfo.event
  const eventData = event.extendedProps

  try {
    await axios.put(`/api/events/${eventData.id}`, {
      start_at: event.start.toISOString(),
      end_at: event.end?.toISOString(),
      timezone: DateTime.local().zoneName,
    })
  } catch (error) {
    console.error('Error updating event:', error)
    resizeInfo.revert()
  }
}

function handleEventDidMount(info) {
  // Add custom styling or tooltips here if needed
  const event = info.event
  if (event.extendedProps.is_private) {
    info.el.style.opacity = '0.7'
    info.el.title = 'Private Event'
  }
}

function toggleCalendar(calendarId) {
  const index = selectedCalendars.value.indexOf(calendarId)
  if (index > -1) {
    selectedCalendars.value.splice(index, 1)
  } else {
    selectedCalendars.value.push(calendarId)
  }
}

async function handleCalendarCreated(calendar) {
  calendars.value.push(calendar)
  selectedCalendars.value.push(calendar.id)
}

async function handleCalendarUpdated(calendar) {
  const index = calendars.value.findIndex(cal => cal.id === calendar.id)
  if (index > -1) {
    calendars.value[index] = calendar
  }
  await loadEvents() // Reload events to update colors
}

async function handleCalendarDeleted(calendarId) {
  try {
    await axios.delete(`/api/calendars/${calendarId}`)
    calendars.value = calendars.value.filter(cal => cal.id !== calendarId)
    selectedCalendars.value = selectedCalendars.value.filter(id => id !== calendarId)
    await loadEvents()
  } catch (error) {
    console.error('Error deleting calendar:', error)
  }
}

function closeEventModal() {
  showEventModal.value = false
  selectedEvent.value = null
  selectedDate.value = ''
}

function closeEventDetails() {
  showEventDetails.value = false
  selectedEvent.value = null
}

function editEvent() {
  showEventDetails.value = false
  showEventModal.value = true
}

async function deleteEvent() {
  if (!selectedEvent.value) return

  if (confirm(`Are you sure you want to delete "${selectedEvent.value.title}"?`)) {
    try {
      await axios.delete(`/api/events/${selectedEvent.value.id}`)
      await loadEvents()
      closeEventDetails()
    } catch (error) {
      console.error('Error deleting event:', error)
    }
  }
}

async function handleEventSaved() {
  await loadEvents()
}

function getCalendarColor(calendarId) {
  const calendar = calendars.value.find(cal => cal.id === calendarId)
  return calendar?.color || '#3B82F6'
}

function getCalendarName(calendarId) {
  const calendar = calendars.value.find(cal => cal.id === calendarId)
  return calendar?.name || 'Unknown Calendar'
}

function getContrastColor(hexColor) {
  // Convert hex to RGB
  const r = parseInt(hexColor.slice(1, 3), 16)
  const g = parseInt(hexColor.slice(3, 5), 16)
  const b = parseInt(hexColor.slice(5, 7), 16)
  
  // Calculate luminance
  const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255
  
  return luminance > 0.5 ? '#000000' : '#FFFFFF'
}

function formatEventDateTime(event) {
  const start = DateTime.fromISO(event.start_at)
  const end = event.end_at ? DateTime.fromISO(event.end_at) : null

  if (event.all_day) {
    if (end && !start.hasSame(end, 'day')) {
      return `${start.toFormat('MMM d')} - ${end.toFormat('MMM d, yyyy')}`
    }
    return start.toFormat('MMM d, yyyy')
  }

  const timeFormat = 'h:mm a'
  if (end && start.hasSame(end, 'day')) {
    return `${start.toFormat('MMM d, yyyy')} • ${start.toFormat(timeFormat)} - ${end.toFormat(timeFormat)}`
  } else if (end) {
    return `${start.toFormat('MMM d, yyyy h:mm a')} - ${end.toFormat('MMM d, yyyy h:mm a')}`
  }
  
  return start.toFormat('MMM d, yyyy h:mm a')
}

function formatMarkdown(text) {
  // Simple markdown formatting (you might want to use a proper markdown library)
  return text
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.*?)\*/g, '<em>$1</em>')
    .replace(/\n/g, '<br>')
}
</script>

<style scoped>
.calendar-container {
  width: 100%;
  min-height: 600px;
}

:deep(.fc-event) {
  cursor: pointer;
  border-radius: 4px;
}

:deep(.fc-event:hover) {
  opacity: 0.8;
}

:deep(.fc-daygrid-event) {
  margin: 1px;
}

:deep(.fc-timegrid-event) {
  border-radius: 3px;
}
</style>

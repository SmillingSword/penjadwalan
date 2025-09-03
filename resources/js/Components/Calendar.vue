<template>
  <div class="calendar-container">
    <FullCalendar
      ref="calendarRef"
      :options="calendarOptions"
      :events="events"
      @eventClick="handleEventClick"
      @dateClick="handleDateClick"
      @eventAdd="handleEventAdd"
      @eventChange="handleEventChange"
      @eventRemove="handleEventRemove"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import axios from 'axios';

const calendarRef = ref(null);
const events = ref([]);

const calendarOptions = {
  plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
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
  select: handleDateSelect,
  eventClick: handleEventClick,
  eventsSet: handleEventsSet,
  /* you can update a remote database when these fire:
  eventAdd:
  eventChange:
  eventRemove:
  */
};

function handleDateSelect(selectInfo) {
  let title = prompt('Please enter a new title for your event');
  let calendarApi = selectInfo.view.calendar;

  calendarApi.unselect(); // clear date selection

  if (title) {
    calendarApi.addEvent({
      id: createEventId(),
      title,
      start: selectInfo.startStr,
      end: selectInfo.endStr,
      allDay: selectInfo.allDay
    });
  }
}

function handleEventClick(clickInfo) {
  if (confirm(`Are you sure you want to delete the event '${clickInfo.event.title}'`)) {
    clickInfo.event.remove();
  }
}

function handleEventsSet(events) {
  // This is called when events are set or changed
  console.log('Events set:', events);
}

function createEventId() {
  return String(Date.now());
}

onMounted(() => {
  // Load events from backend if needed
  loadEvents();
});

function loadEvents() {
  // Fetch events from Laravel backend
  axios.get('/api/events')
    .then(response => {
      events.value = response.data;
    })
    .catch(error => {
      console.error('Error loading events:', error);
    });
}

function handleEventAdd(addInfo) {
  // Save new event to backend
  axios.post('/api/events', {
    title: addInfo.event.title,
    start: addInfo.event.start,
    end: addInfo.event.end,
    allDay: addInfo.event.allDay
  })
  .then(response => {
    console.log('Event added:', response.data);
  })
  .catch(error => {
    console.error('Error adding event:', error);
    addInfo.revert();
  });
}

function handleEventChange(changeInfo) {
  // Update event in backend
  axios.put(`/api/events/${changeInfo.event.id}`, {
    title: changeInfo.event.title,
    start: changeInfo.event.start,
    end: changeInfo.event.end,
    allDay: changeInfo.event.allDay
  })
  .then(response => {
    console.log('Event updated:', response.data);
  })
  .catch(error => {
    console.error('Error updating event:', error);
    changeInfo.revert();
  });
}

function handleEventRemove(removeInfo) {
  // Delete event from backend
  axios.delete(`/api/events/${removeInfo.event.id}`)
  .then(response => {
    console.log('Event deleted:', response.data);
  })
  .catch(error => {
    console.error('Error deleting event:', error);
    removeInfo.revert();
  });
}
</script>

<style scoped>
.calendar-container {
  width: 100%;
  height: 600px;
}
</style>

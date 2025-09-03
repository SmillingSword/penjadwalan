<template>
  <div class="bg-white rounded-lg shadow p-4">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-medium text-gray-900">My Calendars</h3>
      <button
        @click="showCreateModal = true"
        class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
      >
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        New
      </button>
    </div>

    <div class="space-y-2">
      <div
        v-for="calendar in calendars"
        :key="calendar.id"
        class="flex items-center justify-between p-2 rounded-md hover:bg-gray-50"
      >
        <div class="flex items-center space-x-3">
          <div class="flex items-center">
            <input
              :id="`calendar-${calendar.id}`"
              type="checkbox"
              :checked="selectedCalendars.includes(calendar.id)"
              @change="toggleCalendar(calendar.id)"
              class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
            />
            <div
              class="w-4 h-4 rounded ml-2"
              :style="{ backgroundColor: calendar.color }"
            ></div>
          </div>
          <label
            :for="`calendar-${calendar.id}`"
            class="text-sm font-medium text-gray-700 cursor-pointer"
          >
            {{ calendar.name }}
          </label>
        </div>

        <div class="flex items-center space-x-1">
          <button
            @click="editCalendar(calendar)"
            class="p-1 text-gray-400 hover:text-gray-600"
            title="Edit calendar"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
            </svg>
          </button>
          <button
            @click="deleteCalendar(calendar)"
            class="p-1 text-gray-400 hover:text-red-600"
            title="Delete calendar"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Calendar Modal -->
    <Modal :show="showCreateModal || showEditModal" @close="closeCalendarModal" max-width="md">
      <div class="p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-medium text-gray-900">
            {{ editingCalendar ? 'Edit Calendar' : 'Create Calendar' }}
          </h2>
          <button @click="closeCalendarModal" class="text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>

        <form @submit.prevent="saveCalendar" class="space-y-4">
          <div>
            <InputLabel for="calendar_name" value="Calendar Name" />
            <TextInput
              id="calendar_name"
              v-model="calendarForm.name"
              type="text"
              class="mt-1 block w-full"
              required
              autofocus
            />
            <InputError class="mt-2" :message="calendarErrors.name" />
          </div>

          <div>
            <InputLabel for="calendar_description" value="Description" />
            <textarea
              id="calendar_description"
              v-model="calendarForm.description"
              rows="3"
              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            ></textarea>
            <InputError class="mt-2" :message="calendarErrors.description" />
          </div>

          <div>
            <InputLabel for="calendar_color" value="Color" />
            <div class="mt-1 flex items-center space-x-2">
              <input
                id="calendar_color"
                v-model="calendarForm.color"
                type="color"
                class="h-10 w-20 border border-gray-300 rounded-md"
              />
              <TextInput
                v-model="calendarForm.color"
                type="text"
                class="flex-1"
                placeholder="#3B82F6"
              />
            </div>
            <InputError class="mt-2" :message="calendarErrors.color" />
          </div>

          <div class="flex items-center">
            <Checkbox
              id="calendar_is_public"
              v-model:checked="calendarForm.is_public"
              name="is_public"
            />
            <InputLabel for="calendar_is_public" value="Public Calendar" class="ml-2" />
          </div>

          <div class="flex items-center justify-end pt-4 space-x-2">
            <SecondaryButton @click="closeCalendarModal">
              Cancel
            </SecondaryButton>
            <PrimaryButton :class="{ 'opacity-25': calendarProcessing }" :disabled="calendarProcessing">
              {{ editingCalendar ? 'Update Calendar' : 'Create Calendar' }}
            </PrimaryButton>
          </div>
        </form>
      </div>
    </Modal>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import Modal from '@/Components/Modal.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import InputError from '@/Components/InputError.vue'
import Checkbox from '@/Components/Checkbox.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'

const props = defineProps({
  calendars: Array,
  selectedCalendars: Array,
})

const emit = defineEmits(['calendar-toggled', 'calendar-created', 'calendar-updated', 'calendar-deleted'])

const showCreateModal = ref(false)
const showEditModal = ref(false)
const editingCalendar = ref(null)
const calendarProcessing = ref(false)
const calendarErrors = ref({})

const calendarForm = reactive({
  name: '',
  description: '',
  color: '#3B82F6',
  is_public: false,
})

function toggleCalendar(calendarId) {
  emit('calendar-toggled', calendarId)
}

function editCalendar(calendar) {
  editingCalendar.value = calendar
  Object.assign(calendarForm, {
    name: calendar.name,
    description: calendar.description || '',
    color: calendar.color,
    is_public: calendar.is_public || false,
  })
  showEditModal.value = true
}

function deleteCalendar(calendar) {
  if (confirm(`Are you sure you want to delete "${calendar.name}"? This will also delete all events in this calendar.`)) {
    emit('calendar-deleted', calendar.id)
  }
}

function closeCalendarModal() {
  showCreateModal.value = false
  showEditModal.value = false
  editingCalendar.value = null
  resetCalendarForm()
}

function resetCalendarForm() {
  Object.assign(calendarForm, {
    name: '',
    description: '',
    color: '#3B82F6',
    is_public: false,
  })
  calendarErrors.value = {}
}

async function saveCalendar() {
  calendarProcessing.value = true
  calendarErrors.value = {}

  try {
    const url = editingCalendar.value 
      ? `/api/calendars/${editingCalendar.value.id}` 
      : '/api/calendars'
    const method = editingCalendar.value ? 'PUT' : 'POST'

    const response = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
      },
      body: JSON.stringify(calendarForm),
    })

    if (response.ok) {
      const data = await response.json()
      if (editingCalendar.value) {
        emit('calendar-updated', data)
      } else {
        emit('calendar-created', data)
      }
      closeCalendarModal()
    } else {
      const errorData = await response.json()
      if (errorData.errors) {
        calendarErrors.value = errorData.errors
      }
    }
  } catch (error) {
    console.error('Error saving calendar:', error)
  } finally {
    calendarProcessing.value = false
  }
}
</script>

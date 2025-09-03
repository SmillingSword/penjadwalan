<template>
  <Modal :show="show" @close="closeModal" max-width="2xl">
    <div class="p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-medium text-gray-900">
          {{ isEditing ? 'Edit Event' : 'Create Event' }}
        </h2>
        <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <form @submit.prevent="submitForm" class="space-y-4">
        <!-- Calendar Selection -->
        <div>
          <InputLabel for="calendar_id" value="Calendar" />
          <select
            id="calendar_id"
            v-model="form.calendar_id"
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            required
          >
            <option value="">Select a calendar</option>
            <option
              v-for="calendar in calendars"
              :key="calendar.id"
              :value="calendar.id"
            >
              {{ calendar.name }}
            </option>
          </select>
          <InputError class="mt-2" :message="errors.calendar_id" />
        </div>

        <!-- Title -->
        <div>
          <InputLabel for="title" value="Title" />
          <TextInput
            id="title"
            v-model="form.title"
            type="text"
            class="mt-1 block w-full"
            required
            autofocus
          />
          <InputError class="mt-2" :message="errors.title" />
        </div>

        <!-- Description -->
        <div>
          <InputLabel for="description_md" value="Description" />
          <textarea
            id="description_md"
            v-model="form.description_md"
            rows="3"
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
          ></textarea>
          <InputError class="mt-2" :message="errors.description_md" />
        </div>

        <!-- Date and Time -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <InputLabel for="start_at" value="Start Date & Time" />
            <TextInput
              id="start_at"
              v-model="form.start_at"
              type="datetime-local"
              class="mt-1 block w-full"
              required
            />
            <InputError class="mt-2" :message="errors.start_at" />
          </div>

          <div>
            <InputLabel for="end_at" value="End Date & Time" />
            <TextInput
              id="end_at"
              v-model="form.end_at"
              type="datetime-local"
              class="mt-1 block w-full"
            />
            <InputError class="mt-2" :message="errors.end_at" />
          </div>
        </div>

        <!-- All Day Toggle -->
        <div class="flex items-center">
          <Checkbox
            id="all_day"
            v-model:checked="form.all_day"
            name="all_day"
          />
          <InputLabel for="all_day" value="All Day" class="ml-2" />
        </div>

        <!-- Location -->
        <div>
          <InputLabel for="location" value="Location" />
          <TextInput
            id="location"
            v-model="form.location"
            type="text"
            class="mt-1 block w-full"
          />
          <InputError class="mt-2" :message="errors.location" />
        </div>

        <!-- Meeting Link -->
        <div>
          <InputLabel for="meeting_link" value="Meeting Link" />
          <TextInput
            id="meeting_link"
            v-model="form.meeting_link"
            type="url"
            class="mt-1 block w-full"
          />
          <InputError class="mt-2" :message="errors.meeting_link" />
        </div>

        <!-- Timezone -->
        <div>
          <InputLabel for="timezone" value="Timezone" />
          <select
            id="timezone"
            v-model="form.timezone"
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
          >
            <option value="Asia/Jakarta">Asia/Jakarta</option>
            <option value="UTC">UTC</option>
            <option value="America/New_York">America/New_York</option>
            <option value="Europe/London">Europe/London</option>
            <option value="Asia/Tokyo">Asia/Tokyo</option>
          </select>
          <InputError class="mt-2" :message="errors.timezone" />
        </div>

        <!-- Recurrence -->
        <div>
          <InputLabel for="recurrence_type" value="Repeat" />
          <select
            id="recurrence_type"
            v-model="recurrenceType"
            @change="updateRRule"
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
          >
            <option value="">Does not repeat</option>
            <option value="daily">Daily</option>
            <option value="weekly">Weekly</option>
            <option value="monthly">Monthly</option>
            <option value="yearly">Yearly</option>
            <option value="custom">Custom</option>
          </select>
        </div>

        <!-- Custom RRULE input -->
        <div v-if="recurrenceType === 'custom'">
          <InputLabel for="rrule" value="Custom Recurrence Rule (RRULE)" />
          <TextInput
            id="rrule"
            v-model="form.rrule"
            type="text"
            class="mt-1 block w-full"
            placeholder="FREQ=WEEKLY;BYDAY=MO,WE,FR"
          />
          <InputError class="mt-2" :message="errors.rrule" />
          <p class="mt-1 text-sm text-gray-500">
            Enter a valid RRULE string (RFC 5545 format)
          </p>
        </div>

        <!-- Privacy -->
        <div class="flex items-center">
          <Checkbox
            id="is_private"
            v-model:checked="form.is_private"
            name="is_private"
          />
          <InputLabel for="is_private" value="Private Event" class="ml-2" />
        </div>

        <!-- Form Actions -->
        <div class="flex items-center justify-end pt-4 space-x-2">
          <SecondaryButton @click="closeModal">
            Cancel
          </SecondaryButton>
          <PrimaryButton :class="{ 'opacity-25': processing }" :disabled="processing">
            {{ isEditing ? 'Update Event' : 'Create Event' }}
          </PrimaryButton>
        </div>
      </form>
    </div>
  </Modal>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import InputError from '@/Components/InputError.vue'
import Checkbox from '@/Components/Checkbox.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import { DateTime } from 'luxon'

const props = defineProps({
  show: Boolean,
  event: Object,
  calendars: Array,
  selectedDate: String,
})

const emit = defineEmits(['close', 'saved'])

const processing = ref(false)
const errors = ref({})
const recurrenceType = ref('')

const isEditing = computed(() => !!props.event?.id)

const form = reactive({
  calendar_id: '',
  title: '',
  description_md: '',
  location: '',
  meeting_link: '',
  start_at: '',
  end_at: '',
  all_day: false,
  timezone: 'Asia/Jakarta',
  rrule: '',
  is_private: false,
})

// Watch for event changes to populate form
watch(() => props.event, (newEvent) => {
  if (newEvent) {
    Object.assign(form, {
      calendar_id: newEvent.calendar_id || '',
      title: newEvent.title || '',
      description_md: newEvent.description_md || '',
      location: newEvent.location || '',
      meeting_link: newEvent.meeting_link || '',
      start_at: newEvent.start_at ? formatDateTimeLocal(newEvent.start_at) : '',
      end_at: newEvent.end_at ? formatDateTimeLocal(newEvent.end_at) : '',
      all_day: newEvent.all_day || false,
      timezone: newEvent.timezone || 'Asia/Jakarta',
      rrule: newEvent.rrule || '',
      is_private: newEvent.is_private || false,
    })
    
    // Set recurrence type based on RRULE
    if (newEvent.rrule) {
      if (newEvent.rrule.includes('FREQ=DAILY')) recurrenceType.value = 'daily'
      else if (newEvent.rrule.includes('FREQ=WEEKLY')) recurrenceType.value = 'weekly'
      else if (newEvent.rrule.includes('FREQ=MONTHLY')) recurrenceType.value = 'monthly'
      else if (newEvent.rrule.includes('FREQ=YEARLY')) recurrenceType.value = 'yearly'
      else recurrenceType.value = 'custom'
    }
  } else {
    resetForm()
  }
}, { immediate: true })

// Watch for selected date to set default start time
watch(() => props.selectedDate, (newDate) => {
  if (newDate && !isEditing.value) {
    const dt = DateTime.fromISO(newDate).set({ hour: 9, minute: 0 })
    form.start_at = dt.toFormat("yyyy-MM-dd'T'HH:mm")
    form.end_at = dt.plus({ hours: 1 }).toFormat("yyyy-MM-dd'T'HH:mm")
  }
})

function formatDateTimeLocal(dateString) {
  return DateTime.fromISO(dateString).toFormat("yyyy-MM-dd'T'HH:mm")
}

function resetForm() {
  Object.assign(form, {
    calendar_id: '',
    title: '',
    description_md: '',
    location: '',
    meeting_link: '',
    start_at: '',
    end_at: '',
    all_day: false,
    timezone: 'Asia/Jakarta',
    rrule: '',
    is_private: false,
  })
  recurrenceType.value = ''
  errors.value = {}
}

function updateRRule() {
  if (!recurrenceType.value || recurrenceType.value === 'custom') {
    if (recurrenceType.value !== 'custom') {
      form.rrule = ''
    }
    return
  }

  const ruleMap = {
    daily: 'FREQ=DAILY',
    weekly: 'FREQ=WEEKLY',
    monthly: 'FREQ=MONTHLY',
    yearly: 'FREQ=YEARLY',
  }

  form.rrule = ruleMap[recurrenceType.value] || ''
}

async function submitForm() {
  processing.value = true
  errors.value = {}

  try {
    const url = isEditing.value ? `/api/events/${props.event.id}` : '/api/events'
    const method = isEditing.value ? 'PUT' : 'POST'

    const response = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
      },
      body: JSON.stringify(form),
    })

    if (response.ok) {
      const data = await response.json()
      emit('saved', data)
      closeModal()
    } else {
      const errorData = await response.json()
      if (errorData.errors) {
        errors.value = errorData.errors
      }
    }
  } catch (error) {
    console.error('Error saving event:', error)
  } finally {
    processing.value = false
  }
}

function closeModal() {
  emit('close')
  resetForm()
}
</script>

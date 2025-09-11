<template>
    <div class="push-notification-manager">
        <!-- Push Notification Status -->
        <div v-if="showStatus" class="mb-4 p-4 rounded-lg" :class="statusClass">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <component :is="statusIcon" class="h-5 w-5" />
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium">{{ statusTitle }}</h3>
                    <p class="text-sm mt-1">{{ statusMessage }}</p>
                </div>
                <div v-if="canEnable" class="ml-auto">
                    <button
                        @click="enableNotifications"
                        :disabled="isLoading"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium disabled:opacity-50"
                    >
                        {{ isLoading ? 'Enabling...' : 'Enable Notifications' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Test Notification Button (Development) -->
        <div v-if="isEnabled && isDevelopment" class="mb-4">
            <button
                @click="sendTestNotification"
                :disabled="isLoading"
                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium disabled:opacity-50"
            >
                {{ isLoading ? 'Sending...' : 'Send Test Notification' }}
            </button>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { CheckCircleIcon, ExclamationTriangleIcon, XCircleIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

export default {
    name: 'PushNotificationManager',
    components: {
        CheckCircleIcon,
        ExclamationTriangleIcon,
        XCircleIcon,
    },
    props: {
        showStatus: {
            type: Boolean,
            default: true
        },
        isDevelopment: {
            type: Boolean,
            default: import.meta.env.DEV
        }
    },
    emits: ['notification-enabled', 'notification-disabled', 'notification-received'],
    setup(props, { emit }) {
        const isSupported = ref(false)
        const isEnabled = ref(false)
        const permission = ref('default')
        const isLoading = ref(false)
        const beamsClient = ref(null)
        const config = ref(null)
        const error = ref(null)

        // Computed properties
        const canEnable = computed(() => {
            return isSupported.value && permission.value !== 'granted' && permission.value !== 'denied'
        })

        const statusClass = computed(() => {
            if (isEnabled.value) return 'bg-green-50 border border-green-200 text-green-800'
            if (permission.value === 'denied') return 'bg-red-50 border border-red-200 text-red-800'
            return 'bg-yellow-50 border border-yellow-200 text-yellow-800'
        })

        const statusIcon = computed(() => {
            if (isEnabled.value) return 'CheckCircleIcon'
            if (permission.value === 'denied') return 'XCircleIcon'
            return 'ExclamationTriangleIcon'
        })

        const statusTitle = computed(() => {
            if (isEnabled.value) return 'Push Notifications Enabled'
            if (permission.value === 'denied') return 'Push Notifications Blocked'
            if (!isSupported.value) return 'Push Notifications Not Supported'
            return 'Push Notifications Available'
        })

        const statusMessage = computed(() => {
            if (isEnabled.value) return 'You will receive push notifications for important updates.'
            if (permission.value === 'denied') return 'Push notifications are blocked. Please enable them in your browser settings.'
            if (!isSupported.value) return 'Your browser does not support push notifications.'
            return 'Enable push notifications to receive important updates even when the app is closed.'
        })

        // Methods
        const checkSupport = () => {
            isSupported.value = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window
            if (isSupported.value) {
                permission.value = Notification.permission
            }
        }

        const loadConfig = async () => {
            try {
                const response = await axios.get('/api/push-notifications/config')
                config.value = response.data
                return true
            } catch (err) {
                console.error('Failed to load push notification config:', err)
                error.value = 'Failed to load configuration'
                return false
            }
        }

        const initializeBeams = async () => {
            if (!config.value || !isSupported.value) return false

            try {
                // Import Pusher Beams SDK dynamically
                const PusherPushNotifications = await import('@pusher/push-notifications-web')
                
                beamsClient.value = new PusherPushNotifications.Client({
                    instanceId: config.value.instance_id,
                    serviceWorkerRegistration: await navigator.serviceWorker.register(config.value.service_worker_url)
                })

                return true
            } catch (err) {
                console.error('Failed to initialize Pusher Beams:', err)
                error.value = 'Failed to initialize push notifications'
                return false
            }
        }

        const enableNotifications = async () => {
            if (!isSupported.value || isLoading.value) return

            isLoading.value = true
            try {
                // Load config if not already loaded
                if (!config.value) {
                    const configLoaded = await loadConfig()
                    if (!configLoaded) return
                }

                // Initialize Beams if not already initialized
                if (!beamsClient.value) {
                    const beamsInitialized = await initializeBeams()
                    if (!beamsInitialized) return
                }

                // Start Beams client
                await beamsClient.value.start()

                // Get user token for authenticated notifications
                try {
                    const tokenResponse = await axios.get('/api/push-notifications/user-token')
                    await beamsClient.value.setUserId(tokenResponse.data.user_id, tokenResponse.data.token)
                } catch (err) {
                    console.warn('Failed to set user ID, using device-only notifications:', err)
                }

                // Subscribe to device interest
                await beamsClient.value.addDeviceInterest('hello')

                permission.value = 'granted'
                isEnabled.value = true
                emit('notification-enabled')

                console.log('Push notifications enabled successfully')
            } catch (err) {
                console.error('Failed to enable push notifications:', err)
                error.value = 'Failed to enable notifications'
                
                if (err.message && err.message.includes('denied')) {
                    permission.value = 'denied'
                }
            } finally {
                isLoading.value = false
            }
        }

        const sendTestNotification = async () => {
            if (!isEnabled.value || isLoading.value) return

            isLoading.value = true
            try {
                await axios.post('/api/push-notifications/test')
                console.log('Test notification sent')
            } catch (err) {
                console.error('Failed to send test notification:', err)
                error.value = 'Failed to send test notification'
            } finally {
                isLoading.value = false
            }
        }

        const handleNotificationClick = (event) => {
            console.log('Notification clicked:', event)
            emit('notification-received', event.detail)
        }

        // Lifecycle
        onMounted(async () => {
            checkSupport()
            
            if (isSupported.value) {
                // Check if already enabled
                if (permission.value === 'granted') {
                    const configLoaded = await loadConfig()
                    if (configLoaded) {
                        const beamsInitialized = await initializeBeams()
                        if (beamsInitialized) {
                            try {
                                await beamsClient.value.start()
                                isEnabled.value = true
                            } catch (err) {
                                console.warn('Failed to start existing Beams client:', err)
                            }
                        }
                    }
                }

                // Listen for notification events
                window.addEventListener('pusher:notification_clicked', handleNotificationClick)
            }
        })

        onUnmounted(() => {
            if (isSupported.value) {
                window.removeEventListener('pusher:notification_clicked', handleNotificationClick)
            }
        })

        return {
            isSupported,
            isEnabled,
            permission,
            isLoading,
            error,
            canEnable,
            statusClass,
            statusIcon,
            statusTitle,
            statusMessage,
            enableNotifications,
            sendTestNotification
        }
    }
}
</script>

<style scoped>
.push-notification-manager {
    /* Component-specific styles */
}
</style>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import Toast from '@/Components/Toast.vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    user: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        default: () => ({}),
    },
});

// Reactive data for animations
const isLoaded = ref(false)
const activeTab = ref('profile')
const avatarPreview = ref(null)
const avatarFile = ref(null)
const isUploadingAvatar = ref(false)

// Check for tab parameter from URL or props
const urlParams = new URLSearchParams(window.location.search)
const tabParam = urlParams.get('tab')
if (tabParam && ['profile', 'security', 'preferences', 'danger'].includes(tabParam)) {
    activeTab.value = tabParam
}

// User data from props
const user = computed(() => props.user)

const userInitials = computed(() => {
    return user.value.name
        .split(' ')
        .map(name => name.charAt(0))
        .join('')
        .toUpperCase()
        .slice(0, 2)
})

const userAvatar = computed(() => {
    if (avatarPreview.value) {
        return avatarPreview.value
    }
    
    if (user.value.avatar) {
        // If it's a Google avatar (starts with http), use it directly
        if (user.value.avatar.startsWith('http')) {
            return user.value.avatar
        }
        // Otherwise, it's a local file in storage
        return `/storage/${user.value.avatar}`
    }
    
    return null
})

const isGoogleUser = computed(() => {
    return user.value.google_id !== null
})

const tabs = [
    { id: 'profile', name: 'Profile Info', icon: 'user' },
    { id: 'security', name: 'Security', icon: 'shield' },
    { id: 'preferences', name: 'Preferences', icon: 'settings' },
    { id: 'danger', name: 'Danger Zone', icon: 'warning' }
]

// Avatar upload methods
const handleAvatarChange = (event) => {
    const file = event.target.files[0]
    if (file) {
        avatarFile.value = file
        
        // Create preview
        const reader = new FileReader()
        reader.onload = (e) => {
            avatarPreview.value = e.target.result
        }
        reader.readAsDataURL(file)
    }
}

const uploadAvatar = async () => {
    if (!avatarFile.value) return
    
    isUploadingAvatar.value = true
    
    const formData = new FormData()
    formData.append('avatar', avatarFile.value)
    
    try {
        await router.post('/profile/avatar', formData, {
            preserveScroll: true,
            onSuccess: () => {
                avatarPreview.value = null
                avatarFile.value = null
                if (window.toast) {
                    window.toast.success('Success!', 'Profile photo updated successfully.')
                }
            },
            onError: (errors) => {
                if (window.toast) {
                    const errorMessage = errors.avatar ? errors.avatar[0] : 'Failed to upload avatar.'
                    window.toast.error('Upload Failed', errorMessage)
                }
            }
        })
    } catch (error) {
        console.error('Avatar upload error:', error)
        if (window.toast) {
            window.toast.error('Upload Failed', 'An error occurred while uploading your avatar.')
        }
    } finally {
        isUploadingAvatar.value = false
    }
}

const removeAvatar = async () => {
    if (isGoogleUser.value) {
        if (window.toast) {
            window.toast.info('Info', 'Google profile photos cannot be removed. You can upload a custom photo to replace it.')
        }
        return
    }
    
    try {
        await router.delete('/profile/avatar', {
            preserveScroll: true,
            onSuccess: () => {
                avatarPreview.value = null
                avatarFile.value = null
                if (window.toast) {
                    window.toast.success('Success!', 'Profile photo removed successfully.')
                }
            }
        })
    } catch (error) {
        console.error('Avatar removal error:', error)
        if (window.toast) {
            window.toast.error('Removal Failed', 'An error occurred while removing your avatar.')
        }
    }
}

const cancelAvatarPreview = () => {
    avatarPreview.value = null
    avatarFile.value = null
}

onMounted(() => {
    setTimeout(() => {
        isLoaded.value = true
    }, 100)
})
</script>

<template>
    <Head title="Profile Settings" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-purple-50">
            <!-- Header Section -->
            <div class="bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 relative overflow-hidden">
                <!-- Background Pattern -->
                <div class="absolute inset-0 bg-black/10"></div>
                <div class="absolute inset-0 bg-gradient-to-r from-indigo-600/90 to-purple-600/90"></div>
                
                <!-- Floating Elements -->
                <div class="absolute top-10 left-10 w-20 h-20 bg-white/10 rounded-full animate-float"></div>
                <div class="absolute top-32 right-20 w-16 h-16 bg-white/10 rounded-full animate-float-delayed"></div>
                <div class="absolute bottom-10 left-1/3 w-12 h-12 bg-white/10 rounded-full animate-float"></div>

                <div class="relative px-6 py-16">
                    <div class="max-w-7xl mx-auto">
                        <div class="flex items-center space-x-6">
                            <!-- Avatar -->
                            <div class="relative group">
                                <!-- Avatar Display -->
                                <div class="w-24 h-24 rounded-2xl overflow-hidden shadow-2xl group-hover:shadow-3xl transition-all duration-300 group-hover:scale-105">
                                    <img v-if="userAvatar" 
                                         :src="userAvatar" 
                                         :alt="user.name"
                                         class="w-full h-full object-cover"
                                    />
                                    <div v-else class="w-full h-full bg-gradient-to-r from-pink-500 to-purple-500 flex items-center justify-center">
                                        <span class="text-white text-2xl font-bold">{{ userInitials }}</span>
                                    </div>
                                </div>
                                
                                <!-- Avatar Upload Button -->
                                <div class="absolute -bottom-2 -right-2">
                                    <input 
                                        type="file" 
                                        ref="avatarInput"
                                        @change="handleAvatarChange"
                                        accept="image/*"
                                        class="hidden"
                                    />
                                    <button 
                                        @click="$refs.avatarInput.click()"
                                        class="w-8 h-8 bg-blue-500 hover:bg-blue-600 rounded-full border-4 border-white flex items-center justify-center transition-colors"
                                        title="Change profile photo"
                                    >
                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                    </button>
                                </div>
                                
                                <!-- Google Badge -->
                                <div v-if="isGoogleUser && !avatarPreview" class="absolute -top-2 -left-2 w-6 h-6 bg-white rounded-full flex items-center justify-center shadow-lg">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24">
                                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                                    </svg>
                                </div>
                            </div>

                            <!-- User Info -->
                            <div class="flex-1">
                                <h1 class="text-4xl font-bold text-white mb-2">{{ user.name }}</h1>
                                <p class="text-indigo-100 text-lg mb-4">{{ user.email }}</p>
                                <div class="flex items-center space-x-6 text-indigo-200">
                                    <div class="flex items-center space-x-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9a2 2 0 012-2h3z"></path>
                                        </svg>
                                        <span>Joined {{ new Date(user.created_at).toLocaleDateString() }}</span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                                        <span>Online</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Stats -->
                            <div class="hidden lg:flex space-x-6">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-white">{{ stats.eventsCount || 0 }}</div>
                                    <div class="text-indigo-200 text-sm">Events</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-white">{{ stats.calendarsCount || 0 }}</div>
                                    <div class="text-indigo-200 text-sm">Calendars</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-white">{{ user.email_verified_at ? '✓' : '?' }}</div>
                                    <div class="text-indigo-200 text-sm">Verified</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="max-w-7xl mx-auto px-6 py-8">
                <div class="flex flex-col lg:flex-row gap-8">
                    <!-- Sidebar Navigation -->
                    <div class="lg:w-80">
                        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden sticky top-8">
                            <div class="p-6 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900">Settings</h3>
                                <p class="text-sm text-gray-600 mt-1">Manage your account preferences</p>
                            </div>
                            
                            <nav class="p-2">
                                <button
                                    v-for="tab in tabs"
                                    :key="tab.id"
                                    @click="activeTab = tab.id"
                                    class="w-full flex items-center space-x-3 px-4 py-3 rounded-xl text-left transition-all duration-200 group"
                                    :class="activeTab === tab.id 
                                        ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-lg' 
                                        : 'text-gray-700 hover:bg-gray-50 hover:text-indigo-600'"
                                >
                                    <!-- Icons for each tab -->
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors"
                                         :class="activeTab === tab.id ? 'bg-white/20' : 'bg-gray-100 group-hover:bg-indigo-100'">
                                        <svg v-if="tab.icon === 'user'" class="w-4 h-4" :class="activeTab === tab.id ? 'text-white' : 'text-gray-600 group-hover:text-indigo-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                        <svg v-else-if="tab.icon === 'shield'" class="w-4 h-4" :class="activeTab === tab.id ? 'text-white' : 'text-gray-600 group-hover:text-indigo-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                        </svg>
                                        <svg v-else-if="tab.icon === 'settings'" class="w-4 h-4" :class="activeTab === tab.id ? 'text-white' : 'text-gray-600 group-hover:text-indigo-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        <svg v-else-if="tab.icon === 'warning'" class="w-4 h-4" :class="activeTab === tab.id ? 'text-white' : 'text-red-600 group-hover:text-red-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-medium">{{ tab.name }}</div>
                                    </div>
                                    <svg v-if="activeTab === tab.id" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </button>
                            </nav>
                        </div>
                    </div>

                    <!-- Content Area -->
                    <div class="flex-1">
                        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                            <!-- Profile Information Tab -->
                            <div v-if="activeTab === 'profile'" class="p-8">
                                <div class="mb-8">
                                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Profile Information</h2>
                                    <p class="text-gray-600">Update your account's profile information and email address.</p>
                                </div>

                                <!-- Avatar Upload Section -->
                                <div v-if="avatarPreview" class="mb-8 p-6 bg-blue-50 border border-blue-200 rounded-xl">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-16 h-16 rounded-xl overflow-hidden">
                                            <img :src="avatarPreview" alt="Preview" class="w-full h-full object-cover" />
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="font-semibold text-gray-900">New Profile Photo</h3>
                                            <p class="text-sm text-gray-600">Ready to upload your new profile photo?</p>
                                        </div>
                                        <div class="flex space-x-2">
                                            <button 
                                                @click="uploadAvatar"
                                                :disabled="isUploadingAvatar"
                                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                            >
                                                <span v-if="isUploadingAvatar">Uploading...</span>
                                                <span v-else>Upload</span>
                                            </button>
                                            <button 
                                                @click="cancelAvatarPreview"
                                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors"
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Current Avatar Management -->
                                <div v-if="userAvatar && !avatarPreview" class="mb-8 p-6 bg-gray-50 border border-gray-200 rounded-xl">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-16 h-16 rounded-xl overflow-hidden">
                                            <img :src="userAvatar" :alt="user.name" class="w-full h-full object-cover" />
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="font-semibold text-gray-900">Current Profile Photo</h3>
                                            <p class="text-sm text-gray-600">
                                                <span v-if="isGoogleUser">From Google Account</span>
                                                <span v-else>Custom uploaded photo</span>
                                            </p>
                                        </div>
                                        <div class="flex space-x-2">
                                            <button 
                                                @click="$refs.avatarInput.click()"
                                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                                            >
                                                Change Photo
                                            </button>
                                            <button 
                                                v-if="!isGoogleUser"
                                                @click="removeAvatar"
                                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
                                            >
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <UpdateProfileInformationForm
                                    :must-verify-email="mustVerifyEmail"
                                    :status="status"
                                    :user="user"
                                    class="max-w-2xl"
                                />
                            </div>

                            <!-- Security Tab -->
                            <div v-if="activeTab === 'security'" class="p-8">
                                <div class="mb-8">
                                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Security Settings</h2>
                                    <p class="text-gray-600">Ensure your account is using a long, random password to stay secure.</p>
                                </div>
                                
                                <UpdatePasswordForm class="max-w-2xl" />
                            </div>

                            <!-- Preferences Tab -->
                            <div v-if="activeTab === 'preferences'" class="p-8">
                                <div class="mb-8">
                                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Preferences</h2>
                                    <p class="text-gray-600">Customize your experience and notification settings.</p>
                                </div>
                                
                                <!-- Preferences content -->
                                <div class="space-y-6 max-w-2xl">
                                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-6 border border-blue-100">
                                        <div class="flex items-center space-x-3 mb-4">
                                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            </div>
                                            <h3 class="text-lg font-semibold text-gray-900">Timezone & Language</h3>
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Timezone</label>
                                                <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                                    <option>Asia/Jakarta</option>
                                                    <option>Asia/Singapore</option>
                                                    <option>UTC</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Language</label>
                                                <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                                    <option>English</option>
                                                    <option>Bahasa Indonesia</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl p-6 border border-green-100">
                                        <div class="flex items-center space-x-3 mb-4">
                                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zM4 19h5v-5H4v5zM13 3H4v5h9V3z"></path>
                                                </svg>
                                            </div>
                                            <h3 class="text-lg font-semibold text-gray-900">Notifications</h3>
                                        </div>
                                        <div class="space-y-4">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <p class="font-medium text-gray-900">Email Notifications</p>
                                                    <p class="text-sm text-gray-600">Receive email updates about your events</p>
                                                </div>
                                                <label class="relative inline-flex items-center cursor-pointer">
                                                    <input type="checkbox" class="sr-only peer" checked>
                                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                                </label>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <p class="font-medium text-gray-900">Push Notifications</p>
                                                    <p class="text-sm text-gray-600">Receive push notifications in your browser</p>
                                                </div>
                                                <label class="relative inline-flex items-center cursor-pointer">
                                                    <input type="checkbox" class="sr-only peer">
                                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Danger Zone Tab -->
                            <div v-if="activeTab === 'danger'" class="p-8">
                                <div class="mb-8">
                                    <h2 class="text-2xl font-bold text-red-600 mb-2">Danger Zone</h2>
                                    <p class="text-gray-600">Permanently delete your account and all associated data.</p>
                                </div>
                                
                                <DeleteUserForm class="max-w-2xl" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Toast Notifications -->
            <Toast />
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(180deg); }
}

@keyframes float-delayed {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-15px) rotate(-180deg); }
}

.animate-float {
    animation: float 6s ease-in-out infinite;
}

.animate-float-delayed {
    animation: float-delayed 8s ease-in-out infinite;
}
</style>

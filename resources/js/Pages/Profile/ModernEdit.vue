<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import Toast from '@/Components/Toast.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

// Reactive data for animations
const isLoaded = ref(false)
const activeTab = ref('profile')

// Check for tab parameter from URL or props
const urlParams = new URLSearchParams(window.location.search)
const tabParam = urlParams.get('tab')
if (tabParam && ['profile', 'security', 'preferences', 'danger'].includes(tabParam)) {
    activeTab.value = tabParam
}

// User data (you can get this from props or page props)
const user = computed(() => {
    return {
        name: 'Rey',
        email: 'kacaribureynaldi@gmail.com',
        avatar: null,
        joinedDate: '2024-01-15',
        lastLogin: '2024-09-03 18:46:26',
        timezone: 'Asia/Jakarta',
        language: 'English'
    }
})

const userInitials = computed(() => {
    return user.value.name
        .split(' ')
        .map(name => name.charAt(0))
        .join('')
        .toUpperCase()
        .slice(0, 2)
})

const tabs = [
    { id: 'profile', name: 'Profile Info', icon: 'user' },
    { id: 'security', name: 'Security', icon: 'shield' },
    { id: 'preferences', name: 'Preferences', icon: 'settings' },
    { id: 'danger', name: 'Danger Zone', icon: 'warning' }
]

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
                                <div class="w-24 h-24 bg-gradient-to-r from-pink-500 to-purple-500 rounded-2xl flex items-center justify-center shadow-2xl group-hover:shadow-3xl transition-all duration-300 group-hover:scale-105">
                                    <span class="text-white text-2xl font-bold">{{ userInitials }}</span>
                                </div>
                                <div class="absolute -bottom-2 -right-2 w-8 h-8 bg-green-400 rounded-full border-4 border-white flex items-center justify-center animate-pulse">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
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
                                        <span>Joined {{ new Date(user.joinedDate).toLocaleDateString() }}</span>
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
                                    <div class="text-2xl font-bold text-white">24</div>
                                    <div class="text-indigo-200 text-sm">Events</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-white">5</div>
                                    <div class="text-indigo-200 text-sm">Calendars</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-white">89%</div>
                                    <div class="text-indigo-200 text-sm">Productivity</div>
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
                                
                                <UpdateProfileInformationForm
                                    :must-verify-email="mustVerifyEmail"
                                    :status="status"
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

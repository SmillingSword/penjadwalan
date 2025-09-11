<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import UserDropdown from '@/Components/UserDropdown.vue';
import NotificationCenter from '@/Components/NotificationCenter.vue';
import ChatManager from '@/Components/Chat/ChatManager.vue';
import { Link } from '@inertiajs/vue3';

// Props
defineProps({
    initialConversations: {
        type: Array,
        default: () => []
    },
    initialOnlineUsers: {
        type: Array,
        default: () => []
    }
});

const showingNavigationDropdown = ref(false);
const currentTime = ref('');

let timeInterval;

onMounted(() => {
    updateTime();
    timeInterval = setInterval(updateTime, 1000);
});

onUnmounted(() => {
    if (timeInterval) {
        clearInterval(timeInterval);
    }
});

function updateTime() {
    const now = new Date();
    const options = { 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: true 
    };
    currentTime.value = now.toLocaleTimeString('en-US', options);
}
</script>

<template>
    <div>
        <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
            <!-- Modern Navigation Bar -->
            <nav class="sticky top-0 z-30 backdrop-blur-xl bg-white/80 border-b border-white/20 shadow-lg shadow-black/5">
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 sm:h-20 justify-between items-center">
                        <!-- Left Section: Logo Only -->
                        <div class="flex items-center">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')" class="transition-transform hover:scale-105">
                                    <ApplicationLogo />
                                </Link>
                            </div>
                        </div>

                        <!-- Right Section: Actions + User -->
                        <div class="flex items-center space-x-3 sm:space-x-4">
                            <!-- Notifications -->
                            <NotificationCenter />

                            <!-- Clock -->
                            <div class="hidden sm:flex items-center space-x-2 px-3 py-2 rounded-xl bg-white/50 backdrop-blur-sm">
                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-sm font-medium text-gray-700">{{ currentTime }}</span>
                            </div>

                            <!-- Mobile Clock (Compact) -->
                            <div class="flex sm:hidden items-center px-2 py-1 rounded-lg bg-white/50 backdrop-blur-sm">
                                <span class="text-xs font-medium text-gray-700">{{ currentTime }}</span>
                            </div>

                            <!-- User Dropdown -->
                            <div>
                                <UserDropdown :user="$page.props.auth.user" />
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header
                class="bg-white/50 backdrop-blur-sm shadow-sm border-b border-white/20"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main class="relative">
                <slot />
            </main>

            <!-- Chat Manager -->
            <ChatManager 
                :initial-conversations="initialConversations"
                :initial-online-users="initialOnlineUsers"
            />
        </div>
    </div>
</template>

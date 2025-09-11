<template>
    <div class="flex items-center space-x-3 group">
        <!-- Modern Logo Container -->
        <div class="relative">
            <!-- Background glow effect -->
            <div class="absolute inset-0 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl blur-lg opacity-30 group-hover:opacity-50 transition-opacity duration-300"></div>
            
            <!-- Logo container -->
            <div class="relative bg-gradient-to-r from-indigo-500 to-purple-600 p-2 rounded-xl shadow-lg group-hover:shadow-xl transition-all duration-300 group-hover:scale-105">
                <svg class="w-8 h-8 text-white" viewBox="0 0 24 24" fill="currentColor">
                    <!-- Modern Calendar Icon -->
                    <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                </svg>
            </div>
        </div>
        
        <!-- Brand Text -->
        <div class="hidden sm:block">
            <h1 class="text-xl font-bold bg-gradient-to-r from-gray-800 to-gray-600 bg-clip-text text-transparent group-hover:from-indigo-600 group-hover:to-purple-600 transition-all duration-300">
                CalendarPro
            </h1>
            <p class="text-xs text-gray-500 font-medium">
                Smart Scheduling
            </p>
        </div>
    </div>
</template>

<style scoped>
/* Additional hover effects */
.group:hover .relative {
    transform: translateY(-1px);
}

/* Smooth gradient animation */
@keyframes gradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

.group:hover h1 {
    background-size: 200% 200%;
    animation: gradientShift 3s ease infinite;
}
</style>

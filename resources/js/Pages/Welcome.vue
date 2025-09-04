<template>
  <GuestLayout>
    <!-- Hero Section -->
    <section id="hero" class="py-20 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto">
        <div class="grid lg:grid-cols-2 gap-12 items-center">
          <!-- Left Content -->
          <div class="space-y-8">
            <div class="space-y-4">
              <h1 class="text-5xl lg:text-6xl font-bold leading-tight">
                <span class="bg-gradient-to-r from-indigo-500 to-purple-600 bg-clip-text text-transparent">
                  Smart Calendar
                </span>
                <br>
                <span class="text-gray-900">
                  for Modern Teams
                </span>
              </h1>
              <p class="text-xl text-gray-600 leading-relaxed max-w-lg">
                Revolusikan penjadwalan Anda dengan manajemen kalender bertenaga AI, kolaborasi tim yang mulus, dan optimisasi meeting yang cerdas.
              </p>
            </div>

            <!-- CTA Buttons -->
            <div class="flex flex-col sm:flex-row gap-4">
              <Link 
                :href="route('register')" 
                class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white px-8 py-4 rounded-xl hover:from-indigo-600 hover:to-purple-700 transition-all duration-200 font-semibold shadow-lg hover:shadow-xl text-center"
              >
                Mulai Gratis
              </Link>
              <button 
                @click="showDemo"
                class="border-2 border-gray-300 text-gray-700 px-8 py-4 rounded-xl hover:border-indigo-600 hover:text-indigo-600 transition-all duration-200 font-semibold flex items-center justify-center space-x-2"
              >
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path>
                </svg>
                <span>Lihat Demo</span>
              </button>
            </div>

            <!-- Social Proof -->
            <div class="flex items-center space-x-6 pt-4">
              <div class="flex -space-x-2">
                <img class="w-8 h-8 rounded-full border-2 border-white" src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=32&h=32&fit=crop&crop=face" alt="User">
                <img class="w-8 h-8 rounded-full border-2 border-white" src="https://images.unsplash.com/photo-1494790108755-2616b612b786?w=32&h=32&fit=crop&crop=face" alt="User">
                <img class="w-8 h-8 rounded-full border-2 border-white" src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=32&h=32&fit=crop&crop=face" alt="User">
              </div>
              <div class="text-sm text-gray-600">
                <span class="font-semibold text-gray-900">10,000+</span> tim sudah menggunakan CalendarPro
              </div>
            </div>
          </div>

          <!-- Right Content - Hero Image/Animation -->
          <div class="relative">
            <div class="relative z-10 bg-white rounded-2xl shadow-2xl p-6 transform rotate-3 hover:rotate-0 transition-transform duration-500">
              <!-- Calendar Preview -->
              <div class="space-y-4">
                <div class="flex items-center justify-between">
                  <h3 class="font-semibold text-gray-900">{{ currentMonthYear }}</h3>
                  <div class="flex space-x-2">
                    <button class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center hover:bg-gray-200 transition-colors">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                      </svg>
                    </button>
                    <button class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center hover:bg-gray-200 transition-colors">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                      </svg>
                    </button>
                  </div>
                </div>
                
                <!-- Calendar Grid -->
                <div class="grid grid-cols-7 gap-2 text-center text-sm">
                  <div class="font-medium text-gray-500 py-2">Min</div>
                  <div class="font-medium text-gray-500 py-2">Sen</div>
                  <div class="font-medium text-gray-500 py-2">Sel</div>
                  <div class="font-medium text-gray-500 py-2">Rab</div>
                  <div class="font-medium text-gray-500 py-2">Kam</div>
                  <div class="font-medium text-gray-500 py-2">Jum</div>
                  <div class="font-medium text-gray-500 py-2">Sab</div>
                  
                  <!-- Calendar Days -->
                  <div class="py-2 text-gray-400">30</div>
                  <div class="py-2 hover:bg-blue-50 rounded cursor-pointer">1</div>
                  <div class="py-2 hover:bg-blue-50 rounded cursor-pointer">2</div>
                  <div class="py-2 hover:bg-blue-50 rounded cursor-pointer relative">
                    3
                    <div class="absolute bottom-1 left-1/2 transform -translate-x-1/2 w-1 h-1 bg-blue-500 rounded-full"></div>
                  </div>
                  <div class="py-2 hover:bg-blue-50 rounded cursor-pointer">4</div>
                  <div class="py-2 hover:bg-blue-50 rounded cursor-pointer">5</div>
                  <div class="py-2 hover:bg-blue-50 rounded cursor-pointer">6</div>
                  
                  <div class="py-2 hover:bg-blue-50 rounded cursor-pointer">7</div>
                  <div class="py-2 hover:bg-blue-50 rounded cursor-pointer">8</div>
                  <div class="py-2 hover:bg-blue-50 rounded cursor-pointer">9</div>
                  <div class="py-2 hover:bg-blue-50 rounded cursor-pointer">10</div>
                  <div class="py-2 hover:bg-blue-50 rounded cursor-pointer">11</div>
                  <div class="py-2 hover:bg-blue-50 rounded cursor-pointer relative">
                    12
                    <div class="absolute bottom-1 left-1/2 transform -translate-x-1/2 w-1 h-1 bg-green-500 rounded-full"></div>
                  </div>
                  <div class="py-2 hover:bg-blue-50 rounded cursor-pointer">13</div>
                  
                  <div class="py-2 hover:bg-blue-50 rounded cursor-pointer">14</div>
                  <div class="py-2 bg-blue-600 text-white rounded font-medium">15</div>
                  <div class="py-2 hover:bg-blue-50 rounded cursor-pointer">16</div>
                  <div class="py-2 hover:bg-blue-50 rounded cursor-pointer">17</div>
                  <div class="py-2 hover:bg-blue-50 rounded cursor-pointer">18</div>
                  <div class="py-2 hover:bg-blue-50 rounded cursor-pointer">19</div>
                  <div class="py-2 hover:bg-blue-50 rounded cursor-pointer">20</div>
                </div>
              </div>
            </div>
            
            <!-- Floating Elements -->
            <div class="absolute -top-4 -right-4 w-20 h-20 bg-gradient-to-r from-purple-400 to-pink-400 rounded-full opacity-20 animate-pulse"></div>
            <div class="absolute -bottom-8 -left-8 w-32 h-32 bg-gradient-to-r from-blue-400 to-indigo-400 rounded-full opacity-10 animate-bounce"></div>
          </div>
        </div>
      </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center space-y-4 mb-16">
          <h2 class="text-4xl font-bold text-gray-900">
            Fitur Powerful untuk
            <span class="bg-gradient-to-r from-indigo-500 to-purple-600 bg-clip-text text-transparent">
              Tim Modern
            </span>
          </h2>
          <p class="text-xl text-gray-600 max-w-3xl mx-auto">
            Semua yang Anda butuhkan untuk mengelola jadwal, koordinasi meeting, dan meningkatkan produktivitas dalam satu platform cerdas.
          </p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
          <!-- Feature 1 -->
          <div class="group p-8 rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-50 hover:from-blue-100 hover:to-indigo-100 transition-all duration-300 border border-blue-100">
            <div class="w-12 h-12 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-3">AI-Powered Scheduling</h3>
            <p class="text-gray-600">
              Algoritma cerdas secara otomatis menemukan waktu meeting terbaik untuk semua peserta, mempertimbangkan zona waktu, preferensi, dan ketersediaan.
            </p>
          </div>

          <!-- Feature 2 -->
          <div class="group p-8 rounded-2xl bg-gradient-to-br from-green-50 to-emerald-50 hover:from-green-100 hover:to-emerald-100 transition-all duration-300 border border-green-100">
            <div class="w-12 h-12 bg-gradient-to-r from-green-600 to-emerald-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Kolaborasi Tim</h3>
            <p class="text-gray-600">
              Koordinasi mulus dengan anggota tim, berbagi kalender, dan kelola jadwal grup dengan kontrol izin yang canggih.
            </p>
          </div>

          <!-- Feature 3 -->
          <div class="group p-8 rounded-2xl bg-gradient-to-br from-purple-50 to-pink-50 hover:from-purple-100 hover:to-pink-100 transition-all duration-300 border border-purple-100">
            <div class="w-12 h-12 bg-gradient-to-r from-purple-600 to-pink-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Lightning Fast</h3>
            <p class="text-gray-600">
              Dibangun dengan teknologi modern untuk loading instan, update real-time, dan performa mulus di semua perangkat.
            </p>
          </div>

          <!-- Feature 4 -->
          <div class="group p-8 rounded-2xl bg-gradient-to-br from-orange-50 to-red-50 hover:from-orange-100 hover:to-red-100 transition-all duration-300 border border-orange-100">
            <div class="w-12 h-12 bg-gradient-to-r from-orange-600 to-red-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Keamanan Enterprise</h3>
            <p class="text-gray-600">
              Keamanan tingkat bank dengan enkripsi end-to-end, compliance GDPR, dan kontrol akses canggih untuk melindungi data Anda.
            </p>
          </div>

          <!-- Feature 5 -->
          <div class="group p-8 rounded-2xl bg-gradient-to-br from-teal-50 to-cyan-50 hover:from-teal-100 hover:to-cyan-100 transition-all duration-300 border border-teal-100">
            <div class="w-12 h-12 bg-gradient-to-r from-teal-600 to-cyan-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Integrasi</h3>
            <p class="text-gray-600">
              Terhubung dengan Google Calendar, Outlook, Zoom, Slack, dan 100+ tools lain yang sudah Anda gunakan setiap hari.
            </p>
          </div>

          <!-- Feature 6 -->
          <div class="group p-8 rounded-2xl bg-gradient-to-br from-indigo-50 to-blue-50 hover:from-indigo-100 hover:to-blue-100 transition-all duration-300 border border-indigo-100">
            <div class="w-12 h-12 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Analytics & Insights</h3>
            <p class="text-gray-600">
              Dapatkan insight detail tentang pola meeting, metrik produktivitas, dan statistik kolaborasi tim.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 bg-gradient-to-r from-indigo-500 to-purple-600">
      <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
        <h2 class="text-4xl font-bold text-white mb-6">
          Siap Transformasi Penjadwalan Anda?
        </h2>
        <p class="text-xl text-indigo-100 mb-8 max-w-2xl mx-auto">
          Bergabunglah dengan ribuan tim yang telah merevolusi produktivitas mereka dengan CalendarPro. Mulai uji coba gratis hari ini.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <Link 
            :href="route('register')" 
            class="bg-white text-indigo-600 px-8 py-4 rounded-xl hover:bg-gray-50 transition-all duration-200 font-semibold shadow-lg hover:shadow-xl"
          >
            Mulai Gratis
          </Link>
          <Link 
            :href="route('pricing')" 
            class="border-2 border-white text-white px-8 py-4 rounded-xl hover:bg-white hover:text-indigo-600 transition-all duration-200 font-semibold"
          >
            Lihat Pricing
          </Link>
        </div>
      </div>
    </section>
  </GuestLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import { ref, computed, onMounted } from 'vue'
import GuestLayout from '@/Layouts/GuestLayout.vue'

const currentDate = ref(new Date())

const currentMonthYear = computed(() => {
  const months = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
  ]
  return `${months[currentDate.value.getMonth()]} ${currentDate.value.getFullYear()}`
})

const showDemo = () => {
  alert('Demo akan segera tersedia! Silakan daftar untuk mendapatkan akses early access.')
}

onMounted(() => {
  // Update current date when component mounts
  currentDate.value = new Date()
})
</script>

<style scoped>
html {
  scroll-behavior: smooth;
}
</style>

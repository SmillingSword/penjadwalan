<template>
  <GuestLayout>
    <!-- Hero Section -->
    <section class="py-20 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto text-center">
        <h1 class="text-5xl font-bold text-gray-900 mb-6">
          Pilih Paket yang 
          <span class="bg-gradient-to-r from-indigo-500 to-purple-600 bg-clip-text text-transparent">
            Tepat untuk Anda
          </span>
        </h1>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto mb-12">
          Mulai gratis dan tingkatkan sesuai kebutuhan tim Anda. Semua paket dilengkapi dengan fitur keamanan tingkat enterprise dan dukungan 24/7.
        </p>

        <!-- Billing Toggle -->
        <div class="flex items-center justify-center mb-12">
          <span class="text-gray-600 mr-3">Bulanan</span>
          <button 
            @click="isYearly = !isYearly"
            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            :class="isYearly ? 'bg-indigo-600' : 'bg-gray-200'"
          >
            <span 
              class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
              :class="isYearly ? 'translate-x-6' : 'translate-x-1'"
            ></span>
          </button>
          <span class="text-gray-600 ml-3">Tahunan</span>
          <span class="ml-2 inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
            Hemat 20%
          </span>
        </div>
      </div>
    </section>

    <!-- Pricing Cards -->
    <section class="pb-20 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto">
        <div class="grid lg:grid-cols-3 gap-8">
          <!-- Free Plan -->
          <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8 relative">
            <div class="text-center mb-8">
              <h3 class="text-2xl font-bold text-gray-900 mb-2">Gratis</h3>
              <p class="text-gray-600 mb-6">Sempurna untuk penggunaan personal</p>
              <div class="mb-6">
                <span class="text-4xl font-bold text-gray-900">Rp 0</span>
                <span class="text-gray-600">/bulan</span>
              </div>
              <Link 
                :href="route('register')" 
                class="w-full bg-gray-900 text-white py-3 px-6 rounded-lg hover:bg-gray-800 transition-colors font-medium inline-block text-center"
              >
                Mulai Gratis
              </Link>
            </div>

            <div class="space-y-4">
              <div class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-gray-700">1 kalender pribadi</span>
              </div>
              <div class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-gray-700">Hingga 50 event per bulan</span>
              </div>
              <div class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-gray-700">Reminder email</span>
              </div>
              <div class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-gray-700">Sinkronisasi Google Calendar</span>
              </div>
              <div class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-gray-700">Dukungan email</span>
              </div>
            </div>
          </div>

          <!-- Pro Plan -->
          <div class="bg-white rounded-2xl shadow-xl border-2 border-indigo-500 p-8 relative transform scale-105">
            <div class="absolute -top-4 left-1/2 transform -translate-x-1/2">
              <span class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white px-4 py-1 rounded-full text-sm font-medium">
                Paling Populer
              </span>
            </div>
            
            <div class="text-center mb-8">
              <h3 class="text-2xl font-bold text-gray-900 mb-2">Pro</h3>
              <p class="text-gray-600 mb-6">Ideal untuk tim kecil dan menengah</p>
              <div class="mb-6">
                <span class="text-4xl font-bold text-gray-900">
                  Rp {{ isYearly ? '79.200' : '99.000' }}
                </span>
                <span class="text-gray-600">/{{ isYearly ? 'bulan' : 'bulan' }}</span>
                <div v-if="isYearly" class="text-sm text-green-600 mt-1">
                  Ditagih Rp 950.400/tahun
                </div>
              </div>
              <Link 
                :href="route('register')" 
                class="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white py-3 px-6 rounded-lg hover:from-indigo-600 hover:to-purple-700 transition-all duration-200 font-medium shadow-lg hover:shadow-xl inline-block text-center"
              >
                Mulai Uji Coba 14 Hari
              </Link>
            </div>

            <div class="space-y-4">
              <div class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-gray-700">Kalender unlimited</span>
              </div>
              <div class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-gray-700">Event unlimited</span>
              </div>
              <div class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-gray-700">Kolaborasi tim (hingga 10 anggota)</span>
              </div>
              <div class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-gray-700">AI scheduling assistant</span>
              </div>
              <div class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-gray-700">Integrasi dengan Zoom, Teams, Meet</span>
              </div>
              <div class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-gray-700">Analytics dan reporting</span>
              </div>
              <div class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-gray-700">Dukungan prioritas</span>
              </div>
            </div>
          </div>

          <!-- Enterprise Plan -->
          <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8 relative">
            <div class="text-center mb-8">
              <h3 class="text-2xl font-bold text-gray-900 mb-2">Enterprise</h3>
              <p class="text-gray-600 mb-6">Untuk organisasi besar dengan kebutuhan khusus</p>
              <div class="mb-6">
                <span class="text-4xl font-bold text-gray-900">
                  Rp {{ isYearly ? '239.200' : '299.000' }}
                </span>
                <span class="text-gray-600">/{{ isYearly ? 'bulan' : 'bulan' }}</span>
                <div v-if="isYearly" class="text-sm text-green-600 mt-1">
                  Ditagih Rp 2.870.400/tahun
                </div>
              </div>
              <Link 
                :href="route('contact')" 
                class="w-full bg-gray-900 text-white py-3 px-6 rounded-lg hover:bg-gray-800 transition-colors font-medium inline-block text-center"
              >
                Hubungi Sales
              </Link>
            </div>

            <div class="space-y-4">
              <div class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-gray-700">Semua fitur Pro</span>
              </div>
              <div class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-gray-700">Anggota tim unlimited</span>
              </div>
              <div class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-gray-700">Advanced security & compliance</span>
              </div>
              <div class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-gray-700">Single Sign-On (SSO)</span>
              </div>
              <div class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-gray-700">API access & webhooks</span>
              </div>
              <div class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-gray-700">Dedicated account manager</span>
              </div>
              <div class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-gray-700">24/7 phone support</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-20 bg-white">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
          <h2 class="text-3xl font-bold text-gray-900 mb-4">
            Pertanyaan yang Sering Diajukan
          </h2>
          <p class="text-xl text-gray-600">
            Temukan jawaban untuk pertanyaan umum tentang pricing dan fitur kami
          </p>
        </div>

        <div class="space-y-8">
          <div class="border border-gray-200 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">
              Apakah saya bisa upgrade atau downgrade paket kapan saja?
            </h3>
            <p class="text-gray-600">
              Ya, Anda dapat mengubah paket berlangganan kapan saja. Perubahan akan berlaku pada periode billing berikutnya, dan kami akan melakukan prorating untuk perbedaan harga.
            </p>
          </div>

          <div class="border border-gray-200 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">
              Apakah ada biaya setup atau biaya tersembunyi?
            </h3>
            <p class="text-gray-600">
              Tidak ada biaya setup atau biaya tersembunyi. Harga yang tertera sudah termasuk semua fitur yang disebutkan dalam paket tersebut.
            </p>
          </div>

          <div class="border border-gray-200 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">
              Bagaimana cara pembayaran?
            </h3>
            <p class="text-gray-600">
              Kami menerima pembayaran melalui kartu kredit, transfer bank, dan e-wallet. Pembayaran diproses secara otomatis setiap bulan atau tahun sesuai paket yang dipilih.
            </p>
          </div>

          <div class="border border-gray-200 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">
              Apakah data saya aman?
            </h3>
            <p class="text-gray-600">
              Keamanan data adalah prioritas utama kami. Semua data dienkripsi end-to-end dan kami mematuhi standar keamanan internasional termasuk GDPR dan ISO 27001.
            </p>
          </div>

          <div class="border border-gray-200 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">
              Bagaimana jika saya tidak puas dengan layanan?
            </h3>
            <p class="text-gray-600">
              Kami menawarkan jaminan uang kembali 30 hari untuk semua paket berbayar. Jika Anda tidak puas, hubungi tim support kami untuk mendapatkan refund penuh.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 bg-gradient-to-r from-indigo-500 to-purple-600">
      <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
        <h2 class="text-4xl font-bold text-white mb-6">
          Siap Memulai?
        </h2>
        <p class="text-xl text-indigo-100 mb-8 max-w-2xl mx-auto">
          Bergabunglah dengan ribuan tim yang telah meningkatkan produktivitas mereka dengan CalendarPro
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <Link 
            :href="route('register')" 
            class="bg-white text-indigo-600 px-8 py-4 rounded-xl hover:bg-gray-50 transition-all duration-200 font-semibold shadow-lg hover:shadow-xl"
          >
            Mulai Gratis Sekarang
          </Link>
          <Link 
            :href="route('contact')" 
            class="border-2 border-white text-white px-8 py-4 rounded-xl hover:bg-white hover:text-indigo-600 transition-all duration-200 font-semibold"
          >
            Hubungi Sales
          </Link>
        </div>
      </div>
    </section>
  </GuestLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import GuestLayout from '@/Layouts/GuestLayout.vue'

const isYearly = ref(false)
</script>

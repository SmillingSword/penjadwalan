<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
    <!-- Navigation -->
    <nav class="fixed w-full z-50 backdrop-blur-xl bg-white/80 border-b border-white/20 shadow-lg shadow-black/5">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
          <!-- Logo -->
          <div class="flex items-center space-x-3">
            <Link :href="route('welcome')" class="flex items-center space-x-3">
              <div class="w-10 h-10 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
              </div>
              <span class="text-xl font-bold bg-gradient-to-r from-indigo-500 to-purple-600 bg-clip-text text-transparent">
                CalendarPro
              </span>
            </Link>
          </div>

          <!-- Navigation Links -->
          <div class="hidden md:flex items-center space-x-8">
            <Link 
              :href="route('welcome')" 
              class="text-gray-600 hover:text-indigo-600 transition-colors font-medium"
              :class="{ 'text-indigo-600': $page.component === 'Welcome' }"
            >
              Home
            </Link>
            <Link 
              :href="route('pricing')" 
              class="text-gray-600 hover:text-indigo-600 transition-colors font-medium"
              :class="{ 'text-indigo-600': $page.component === 'Pricing' }"
            >
              Pricing
            </Link>
            <Link 
              :href="route('about')" 
              class="text-gray-600 hover:text-indigo-600 transition-colors font-medium"
              :class="{ 'text-indigo-600': $page.component === 'About' }"
            >
              About
            </Link>
            <Link 
              :href="route('contact')" 
              class="text-gray-600 hover:text-indigo-600 transition-colors font-medium"
              :class="{ 'text-indigo-600': $page.component === 'Contact' }"
            >
              Contact
            </Link>
          </div>

          <!-- Auth Buttons -->
          <div class="flex items-center space-x-4">
            <Link 
              :href="route('login')" 
              class="text-gray-600 hover:text-indigo-600 transition-colors font-medium"
            >
              Login
            </Link>
            <Link 
              :href="route('register')" 
              class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white px-6 py-2 rounded-lg hover:from-indigo-600 hover:to-purple-700 transition-all duration-200 font-medium shadow-lg hover:shadow-xl"
            >
              Get Started
            </Link>
          </div>

          <!-- Mobile menu button -->
          <div class="md:hidden">
            <button 
              @click="mobileMenuOpen = !mobileMenuOpen"
              class="text-gray-600 hover:text-indigo-600 transition-colors"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
        </div>

        <!-- Mobile Menu -->
        <div v-show="mobileMenuOpen" class="md:hidden py-4 border-t border-gray-200">
          <div class="flex flex-col space-y-4">
            <Link 
              :href="route('welcome')" 
              class="text-gray-600 hover:text-indigo-600 transition-colors font-medium"
              @click="mobileMenuOpen = false"
            >
              Home
            </Link>
            <Link 
              :href="route('pricing')" 
              class="text-gray-600 hover:text-indigo-600 transition-colors font-medium"
              @click="mobileMenuOpen = false"
            >
              Pricing
            </Link>
            <Link 
              :href="route('about')" 
              class="text-gray-600 hover:text-indigo-600 transition-colors font-medium"
              @click="mobileMenuOpen = false"
            >
              About
            </Link>
            <Link 
              :href="route('contact')" 
              class="text-gray-600 hover:text-indigo-600 transition-colors font-medium"
              @click="mobileMenuOpen = false"
            >
              Contact
            </Link>
            <div class="flex flex-col space-y-2 pt-4 border-t border-gray-200">
              <Link 
                :href="route('login')" 
                class="text-gray-600 hover:text-indigo-600 transition-colors font-medium"
                @click="mobileMenuOpen = false"
              >
                Login
              </Link>
              <Link 
                :href="route('register')" 
                class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white px-6 py-2 rounded-lg hover:from-indigo-600 hover:to-purple-700 transition-all duration-200 font-medium shadow-lg hover:shadow-xl text-center"
                @click="mobileMenuOpen = false"
              >
                Get Started
              </Link>
            </div>
          </div>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <main class="pt-16">
      <slot />
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-16">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-4 gap-8">
          <!-- Company Info -->
          <div class="space-y-4">
            <div class="flex items-center space-x-3">
              <div class="w-10 h-10 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
              </div>
              <span class="text-xl font-bold">CalendarPro</span>
            </div>
            <p class="text-gray-400 max-w-sm">
              Platform kalender dan penjadwalan paling canggih untuk tim modern. Dibangun dengan keamanan tingkat enterprise dan kecerdasan AI.
            </p>
          </div>

          <!-- Product -->
          <div>
            <h3 class="font-semibold mb-4">Produk</h3>
            <ul class="space-y-2 text-gray-400">
              <li><Link :href="route('welcome')" class="hover:text-white transition-colors">Features</Link></li>
              <li><Link :href="route('pricing')" class="hover:text-white transition-colors">Pricing</Link></li>
              <li><a href="#" class="hover:text-white transition-colors">API</a></li>
              <li><a href="#" class="hover:text-white transition-colors">Integrations</a></li>
            </ul>
          </div>

          <!-- Company -->
          <div>
            <h3 class="font-semibold mb-4">Perusahaan</h3>
            <ul class="space-y-2 text-gray-400">
              <li><Link :href="route('about')" class="hover:text-white transition-colors">About</Link></li>
              <li><a href="#" class="hover:text-white transition-colors">Blog</a></li>
              <li><a href="#" class="hover:text-white transition-colors">Careers</a></li>
              <li><Link :href="route('contact')" class="hover:text-white transition-colors">Contact</Link></li>
            </ul>
          </div>

          <!-- Support -->
          <div>
            <h3 class="font-semibold mb-4">Dukungan</h3>
            <ul class="space-y-2 text-gray-400">
              <li><a href="#" class="hover:text-white transition-colors">Help Center</a></li>
              <li><a href="#" class="hover:text-white transition-colors">Documentation</a></li>
              <li><a href="#" class="hover:text-white transition-colors">Status</a></li>
              <li><a href="#" class="hover:text-white transition-colors">Privacy</a></li>
            </ul>
          </div>
        </div>

        <div class="border-t border-gray-800 mt-12 pt-8 flex flex-col md:flex-row justify-between items-center">
          <p class="text-gray-400">
            © 2024 CalendarPro. All rights reserved.
          </p>
          <div class="flex space-x-6 mt-4 md:mt-0">
            <a href="#" class="text-gray-400 hover:text-white transition-colors">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M20 10c0-5.523-4.477-10-10-10S0 4.477 0 10c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V10h2.54V7.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V10h2.773l-.443 2.89h-2.33v6.988C16.343 19.128 20 14.991 20 10z" clip-rule="evenodd"></path>
              </svg>
            </a>
            <a href="#" class="text-gray-400 hover:text-white transition-colors">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M6.29 18.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0020 3.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.073 4.073 0 01.8 7.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 010 16.407a11.616 11.616 0 006.29 1.84"></path>
              </svg>
            </a>
            <a href="#" class="text-gray-400 hover:text-white transition-colors">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.338 16.338H13.67V12.16c0-.995-.017-2.277-1.387-2.277-1.39 0-1.601 1.086-1.601 2.207v4.248H8.014v-8.59h2.559v1.174h.037c.356-.675 1.227-1.387 2.526-1.387 2.703 0 3.203 1.778 3.203 4.092v4.711zM5.005 6.575a1.548 1.548 0 11-.003-3.096 1.548 1.548 0 01.003 3.096zm-1.337 9.763H6.34v-8.59H3.667v8.59zM17.668 1H2.328C1.595 1 1 1.581 1 2.298v15.403C1 18.418 1.595 19 2.328 19h15.34c.734 0 1.332-.582 1.332-1.299V2.298C19 1.581 18.402 1 17.668 1z" clip-rule="evenodd"></path>
              </svg>
            </a>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import { ref } from 'vue'

const mobileMenuOpen = ref(false)
</script>

<style scoped>
html {
  scroll-behavior: smooth;
}
</style>

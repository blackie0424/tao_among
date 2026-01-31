<template>
  <div class="min-h-screen bg-gray-50 pb-[calc(6rem+env(safe-area-inset-bottom))] lg:pb-6 relative pt-4">
    
    <!-- 頂部導覽列 -->
    <header class="sticky top-4 z-30">
      <div class="container mx-auto max-w-7xl bg-white/90 backdrop-blur-md shadow-sm border border-gray-100 rounded-2xl">
        <div class="px-4 flex flex-col lg:flex-row lg:items-center justify-between">
        
        <!-- Mobile Row 1: Nav & User (Layout) -->
        <div class="flex items-center justify-between w-full lg:hidden h-14">
          <!-- Mobile Links (Breadcrumb Style) to match Desktop -->
          <div class="flex items-center gap-1 shrink-0 overflow-hidden">
             <!-- Home Link -->
             <Link href="/" class="font-medium text-gray-500 hover:text-gray-900 transition flex items-center gap-1 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                <span class="text-sm">首頁</span>
             </Link>
             
             <!-- Separator 1 -->
             <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
             
             <!-- Intermediate Link (e.g. "among no tao" when on Detail page) -->
             <template v-if="mobileBackUrl !== '/'">
                <Link :href="mobileBackUrl" class="font-bold text-gray-500 hover:text-blue-600 transition shrink-0 whitespace-nowrap text-sm sm:text-base">
                   {{ mobileBackText }}
                </Link>
                <!-- Separator 2 -->
                <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
             </template>

             <!-- Current Page Title -->
             <span class="font-bold text-gray-900 text-lg tracking-wide truncate">
               {{ pageTitle }}
             </span>
          </div>

          <!-- Right: User Menu -->
          <div class="relative shrink-0">
             <!-- Logged In: Avatar Button -->
             <button 
                v-if="user" 
                @click="showMobileUserMenu = !showMobileUserMenu"
                class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-50 text-blue-600 border border-blue-100 hover:bg-blue-100 transition"
             >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
             </button>
             
             <!-- Guest: Login Link -->
             <Link 
                v-else 
                href="/login" 
                class="flex items-center gap-1 text-sm font-medium text-gray-500 hover:text-blue-600"
             >
                登入
             </Link>

             <!-- Mobile User Dropdown -->
             <div v-if="showMobileUserMenu && user" class="absolute right-0 top-full mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-50 animate-fade-in-down">
                <!-- User Info -->
                <div class="px-4 py-3 border-b border-gray-50 bg-gray-50/50">
                    <div class="text-sm font-bold text-gray-900 truncate">{{ user.name }}</div>
                    <div class="text-xs text-blue-600 font-medium mt-0.5">田調人員</div>
                </div>
                <!-- Actions -->
                <Link href="/logout" method="post" as="button" class="block w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-red-600 transition">
                    登出
                </Link>
             </div>
             
             <!-- Backdrop for closing -->
             <div v-if="showMobileUserMenu" @click="showMobileUserMenu = false" class="fixed inset-0 z-40" style="background: transparent;"></div>
          </div>
        </div>

        <!-- Mobile Row 2: Actions Slot (Search, Count, Add) -->
        <div class="lg:hidden w-full border-t border-gray-100 py-2">
            <slot name="mobile-actions" />
        </div>

        <!-- Desktop Nav (>= 1024px) -->
        <div class="hidden lg:flex items-center gap-4 w-full h-14">
          <!-- Desktop Navigation Links -->
          <div class="flex items-center gap-2 shrink-0">
            <!-- Home Link -->
            <Link href="/" class="font-medium text-gray-500 hover:text-gray-900 transition flex items-center gap-1">
               <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
               首頁
            </Link>

            <!-- Separator -->
            <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            
            <!-- App / Fish List Link -->
            <Link href="/fishs" class="font-bold text-gray-900 text-lg tracking-wide hover:text-blue-600 transition">
              among no tao
            </Link>
          </div>
          
          <!-- Desktop Nav Content (Breadcrumbs by default) -->
          <div class="flex-1 flex items-center min-w-0">
            <slot name="desktop-nav">
              <div class="flex items-center text-sm text-gray-500 gap-2">
                <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                <Link v-if="breadcrumbPage" :href="`/fish/${fish?.id}`" class="hover:text-blue-600 transition">{{ fish?.name }}</Link>
                <span v-if="breadcrumbPage" class="text-gray-300">/</span>
                <span class="font-medium text-gray-900">{{ breadcrumbPage || fish?.name }}</span>
              </div>
            </slot>
          </div>

          <!-- User Menu (Right aligned) -->
          <div class="ml-auto flex items-center gap-3 shrink-0">
            <div v-if="user" class="text-sm font-medium text-gray-700 flex items-center gap-2">
              <span class="bg-blue-100 text-blue-800 py-1 px-3 rounded-full text-xs">田調人員</span>
              {{ user.name }}
            </div>
            <Link v-if="user" href="/logout" method="post" as="button" class="text-sm text-gray-500 hover:text-red-600">
              登出
            </Link>
            <Link v-else href="/login" class="text-sm text-blue-600 hover:text-blue-700 font-medium">
              登入
            </Link>
          </div>
        </div>
      </div>
    <!-- Header Extension Slot -->
    <slot name="header-extension" />
      </div>
    </header>

    <!-- 全局 Flash Message -->
    <FlashMessage />

    <!-- 主要內容區域 -->
    <main class="container mx-auto max-w-7xl px-4 py-6">
      <slot />
    </main>

    <!-- 底部導覽列 (手機版) -->
    <slot name="bottom-nav">
      <BottomNavBar :fishId="fishId" :activeTab="activeTab" />
    </slot>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import BottomNavBar from '@/Components/Global/BottomNavBar.vue'
import FlashMessage from '@/Components/FlashMessage.vue'

// 從 Inertia page props 取得 fish 資料
const page = usePage()
const fish = computed(() => page.props.fish)
const user = computed(() => page.props.auth?.user)

// Mobile User Menu State
const showMobileUserMenu = ref(false)

// Props 定義
const props = defineProps({
  pageTitle: {
    type: String,
    default: '基本資料'
  },
  activeTab: {
    type: String,
    default: 'basic' // 'basic' | 'media' | 'knowledge'
  },
  // 用於子頁面的 breadcrumb (例如：「捕獲與發音」)
  breadcrumbPage: {
    type: String,
    default: ''
  },
  // 手機版返回按鈕設定
  mobileBackUrl: {
    type: String,
    default: '/fishs'
  },
  mobileBackText: {
    type: String,
    default: 'among no tao'
  }
})

// 計算 fishId，優先使用 fish 物件
const fishId = computed(() => fish.value?.id)
</script>

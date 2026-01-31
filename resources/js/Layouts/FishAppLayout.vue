<template>
  <div class="min-h-screen bg-gray-50 pb-[calc(6rem+env(safe-area-inset-bottom))] lg:pb-6 relative">
    
    <!-- 頂部導覽列 -->
    <header class="sticky top-0 z-30 bg-white/90 backdrop-blur-md shadow-sm border-b border-gray-100">
      <div class="container mx-auto max-w-7xl px-4 h-14 flex items-center justify-between gap-4">
        
        <!-- Mobile Nav (< 1024px) -->
        <div class="flex items-center gap-3 lg:hidden flex-1 min-w-0">
          <Link :href="mobileBackUrl" class="text-gray-600 hover:text-blue-600 flex items-center gap-1 shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            <span class="text-sm font-medium">{{ mobileBackText }}</span>
          </Link>
          <h1 class="text-lg font-bold text-gray-900 mx-auto truncate px-2">{{ pageTitle }}</h1>
          <!-- Mobile Actions Slot -->
          <div class="shrink-0">
             <slot name="mobile-actions" />
          </div>
        </div>

        <!-- Desktop Nav (>= 1024px) -->
        <div class="hidden lg:flex items-center gap-4 w-full">
          <!-- Logo / Home -->
          <Link href="/fishs" class="font-bold text-gray-900 text-lg tracking-wide hover:text-blue-600 transition shrink-0">
            among no tao
          </Link>
          
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
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import BottomNavBar from '@/Components/Global/BottomNavBar.vue'
import FlashMessage from '@/Components/FlashMessage.vue'

// 從 Inertia page props 取得 fish 資料
const page = usePage()
const fish = computed(() => page.props.fish)
const user = computed(() => page.props.auth?.user)

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
    default: '圖鑑列表'
  }
})

// 計算 fishId，優先使用 fish 物件
const fishId = computed(() => fish.value?.id)
</script>

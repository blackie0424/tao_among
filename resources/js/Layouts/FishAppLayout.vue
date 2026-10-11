<template>
  <div class="min-h-screen bg-gray-50 relative pb-6" :class="showHeader ? 'pt-4' : 'pt-0'">
    <!-- 頂部導覽列 -->
    <AppNavBar
      v-if="showHeader"
      :pageTitle="pageTitle"
      :breadcrumbPage="breadcrumbPage"
      :mobileBackUrl="mobileBackUrl"
      :mobileBackText="mobileBackText"
      :stickyMobile="stickyMobile"
      :showMobileTitle="showMobileTitle"
    >
      <template #mobile-actions><slot name="mobile-actions" /></template>
      <template v-if="$slots['desktop-nav']" #desktop-nav>
        <slot name="desktop-nav" />
      </template>
      <template v-if="$slots['header-extension']" #header-extension>
        <slot name="header-extension" />
      </template>
    </AppNavBar>

    <!-- 全局 Flash Message -->
    <FlashMessage />

    <!-- 主要內容區域 -->
    <main class="container mx-auto max-w-7xl px-4 py-6">
      <slot />
    </main>

    <AppFooter />
  </div>
</template>

<script setup>
import FlashMessage from '@/Components/Global/FlashMessage.vue'
import AppFooter from '@/Components/Global/AppFooter.vue'
import AppNavBar from '@/Components/Global/AppNavBar.vue'

// Props 定義
defineProps({
  pageTitle: {
    type: String,
    default: '基本資料',
  },
  activeTab: {
    type: String,
    default: 'basic', // 'basic' | 'media' | 'knowledge'
  },
  // 用於子頁面的 breadcrumb (例如：「捕獲與發音」)
  breadcrumbPage: {
    type: String,
    default: '',
  },
  // 手機版返回按鈕設定
  mobileBackUrl: {
    type: String,
    default: '/fishs',
  },
  mobileBackText: {
    type: String,
    default: '首頁',
  },
  showBottomNav: {
    type: Boolean,
    default: true,
  },
  showHeader: {
    type: Boolean,
    default: true,
  },
  stickyMobile: {
    type: Boolean,
    default: true,
  },
  showMobileTitle: {
    type: Boolean,
    default: true,
  },
})
</script>

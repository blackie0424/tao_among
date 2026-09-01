<template>
  <div class="min-h-screen bg-gray-50 flex">
    <!-- Sidebar Overlay (mobile) -->
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 z-40 bg-black/40 lg:hidden"
      @click="sidebarOpen = false"
    />

    <!-- Sidebar -->
    <aside
      class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200 flex flex-col
             transition-transform duration-200
             lg:translate-x-0 lg:static lg:z-auto"
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <!-- Logo -->
      <div class="h-16 flex items-center px-5 border-b border-gray-200 shrink-0">
        <span class="font-bold text-elder-name text-elder-text tracking-wide">among · 後台</span>
      </div>

      <!-- Nav -->
      <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
        <Link
          href="/admin"
          class="nav-item"
          :class="{ 'nav-item--active': currentUrl === '/admin' }"
          @click="sidebarOpen = false"
        >
          <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
          <span>控制台</span>
        </Link>

        <Link
          href="/dashboard"
          class="nav-item"
          :class="{ 'nav-item--active': currentUrl.startsWith('/dashboard') }"
          @click="sidebarOpen = false"
        >
          <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
          <span>統計面板</span>
        </Link>

        <Link
          href="/line-users"
          class="nav-item"
          :class="{ 'nav-item--active': currentUrl.startsWith('/line-users') }"
          @click="sidebarOpen = false"
        >
          <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
          <span>使用者管理</span>
        </Link>

        <Link
          href="/admin/references"
          class="nav-item"
          :class="{ 'nav-item--active': currentUrl.startsWith('/admin/references') }"
          @click="sidebarOpen = false"
        >
          <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
          </svg>
          <span>文獻管理</span>
        </Link>

        <Link
          href="/admin/intro-categories"
          class="nav-item"
          :class="{ 'nav-item--active': currentUrl.startsWith('/admin/intro-categories') }"
          @click="sidebarOpen = false"
        >
          <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z" />
          </svg>
          <span>投影片分類</span>
        </Link>

        <Link
          href="/admin/intro-slides"
          class="nav-item"
          :class="{ 'nav-item--active': currentUrl.startsWith('/admin/intro-slides') }"
          @click="sidebarOpen = false"
        >
          <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          <span>首頁投影片</span>
        </Link>

        <Link
          href="/fish-report"
          class="nav-item"
          :class="{ 'nav-item--active': currentUrl.startsWith('/fish-report') }"
          @click="sidebarOpen = false"
        >
          <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 17v-2m3 2v-4m3 4v-6M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
          </svg>
          <span>魚類報表</span>
        </Link>
      </nav>

      <!-- Footer: user + logout -->
      <div class="shrink-0 border-t border-gray-200 p-4 space-y-2">
        <div class="text-elder-aux text-gray-500 truncate">{{ user?.name }}</div>
        <Link
          href="/logout"
          method="post"
          as="button"
          class="w-full text-left text-elder-aux text-red-600 hover:text-red-700 transition"
          @click="sidebarOpen = false"
        >
          登出
        </Link>
      </div>
    </aside>

    <!-- Main content -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Topbar -->
      <header class="h-16 bg-white border-b border-gray-200 flex items-center px-4 gap-3 shrink-0">
        <!-- Hamburger (mobile) -->
        <button
          class="flex items-center justify-center w-12 h-12 rounded-lg hover:bg-gray-100 transition lg:hidden"
          aria-label="開啟選單"
          @click="sidebarOpen = true"
        >
          <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>

        <h1 class="font-bold text-elder-name text-elder-text flex-1 truncate">{{ title }}</h1>

        <!-- 回前台 -->
        <Link
          href="/"
          class="inline-flex items-center gap-1.5 text-elder-aux text-gray-700 hover:text-blue-600 transition"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          回前台
        </Link>

        <!-- Admin name (desktop) -->
        <span class="hidden lg:block text-elder-aux text-gray-500 truncate max-w-[8rem]">
          {{ user?.name }}
        </span>
      </header>

      <!-- Page content -->
      <main class="flex-1 overflow-y-auto">
        <div class="container mx-auto max-w-7xl px-4 py-6">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

defineProps({
  title: { type: String, required: true },
})

const page = usePage()
const user = computed(() => page.props.auth?.user)
const currentUrl = computed(() => page.url)
const sidebarOpen = ref(false)

watch(sidebarOpen, (val) => {
  document.body.style.overflow = val ? 'hidden' : ''
})
onUnmounted(() => {
  document.body.style.overflow = ''
})
</script>

<style scoped>
.nav-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.625rem 0.75rem;
  border-radius: 0.5rem;
  font-size: 1.125rem; /* elder-body */
  font-weight: 500;
  color: #3f454c;
  transition: background-color 0.15s, color 0.15s;
  min-height: 3rem; /* touch-secondary */
  text-decoration: none;
}
.nav-item:hover {
  background-color: #f3f4f6;
  color: #16181d;
}
.nav-item--active {
  background-color: #eff6ff;
  color: #1d4ed8;
  font-weight: 600;
}
</style>

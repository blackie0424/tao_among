<template>
  <header class="sticky top-0 z-30 bg-white/90 backdrop-blur-md shadow-sm border-b border-gray-100">
    <div class="container mx-auto max-w-7xl px-4 h-14 flex items-center justify-between">
      <div class="flex items-center gap-3 lg:hidden w-full">
        <Link href="/fishs" class="text-gray-600 hover:text-blue-600 flex items-center gap-1">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M15 19l-7-7 7-7"
            ></path>
          </svg>
          <span class="text-sm font-medium">圖鑑列表</span>
        </Link>
        <h1 class="text-lg font-bold text-gray-900 mx-auto">
          <slot name="mobile-title">基本資料</slot>
        </h1>
        <!-- Admin Mobile Dropdown -->
        <div v-if="user?.role === 'admin'" class="relative shrink-0">
          <button
            @click="showMobileAdminMenu = !showMobileAdminMenu"
            class="flex items-center gap-1 text-xs font-medium text-gray-700 hover:text-blue-600 transition whitespace-nowrap"
          >
            {{ user.name }}
            <svg
              class="w-3 h-3 text-gray-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M19 9l-7 7-7-7"
              />
            </svg>
          </button>
          <div
            v-if="showMobileAdminMenu"
            class="absolute right-0 top-full mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-50"
          >
            <Link
              href="/dashboard"
              class="block w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-blue-600 transition"
              @click="showMobileAdminMenu = false"
            >
              統計面板
            </Link>
            <Link
              href="/line-users"
              class="block w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-green-600 transition"
              @click="showMobileAdminMenu = false"
            >
              使用者管理
            </Link>
            <Link
              href="/logout"
              method="post"
              as="button"
              class="block w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-red-600 transition"
            >
              登出
            </Link>
          </div>
          <div
            v-if="showMobileAdminMenu"
            @click="showMobileAdminMenu = false"
            class="fixed inset-0 z-40"
            style="background: transparent"
          ></div>
        </div>
      </div>

      <div class="hidden lg:flex items-center gap-4 w-full">
        <Link
          href="/fishs"
          class="font-bold text-gray-900 text-lg tracking-wide hover:text-blue-600 transition"
        >
          among no tao
        </Link>

        <div class="flex items-center text-sm text-gray-500 gap-2">
          <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M9 5l7 7-7 7"
            ></path>
          </svg>
          <span class="font-medium text-gray-900">
            <slot name="desktop-breadcrumb"></slot>
          </span>
        </div>

        <div class="ml-auto flex items-center gap-3">
          <!-- Admin: Dropdown Button -->
          <div v-if="user?.role === 'admin'" class="relative">
            <button
              @click="showAdminMenu = !showAdminMenu"
              class="flex items-center gap-1.5 text-sm font-medium text-gray-700 hover:text-blue-600 transition"
            >
              {{ user.name }}
              <svg
                class="w-4 h-4 text-gray-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M19 9l-7 7-7-7"
                />
              </svg>
            </button>
            <div
              v-if="showAdminMenu"
              class="absolute right-0 top-full mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-50 animate-fade-in-down"
            >
              <Link
                href="/dashboard"
                class="block w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-blue-600 transition"
                @click="showAdminMenu = false"
              >
                統計面板
              </Link>
              <Link
                href="/line-users"
                class="block w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-green-600 transition"
                @click="showAdminMenu = false"
              >
                使用者管理
              </Link>
              <Link
                href="/logout"
                method="post"
                as="button"
                class="block w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-red-600 transition"
              >
                登出
              </Link>
            </div>
            <div
              v-if="showAdminMenu"
              @click="showAdminMenu = false"
              class="fixed inset-0 z-40"
              style="background: transparent"
            ></div>
          </div>
          <!-- Non-Admin: Name with Badge + Logout -->
          <div v-else-if="user" class="text-sm font-medium text-gray-700 flex items-center gap-2">
            <span class="bg-blue-100 text-blue-800 py-1 px-3 rounded-full text-xs">田調人員</span>
            {{ user.name }}
          </div>
          <Link
            v-if="user && user.role !== 'admin'"
            href="/logout"
            method="post"
            as="button"
            class="text-sm text-gray-500 hover:text-red-600"
          >
            登出
          </Link>
          <Link
            v-if="!user"
            :href="loginUrl"
            class="text-sm text-blue-600 hover:text-blue-700 font-medium"
          >
            登入
          </Link>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

// 直接在元件內取得 User 狀態，減少父層傳遞 props 的負擔
const page = usePage()
const user = computed(() => page.props.auth?.user)

// 計算登入 URL，包含當前頁面作為 redirect 參數
const loginUrl = computed(() => {
  if (typeof window === 'undefined') return '/login'
  const currentUrl = window.location.pathname + window.location.search
  return `/login?redirect=${encodeURIComponent(currentUrl)}`
})

// Admin dropdown state
const showAdminMenu = ref(false)
const showMobileAdminMenu = ref(false)
</script>

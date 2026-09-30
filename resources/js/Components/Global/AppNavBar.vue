<template>
  <header
    class="z-30"
    :class="stickyMobile ? 'sticky top-4' : 'relative lg:sticky lg:top-4'"
  >
    <div class="container mx-auto max-w-7xl rounded-2xl border border-gray-200 bg-white shadow-sm">
      <!-- Mobile navigation -->
      <div class="flex h-16 w-full items-center gap-2 px-3 lg:hidden">
        <div class="flex min-w-0 flex-1 items-center gap-2">
          <Link
            v-if="mobileBackUrl === '/'"
            href="/"
            data-testid="nav-brand"
            class="shrink-0 text-elder-name font-bold text-elder-text"
          >
            among no tao
          </Link>
          <template v-else>
            <Link
              :href="mobileBackUrl"
              :aria-label="`返回${mobileBackText}`"
              data-testid="nav-back-button"
              class="inline-flex min-h-touch-secondary shrink-0 items-center gap-1 whitespace-nowrap rounded-xl border-2 border-blue-700 bg-white px-4 text-elder-body font-bold text-blue-700 hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-300"
            >
              ← {{ mobileBackText }}
            </Link>
            <span
              v-if="showMobileTitle"
              data-testid="nav-mobile-title"
              class="min-w-0 truncate text-elder-name font-bold text-elder-text"
            >
              {{ pageTitle }}
            </span>
          </template>
        </div>

        <div class="flex shrink-0 items-center gap-2">
          <slot name="mobile-actions" />
          <div class="relative">
            <button
              v-if="user"
              type="button"
              aria-label="我的帳號"
              data-testid="nav-user-button-mobile"
              class="flex h-12 w-12 items-center justify-center rounded-full border border-blue-100 bg-blue-50 text-blue-700 hover:bg-blue-100 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-300"
              @click="showMobileUserMenu = !showMobileUserMenu"
            >
              <svg aria-hidden="true" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </button>
            <Link
              v-else
              :href="loginUrl"
              data-testid="nav-login-mobile"
              class="inline-flex min-h-touch-secondary items-center rounded-xl px-4 text-elder-body font-bold text-blue-700 hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-300"
            >
              登入
            </Link>
            <UserMenuDropdown
              v-if="showMobileUserMenu && user"
              :user="user"
              :showUserInfo="true"
              @close="showMobileUserMenu = false"
            />
          </div>
        </div>
      </div>

      <!-- Desktop navigation -->
      <div class="hidden h-[72px] w-full items-center gap-4 px-5 lg:flex">
        <Link href="/" class="shrink-0 text-elder-name font-bold text-elder-text">
          among no tao
        </Link>
        <nav aria-label="主要導覽" class="ml-3 flex shrink-0 gap-1">
          <Link
            href="/"
            :aria-current="isHomePage ? 'page' : undefined"
            :class="desktopNavClass(isHomePage)"
          >
            首頁
          </Link>
          <Link
            href="/fishs"
            :aria-current="isFishPage ? 'page' : undefined"
            :class="desktopNavClass(isFishPage)"
          >
            魚類圖鑑
          </Link>
        </nav>

        <div class="flex min-w-0 flex-1 items-center">
          <slot name="desktop-nav">
            <Link
              v-if="mobileBackUrl !== '/'"
              :href="mobileBackUrl"
              :aria-label="`返回${mobileBackText}`"
              data-testid="nav-back-button-desktop"
              class="inline-flex min-h-touch-secondary items-center gap-1 whitespace-nowrap rounded-xl border-2 border-blue-700 bg-white px-4 text-elder-body font-bold text-blue-700 hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-300"
            >
              ← {{ mobileBackText }}
            </Link>
          </slot>
        </div>

        <div class="ml-auto flex shrink-0 items-center gap-3">
          <div v-if="user" class="relative">
            <button
              type="button"
              aria-label="我的帳號"
              data-testid="nav-user-button-desktop"
              class="flex h-12 w-12 items-center justify-center rounded-full border border-blue-100 bg-blue-50 text-blue-700 hover:bg-blue-100 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-300"
              @click="showDesktopUserMenu = !showDesktopUserMenu"
            >
              <svg aria-hidden="true" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </button>
            <UserMenuDropdown
              v-if="showDesktopUserMenu"
              :user="user"
              :showUserInfo="true"
              @close="showDesktopUserMenu = false"
            />
          </div>
          <Link
            v-else
            :href="loginUrl"
            data-testid="nav-login-desktop"
            class="inline-flex min-h-touch-secondary items-center rounded-xl px-4 text-elder-body font-bold text-blue-700 hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-300"
          >
            登入
          </Link>
        </div>
      </div>

      <slot name="header-extension" />
    </div>
  </header>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import UserMenuDropdown from '@/Components/Global/UserMenuDropdown.vue'

const props = defineProps({
  pageTitle: { type: String, default: '基本資料' },
  breadcrumbPage: { type: String, default: '' },
  mobileBackUrl: { type: String, default: '/fishs' },
  mobileBackText: { type: String, default: '首頁' },
  stickyMobile: { type: Boolean, default: true },
  showMobileTitle: { type: Boolean, default: true },
})

const page = usePage()
const user = computed(() => page.props.auth?.user)
const currentUrl = computed(() => page.url || '')
const isHomePage = computed(() => currentUrl.value === '/')
const isFishPage = computed(
  () => currentUrl.value.startsWith('/fishs') || currentUrl.value.startsWith('/fish/')
)

const desktopNavClass = (active) => [
  'inline-flex min-h-touch-secondary items-center rounded-xl px-4 text-elder-body font-bold',
  active
    ? 'bg-blue-50 text-blue-700 shadow-[inset_0_-3px_0_#1d4ed8]'
    : 'text-elder-subtext hover:bg-gray-100',
]

const loginUrl = computed(() => {
  if (typeof window === 'undefined') return '/login'
  const url = window.location.pathname + window.location.search
  return `/login?redirect=${encodeURIComponent(url)}`
})

const showMobileUserMenu = ref(false)
const showDesktopUserMenu = ref(false)
</script>

<template>
  <header class="z-30" :class="stickyMobile ? 'sticky top-4' : 'relative lg:sticky lg:top-4'">
    <div class="container mx-auto max-w-7xl border-b border-rule bg-paper">
      <!-- Mobile navigation -->
      <div class="flex h-16 w-full items-center gap-2 px-3 lg:hidden">
        <div class="flex min-w-0 flex-1 items-center gap-2">
          <Link
            v-if="mobileBackUrl === '/'"
            href="/"
            data-testid="nav-brand"
            class="shrink-0 font-serif text-elder-name tracking-wider text-elder-text"
          >
            among no tao
          </Link>
          <template v-else>
            <Link
              :href="mobileBackUrl"
              :aria-label="`返回${mobileBackText}`"
              data-testid="nav-back-button"
              class="inline-flex min-h-touch-secondary shrink-0 items-center gap-1 whitespace-nowrap rounded-xl border border-brand bg-paper px-4 text-elder-body font-bold text-brand hover:bg-paper-raised focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/30"
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
              class="flex h-12 w-12 items-center justify-center rounded-full border border-brand bg-paper-raised text-brand hover:bg-rule focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/30"
              @click="showMobileUserMenu = !showMobileUserMenu"
            >
              <svg
                aria-hidden="true"
                class="h-6 w-6"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                />
              </svg>
            </button>
            <Link
              v-else
              :href="loginUrl"
              data-testid="nav-login-mobile"
              class="inline-flex min-h-touch-secondary items-center px-4 text-elder-body font-bold text-brand underline decoration-2 underline-offset-8 hover:text-brand-dark focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/30"
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
        <Link
          href="/"
          data-testid="nav-brand-desktop"
          class="shrink-0 font-serif text-elder-title tracking-wider text-elder-text"
        >
          among no tao
        </Link>

        <div class="flex min-w-0 flex-1 items-center">
          <slot name="desktop-nav">
            <Link
              v-if="mobileBackUrl !== '/' && mobileBackUrl !== '/fishs'"
              :href="mobileBackUrl"
              :aria-label="`返回${mobileBackText}`"
              data-testid="nav-back-button-desktop"
              class="inline-flex min-h-touch-secondary items-center gap-1 whitespace-nowrap rounded-xl border border-brand bg-paper px-4 text-elder-body font-bold text-brand hover:bg-paper-raised focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/30"
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
              class="flex h-12 w-12 items-center justify-center rounded-full border border-brand bg-paper-raised text-brand hover:bg-rule focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/30"
              @click="showDesktopUserMenu = !showDesktopUserMenu"
            >
              <svg
                aria-hidden="true"
                class="h-6 w-6"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                />
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
            class="inline-flex min-h-touch-secondary items-center px-4 text-elder-body font-bold text-brand underline decoration-2 underline-offset-8 hover:text-brand-dark focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/30"
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
const loginUrl = computed(() => {
  if (typeof window === 'undefined') return '/login'
  const url = window.location.pathname + window.location.search
  return `/login?redirect=${encodeURIComponent(url)}`
})

const showMobileUserMenu = ref(false)
const showDesktopUserMenu = ref(false)
</script>

<template>
  <Head title="登入" />

  <div class="flex min-h-screen flex-col bg-paper px-6 pt-10">
    <main class="mx-auto w-full max-w-md flex-1">
      <header class="text-center">
        <a href="/" class="font-serif text-elder-title font-bold text-elder-text focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/30">among no tao</a>
        <p class="mt-2 text-elder-body text-elder-subtext">請先登入，讓我們認識你</p>
      </header>

      <div role="tablist" aria-label="登入方式" class="mt-6 grid grid-cols-2 gap-2">
        <button
          v-for="tab in TABS"
          :id="tabId(tab.key)"
          :key="tab.key"
          type="button"
          role="tab"
          :aria-selected="activeTab === tab.key"
          :aria-controls="panelId(tab.key)"
          :tabindex="activeTab === tab.key ? 0 : -1"
          :class="[
            'min-h-touch-secondary rounded-lg text-elder-body focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/30',
            activeTab === tab.key
              ? 'border-2 border-brand font-bold text-brand'
              : 'border border-rule text-elder-subtext hover:text-elder-text',
          ]"
          @click="selectTab(tab.key)"
          @keydown="onTabKeydown($event, tab.key)"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- 族人登入 -->
      <section
        v-show="activeTab === 'line'"
        :id="panelId('line')"
        role="tabpanel"
        :aria-labelledby="tabId('line')"
        class="mt-6 space-y-4"
      >
        <p class="text-center text-elder-body text-elder-text">請使用 LINE 登入</p>
        <a
          :href="lineLoginUrl"
          class="flex min-h-touch-primary w-full items-center justify-center gap-3 rounded-xl px-4 text-elder-name font-bold text-white transition hover:opacity-90 active:scale-95 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/30"
          style="background-color: #06c755"
        >
          <!-- LINE 官方 icon -->
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-6 w-6 fill-white">
            <path
              d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.627-.63h2.386c.349 0 .63.285.63.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.105.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.627-.63.349 0 .631.285.631.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.281.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"
            />
          </svg>
          使用 LINE 登入
        </a>
        <p class="text-center text-elder-aux text-elder-subtext">第一次登入，管理員確認後，就可以觀看詳細資料。</p>
      </section>

      <!-- 管理員登入 -->
      <section
        v-show="activeTab === 'admin'"
        :id="panelId('admin')"
        role="tabpanel"
        :aria-labelledby="tabId('admin')"
        class="mt-6"
      >
        <form class="space-y-3" @submit.prevent="submit">
          <p v-if="emailError" data-testid="login-error" role="alert" class="text-elder-body text-red-700">
            {{ emailError }}
          </p>
          <div>
            <label for="email" class="sr-only">Email</label>
            <input
              id="email"
              v-model="form.email"
              name="email"
              type="text"
              autocomplete="email"
              required
              :class="[INPUT_CLASS, emailError ? 'border-red-700' : 'border-rule-strong']"
              placeholder="管理員 Email"
            />
          </div>
          <div>
            <label for="password" class="sr-only">密碼</label>
            <input
              id="password"
              v-model="form.password"
              name="password"
              type="password"
              autocomplete="current-password"
              required
              :class="[INPUT_CLASS, 'border-rule-strong']"
              placeholder="密碼"
            />
          </div>
          <button
            type="submit"
            :disabled="form.processing"
            class="flex min-h-touch-primary w-full items-center justify-center rounded-xl bg-brand px-4 text-elder-name font-bold text-white transition hover:bg-brand-dark focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/30 disabled:opacity-50"
          >
            {{ form.processing ? '登入中…' : '管理員登入' }}
          </button>
        </form>
      </section>
    </main>

    <AppFooter />
  </div>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { computed, nextTick, ref, watch } from 'vue'
import AppFooter from '@/Components/Global/AppFooter.vue'

const TABS = [
  { key: 'line', label: '族人登入' },
  { key: 'admin', label: '管理員登入' },
]
const INPUT_CLASS =
  'block w-full min-h-[52px] rounded-lg border bg-paper-raised px-3 text-elder-body text-elder-text placeholder-elder-subtext focus:outline-none focus-visible:ring-4 focus-visible:ring-brand/30'

const tabId = (key) => `login-tab-${key}`
const panelId = (key) => `login-panel-${key}`

// 登入後要回去的站內路徑；沒有就不帶，由後端決定（intended 或首頁）
const redirectParam = computed(() => {
  if (typeof window === 'undefined') return null
  return new URLSearchParams(window.location.search).get('redirect')
})

const withRedirect = (base) =>
  redirectParam.value ? `${base}?redirect=${encodeURIComponent(redirectParam.value)}` : base

const lineLoginUrl = computed(() => withRedirect('/auth/line'))

const form = useForm({
  email: '',
  password: '',
})

// 後端共用的 errors 每個欄位是訊息陣列（HandleInertiaRequests::share），這裡取第一則顯示
const emailError = computed(() => {
  const message = form.errors.email
  return Array.isArray(message) ? message[0] : message
})

// 有登入錯誤（進頁面時就有，或送出後才收到）就停在管理員分頁，錯誤才看得到。
// 連續收到相同錯誤時 emailError 不變，所以送出結束（processing 變回 false）時也要檢查
const activeTab = ref(emailError.value ? 'admin' : 'line')
watch([emailError, () => form.processing], () => {
  if (emailError.value && !form.processing) activeTab.value = 'admin'
})

const selectTab = (key) => {
  activeTab.value = key
}

// 方向鍵／Home／End 切換並選取分頁，焦點跟著移動（WAI-ARIA tabs 慣例）
const onTabKeydown = (event, key) => {
  const index = TABS.findIndex((tab) => tab.key === key)
  const targets = {
    ArrowRight: (index + 1) % TABS.length,
    ArrowLeft: (index - 1 + TABS.length) % TABS.length,
    Home: 0,
    End: TABS.length - 1,
  }
  if (!(event.key in targets)) return
  event.preventDefault()
  const nextKey = TABS[targets[event.key]].key
  selectTab(nextKey)
  nextTick(() => document.getElementById(tabId(nextKey))?.focus())
}

const submit = () => {
  form.post(withRedirect('/login'))
}
</script>

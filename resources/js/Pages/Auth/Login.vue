<template>
  <Head title="登入" />

  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-6">
      <!-- 標題 -->
      <div class="text-center">
        <h2 class="text-3xl font-extrabold text-gray-900">among no tao</h2>
        <p class="mt-2 text-sm text-gray-500">田野調查魚類資料系統</p>
      </div>

      <!-- LINE Login 區塊 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
        <div class="text-center">
          <p class="text-sm font-medium text-gray-700">田調人員請使用 LINE 登入</p>
        </div>
        <a
          href="/auth/line"
          class="flex items-center justify-center gap-3 w-full py-3 px-4 rounded-lg text-white font-medium text-sm transition hover:opacity-90 active:scale-95"
          style="background-color: #06c755"
        >
          <!-- LINE 官方 icon -->
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-5 h-5 fill-white">
            <path
              d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.627-.63h2.386c.349 0 .63.285.63.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.105.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.627-.63.349 0 .631.285.631.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.281.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"
            />
          </svg>
          使用 LINE 登入
        </a>
        <p class="text-xs text-center text-gray-400">首次登入將自動建立帳號（viewer 權限）</p>
      </div>

      <!-- 分隔線 -->
      <div class="flex items-center gap-3">
        <div class="flex-1 border-t border-gray-200"></div>
        <span class="text-xs text-gray-400">管理員</span>
        <div class="flex-1 border-t border-gray-200"></div>
      </div>

      <!-- 管理員登入區塊 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form class="space-y-4" @submit.prevent="submit">
          <div class="space-y-2">
            <div>
              <label for="email" class="sr-only">Email</label>
              <input
                id="email"
                v-model="form.email"
                name="email"
                type="text"
                autocomplete="email"
                required
                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-lg placeholder-gray-400 text-gray-900 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
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
                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-lg placeholder-gray-400 text-gray-900 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="密碼"
              />
            </div>
          </div>

          <div v-if="form.errors.email" class="text-red-500 text-xs text-center">
            {{ form.errors.email }}
          </div>

          <button
            type="submit"
            :disabled="form.processing"
            class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 transition"
          >
            {{ form.processing ? '登入中…' : '管理員登入' }}
          </button>
        </form>
      </div>

      <div class="text-center">
        <a href="/" class="text-xs text-gray-400 hover:text-gray-600">回首頁</a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

const redirectUrl = computed(() => {
  if (typeof window === 'undefined') return '/fishs'
  const urlParams = new URLSearchParams(window.location.search)
  return urlParams.get('redirect') || '/fishs'
})

const form = useForm({
  email: '',
  password: '',
})

const submit = () => {
  form.post(`/login?redirect=${encodeURIComponent(redirectUrl.value)}`)
}
</script>

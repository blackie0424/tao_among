<template>
  <Head title="登入" />

  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <div>
        <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
          管理員登入
        </h2>
      </div>
      <form class="mt-8 space-y-6" @submit.prevent="submit">
        <input type="hidden" name="remember" value="true" />
        <div class="rounded-md shadow-sm -space-y-px">
          <div>
            <label for="email" class="sr-only">帳號</label>
            <input
              id="email"
              v-model="form.email"
              name="email"
              type="text"
              required
              class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
              placeholder="帳號 (輸入 admin)"
            />
          </div>
          <div>
            <label for="password" class="sr-only">密碼</label>
            <input
              id="password"
              v-model="form.password"
              name="password"
              type="password"
              required
              class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
              placeholder="密碼"
            />
          </div>
        </div>

        <div v-if="form.errors.email" class="text-red-500 text-sm text-center">
            {{ form.errors.email }}
        </div>

        <div>
          <button
            type="submit"
            :disabled="form.processing"
            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
          >
            登入
          </button>
        </div>
      </form>
      
      <div class="text-center">
        <a href="/" class="text-sm text-blue-600 hover:text-blue-500">回首頁</a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3'

const form = useForm({
  email: '',
  password: '',
})

const submit = () => {
  form.post('/login')
}
</script>

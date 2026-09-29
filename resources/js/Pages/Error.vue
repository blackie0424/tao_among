<template>
  <FishAppLayout :pageTitle="'無法瀏覽'" mobileBackUrl="/" :showHeader="true" :showEditMenu="false">
    <section class="mx-auto max-w-2xl rounded-2xl border-2 border-gray-200 bg-white px-6 py-10 text-center shadow-sm sm:px-10">
      <p class="mb-3 text-lg font-semibold text-gray-500">{{ status }}</p>
      <h1 class="mb-5 text-3xl font-bold leading-tight text-gray-900">{{ content.title }}</h1>
      <p class="text-elder-body leading-relaxed text-gray-800">{{ content.description }}</p>
      <p class="mt-3 text-elder-body leading-relaxed text-gray-700">{{ content.guidance }}</p>
      <a
        href="/"
        class="mt-8 inline-flex min-h-touch-primary min-w-40 items-center justify-center rounded-xl bg-blue-700 px-8 py-3 text-elder-body font-bold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-amber-400 focus:ring-offset-2"
      >
        回首頁
      </a>
    </section>
  </FishAppLayout>
</template>

<script setup>
import { computed } from "vue"
import FishAppLayout from "@/Layouts/FishAppLayout.vue"

const props = defineProps({
  status: {
    type: Number,
    required: true,
  },
})

const messages = {
  403: {
    title: "權限不足",
    description: "你目前的帳號權限不足，無法瀏覽此頁面。",
    guidance: "如需開通權限，請聯繫管理者。",
  },
  404: {
    title: "找不到頁面",
    description: "你要找的頁面不存在或已被移除。",
    guidance: "請確認網址是否正確，或回到首頁繼續瀏覽。",
  },
  500: {
    title: "系統發生錯誤",
    description: "系統暫時無法處理你的要求。",
    guidance: "請稍後再試，或回到首頁。",
  },
  503: {
    title: "服務暫時無法使用",
    description: "系統目前正在維護或暫時忙碌。",
    guidance: "請稍後再試，或回到首頁。",
  },
}

const content = computed(() => messages[props.status] ?? messages[500])
</script>

<template>
  <Head title="雅美（達悟）族魚類圖鑑網" />

  <FishAppLayout :showHeader="false" pageTitle="首頁">
    <div id="index" class="flex flex-col items-center justify-center min-h-[70vh] w-full gap-6">
      <AnimatedText text="nivasilan ko a among" />

      <!-- 大型搜尋入口（B3）-->
      <form @submit.prevent="handleSearch" class="w-full max-w-sm flex gap-2 px-4">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="搜尋魚種名稱…"
          class="flex-1 min-h-touch-primary rounded-xl border-2 border-gray-300 px-4 text-elder-body text-elder-text focus:outline-none focus:border-blue-500 shadow-sm"
        />
        <button
          type="submit"
          class="flex items-center justify-center min-h-touch-primary px-5 rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition shadow-sm shrink-0"
          aria-label="搜尋魚種"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8" stroke-width="2" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" stroke-width="2" />
          </svg>
        </button>
      </form>

      <div class="flex gap-3 flex-wrap justify-center px-4">
        <button @click="goFishs" class="min-h-touch-primary px-6 rounded-xl bg-green-600 text-white text-elder-body font-bold hover:bg-green-700 transition shadow">
          瀏覽圖鑑
        </button>
        <button
          v-if="showInstallBtn"
          @click="installPWA"
          class="min-h-touch-primary px-6 rounded-xl bg-gray-600 text-white text-elder-body font-bold hover:bg-gray-700 transition shadow"
        >
          安裝 App
        </button>
      </div>
    </div>
  </FishAppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'
import AnimatedText from '@/Components/UI/AnimatedText.vue'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'

const searchQuery = ref('')

function handleSearch() {
  const q = searchQuery.value.trim()
  router.visit(q ? `/fishs?name=${encodeURIComponent(q)}` : '/fishs')
}

function goFishs() {
  router.visit('/fishs')
}

// PWA 安裝提示
const showInstallBtn = ref(false)
let deferredPrompt = null

onMounted(() => {
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault()
    deferredPrompt = e
    showInstallBtn.value = true
  })
})

function installPWA() {
  if (deferredPrompt) {
    deferredPrompt.prompt()
    deferredPrompt.userChoice.then(() => {
      showInstallBtn.value = false
      deferredPrompt = null
    })
  }
}
</script>

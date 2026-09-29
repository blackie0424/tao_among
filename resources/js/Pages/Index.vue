<template>
  <Head title="雅美（達悟）族魚類圖鑑網" />

  <FishAppLayout :showHeader="false" pageTitle="首頁">

    <!-- 主要內容區 -->
    <div class="mx-auto max-w-4xl px-4 py-8 space-y-10">

      <!-- 快速入口 -->
      <div class="flex gap-3 flex-wrap justify-center">
        <button
          v-if="showInstallBtn"
          @click="installPWA"
          class="min-h-touch-primary px-6 rounded-xl bg-gray-600 text-white text-elder-body font-bold hover:bg-gray-700 transition shadow"
        >
          安裝 App
        </button>
      </div>

      <!-- 知識分類 -->
      <section>
        <h2 class="text-center text-lg font-bold text-gray-800 mb-4">探索蘭嶼</h2>
        <div class="flex flex-col gap-4">
          <div
            v-for="category in knowledgeCategories"
            :key="category.id"
            class="bg-white rounded-xl shadow-md overflow-hidden cursor-pointer hover:shadow-lg transition"
            @click="goToCategory(category)"
          >
            <div v-if="category.image_url" class="relative aspect-video w-full">
              <img
                :src="category.image_url"
                :alt="category.title"
                class="w-full h-full object-cover"
              />
            </div>
            <div class="p-3 text-center">
              <h3 class="text-elder-body font-semibold text-gray-800">{{ category.title }}</h3>
            </div>
          </div>
        </div>
      </section>

    </div>
  </FishAppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'

// Knowledge categories
const knowledgeCategories = ref([])

onMounted(async () => {
  // 取得已發布的主題分類
  try {
    const response = await fetch('/prefix/api/topics')
    if (response.ok) {
      knowledgeCategories.value = await response.json()
    }
  } catch (error) {
    console.error('Failed to fetch topics:', error)
  }
})

// Navigation
function goToCategory(category) {
  if (category.is_fish_category) {
    router.visit('/fishs')
  } else {
    router.visit(`/topics/${category.slug}`)
  }
}

// PWA
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

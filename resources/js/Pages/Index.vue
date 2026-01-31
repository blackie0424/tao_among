<template>
    <Head title="雅美（達悟）族魚類圖鑑網" />

  <div class="container mx-auto p-4">
    <div id="index" class="flex flex-col items-center justify-center min-h-[80vh] w-full">
      <AnimatedText text="nivasilan ko a among" />
      <button
        v-if="showInstallBtn"
        @click="installPWA"
        class="mt-6 px-4 py-2 bg-green-600 text-white rounded"
      >
        安裝 App
      </button>
      <button @click="goFishs" class="mt-6 px-4 py-2 bg-green-600 text-white rounded">
        瀏覽魚類圖鑑
      </button>
    </div>
    <footer class="mt-8 text-center text-gray-500">Copyright © 2025 Chungyueh</footer>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AnimatedText from '@/Components/AnimatedText.vue'

const headerM = '/images/header-m.png'
const headerS = '/images/header-s.png'
const headerL = '/images/header-l.jpg'

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

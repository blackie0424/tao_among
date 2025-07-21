<template>
  <div class="container mx-auto p-4">
    <div id="index">
      <picture>
        <source media="(min-width: 1025px)" :srcset="headerM" />
        <source media="(min-width: 481px)" :srcset="headerS" />
        <img :src="headerL" class="responsive-img" loading="lazy" />
      </picture>
      <div class="index-content">
        <h1>nivasilan ko a among</h1>
      </div>
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

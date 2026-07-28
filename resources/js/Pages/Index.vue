<template>
  <Head title="雅美（達悟）族魚類圖鑑網" />

  <FishAppLayout :showHeader="false" pageTitle="首頁">

    <!-- 全寬投影片 -->
    <section v-if="slides.length" class="relative w-full bg-black overflow-hidden" style="height: 56vw; max-height: 520px; min-height: 220px;">
      <transition-group name="fade" tag="div" class="absolute inset-0">
        <div
          v-for="(slide, i) in slides"
          v-show="i === current"
          :key="slide.id"
          class="absolute inset-0"
        >
          <!-- YouTube embed -->
          <iframe
            v-if="slide.media_type === 'youtube'"
            :src="toEmbedUrl(slide.media_path)"
            class="w-full h-full"
            frameborder="0"
            allow="autoplay; encrypted-media"
            allowfullscreen
          />
          <!-- Photo -->
          <img
            v-else-if="slide.media_url"
            :src="slide.media_url"
            :alt="slide.title"
            class="w-full h-full object-cover"
          />
          <div v-else class="w-full h-full bg-gray-800" />

          <!-- Caption overlay -->
          <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent px-6 py-5">
            <h2 class="text-white text-xl font-bold leading-tight">{{ slide.title }}</h2>
            <p v-if="slide.body" class="text-white/80 text-sm mt-1 line-clamp-2">{{ slide.body }}</p>
          </div>
        </div>
      </transition-group>

      <!-- Prev / Next buttons -->
      <button
        v-if="slides.length > 1"
        class="absolute left-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-black/40 text-white flex items-center justify-center hover:bg-black/60 transition"
        aria-label="上一張"
        @click="prev"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <button
        v-if="slides.length > 1"
        class="absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-black/40 text-white flex items-center justify-center hover:bg-black/60 transition"
        aria-label="下一張"
        @click="next"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </button>

      <!-- Dots -->
      <div v-if="slides.length > 1" class="absolute bottom-3 right-4 flex gap-1.5">
        <button
          v-for="(_, i) in slides"
          :key="i"
          class="w-2 h-2 rounded-full transition"
          :class="i === current ? 'bg-white' : 'bg-white/40'"
          :aria-label="`第 ${i + 1} 張`"
          @click="current = i"
        />
      </div>
    </section>

    <!-- Hero（無投影片時顯示標題動畫） -->
    <div v-else class="flex items-center justify-center py-16">
      <AnimatedText text="nivasilan ko a among" />
    </div>

    <!-- 主要內容區 -->
    <div class="mx-auto max-w-4xl px-4 py-8 space-y-10">

      <!-- 快速入口 -->
      <div class="flex gap-3 flex-wrap justify-center">
        <button
          @click="goFishs"
          class="min-h-touch-primary px-6 rounded-xl bg-green-600 text-white text-elder-body font-bold hover:bg-green-700 transition shadow"
        >
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

      <!-- 部落地圖 -->
      <section>
        <h2 class="text-center text-lg font-bold text-gray-800 mb-4">依部落瀏覽</h2>
        <div class="flex justify-center">
          <LanyuMap @tribe-click="onTribeClick" />
        </div>
        <p class="text-center text-xs text-gray-400 mt-2">點選部落名稱可查看該部落的魚類紀錄</p>
      </section>

    </div>
  </FishAppLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import AnimatedText from '@/Components/UI/AnimatedText.vue'
import LanyuMap from '@/Components/Homepage/LanyuMap.vue'

const props = defineProps({
  slides: {
    type: Array,
    default: () => [],
  },
})

// Slideshow
const current = ref(0)
let autoTimer = null

function next() {
  current.value = (current.value + 1) % props.slides.length
  resetTimer()
}

function prev() {
  current.value = (current.value - 1 + props.slides.length) % props.slides.length
  resetTimer()
}

function resetTimer() {
  if (autoTimer) clearInterval(autoTimer)
  if (props.slides.length > 1) {
    autoTimer = setInterval(next, 5000)
  }
}

onMounted(() => {
  if (props.slides.length > 1) resetTimer()
})
onUnmounted(() => {
  if (autoTimer) clearInterval(autoTimer)
})

function toEmbedUrl(url) {
  if (!url) return ''
  const m = url.match(/(?:v=|youtu\.be\/)([A-Za-z0-9_-]{11})/)
  return m ? `https://www.youtube.com/embed/${m[1]}?autoplay=0` : url
}

// Navigation
function goFishs() {
  router.visit('/fishs')
}

function onTribeClick(tribe) {
  router.visit(`/fishs?tribe=${tribe}`)
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

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.5s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>

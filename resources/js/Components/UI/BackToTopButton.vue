<template>
  <button
    v-if="visible"
    type="button"
    aria-label="回到頂端"
    class="fixed bottom-6 right-6 z-40 flex h-14 w-14 items-center justify-center rounded-full border border-brand bg-brand text-white hover:bg-brand-dark focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/30"
    @click="scrollToTop"
  >
    <svg
      aria-hidden="true"
      class="h-6 w-6"
      fill="none"
      stroke="currentColor"
      stroke-width="2"
      viewBox="0 0 24 24"
    >
      <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
    </svg>
  </button>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

const visible = ref(false)

// 捲動超過一個螢幕高度才出現
const updateVisible = () => {
  visible.value = window.scrollY > window.innerHeight
}

const scrollToTop = () => window.scrollTo({ top: 0, behavior: 'smooth' })

onMounted(() => {
  updateVisible()
  window.addEventListener('scroll', updateVisible, { passive: true })
})
onBeforeUnmount(() => window.removeEventListener('scroll', updateVisible))
</script>

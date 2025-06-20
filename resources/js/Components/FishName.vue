<template>
  <div class="section section-name w-full max-w-3xl p-4 mb-4 flex flex-col items-end relative">
    <div class="flex items-center justify-center w-full mb-4">
      <div class="text text-xl text-secondary">ngaran no among</div>
    </div>
    <!-- 魚名與 icon 水平排列 -->
    <div
      class="section-title text-2xl font-bold text-primary flex items-center justify-center w-full"
    >
      <span>{{ fish.name }}</span>
      <Volume class="ml-4" />
    </div>

    <div class="absolute right-0 top-0 h-full rounded-lg p-4">
      <div class="relative fishname-menu">
        <button
          class="ml-2 text-gray-500 hover:text-gray-700 flex-shrink-0"
          title="更多操作"
          @click.stop="toggleMenu"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-6 w-6"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <circle cx="12" cy="5" r="1" fill="currentColor" />
            <circle cx="12" cy="12" r="1" fill="currentColor" />
            <circle cx="12" cy="19" r="1" fill="currentColor" />
          </svg>
        </button>
        <div v-if="menuOpen" class="absolute right-0 mt-2 w-24 bg-white border rounded shadow z-50">
          <ul>
            <li class="px-4 py-2 hover:bg-gray-100 cursor-pointer text-base">編輯</li>
            <li
              @click="deleteFish"
              class="px-4 py-2 hover:bg-red-100 text-red-600 cursor-pointer text-base"
            >
              刪除
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

import Volume from '@/Components/Volume.vue'
const props = defineProps({
  fish: Object,
})

const menuOpen = ref(false)

function toggleMenu() {
  menuOpen.value = !menuOpen.value
}

async function deleteFish() {
  menuOpen.value = false
  if (!confirm('確定要刪除此魚類嗎？')) return
  const res = await fetch(`/prefix/api/fish/${props.fish.id}`, { method: 'DELETE' })
  if (res.ok) {
    // 可重新整理或導向
    window.location.href = '/'
  } else {
    alert('刪除失敗')
  }
}

// 點擊外部自動關閉選單
function handleClickOutside(event) {
  if (!event.target.closest('.fishname-menu')) {
    menuOpen.value = false
  }
}
onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
  <div class="absolute right-0 top-0 h-full rounded-lg p-4">
    <div class="relative overflow-menu">
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
            @click="deleteData"
            class="px-4 py-2 hover:bg-red-100 text-red-600 cursor-pointer text-base"
          >
            刪除
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

const menuOpen = ref(false)
const props = defineProps({
  apiUrl: String,
  redirectUrl: String,
})

function toggleMenu() {
  menuOpen.value = !menuOpen.value
}

async function deleteData() {
  menuOpen.value = false
  if (!confirm('確定要刪除此魚類嗎？')) return
  const res = await fetch(props.apiUrl, { method: 'DELETE' })
  if (res.ok) {
    // 可重新整理或導向
    window.location.href = props.redirectUrl
  } else {
    alert('刪除失敗')
  }
}

// 點擊外部自動關閉選單
function handleClickOutside(event) {
  if (!event.target.closest('.overflow-menu')) {
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

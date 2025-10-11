<template>
  <div class="flex right-0 top-0 h-full rounded-lg p-2">
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
          <li
            v-if="showEdit"
            @click="editData"
            class="px-4 py-2 hover:bg-gray-100 cursor-pointer text-base"
          >
            編輯
          </li>
          <li
            v-if="showDelete"
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
import { router } from '@inertiajs/vue3'

const menuOpen = ref(false)
const props = defineProps({
  apiUrl: { type: String, required: true },
  redirectUrl: { type: String, default: '' },
  fishId: { type: String, required: true },
  showEdit: { type: Boolean, default: true },
  showDelete: { type: Boolean, default: true },
  editUrl: { type: String, default: '' }, // 新增：外部可設定編輯連結
})

const emit = defineEmits(['deleted'])

function toggleMenu() {
  menuOpen.value = !menuOpen.value
}

// 編輯連結由外部設定，預設為 /fish/{fishId}/edit
function editData() {
  menuOpen.value = false
  const url = props.editUrl || `/fish/${props.fishId}/edit`
  router.visit(url)
}

function deleteData() {
  menuOpen.value = false
  if (!confirm('確定要刪除此項目嗎？')) return

  router.delete(props.apiUrl, {
    onSuccess: () => {
      if (props.redirectUrl) {
        router.visit('/fishs')
      } else {
        emit('deleted')
      }
    },
    onError: (errors) => {
      console.error('Delete errors:', errors)
      alert('刪除失敗：' + (errors.message || '未知錯誤'))
    },
  })
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

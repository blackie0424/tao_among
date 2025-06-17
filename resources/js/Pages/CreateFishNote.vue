<template>
  <div class="container mx-auto p-4">
    <Breadcrumb second="新增知識" />
    <!-- 魚圖片與名稱 -->
    <div class="flex flex-col items-center mb-8">
      <div class="w-256 h-256 mb-2">
        <FishImage
          :image="fish.image"
          :name="fish.name"
          class="w-full h-full object-cover rounded-full"
        />
      </div>
      <FishName :name="fish.name" class="text-2xl font-bold" />
    </div>

    <form @submit.prevent="submitNote" class="space-y-6">
      <!-- 地區選擇 -->
      <div>
        <div class="font-semibold mb-2">選擇地區 <span class="text-red-500">*</span></div>
        <div class="flex flex-wrap gap-3">
          <button
            v-for="loc in locates"
            :key="loc.value"
            type="button"
            :class="[
              'px-4 py-2 rounded-full border transition',
              selectedLocate === loc.value
                ? 'bg-yellow-500 text-white border-yellow-500'
                : 'bg-gray-100 border-gray-300 hover:bg-yellow-100',
            ]"
            @click="selectedLocate = loc.value"
          >
            {{ loc.label }}
          </button>
        </div>
        <div v-if="locateError" class="text-red-500 text-sm mt-1">請選擇地區</div>
      </div>

      <!-- 知識類別選擇 -->
      <div>
        <div class="font-semibold mb-2">選擇知識類別 <span class="text-red-500">*</span></div>
        <div class="flex flex-wrap gap-3">
          <button
            v-for="type in noteTypes"
            :key="type"
            type="button"
            :class="[
              'px-4 py-2 rounded-full border transition',
              selectedType === type
                ? 'bg-blue-500 text-white border-blue-500'
                : 'bg-gray-100 border-gray-300 hover:bg-blue-100',
            ]"
            @click="selectedType = type"
          >
            {{ type }}
          </button>
        </div>
        <div v-if="typeError" class="text-red-500 text-sm mt-1">請選擇知識類別</div>
      </div>

      <!-- 筆記輸入 -->
      <div>
        <div class="font-semibold mb-2">知識內容 <span class="text-red-500">*</span></div>
        <textarea
          v-model="note"
          class="w-full border rounded p-3 focus:outline-none focus:ring-2 focus:ring-blue-200"
          rows="4"
          placeholder="請輸入知識內容"
        ></textarea>
        <div v-if="noteError" class="text-red-500 text-sm mt-1">請輸入知識內容</div>
      </div>

      <div class="flex gap-3">
        <button
          type="submit"
          class="flex-1 bg-green-600 text-white py-2 rounded font-bold hover:bg-green-700 transition"
          :disabled="submitting"
        >
          送出
        </button>
        <button
          v-if="showSuccess"
          type="button"
          class="flex-1 bg-gray-400 text-white py-2 rounded font-bold hover:bg-gray-500 transition"
          @click="goToFishDetail"
        >
          返回魚類頁
        </button>
      </div>
      <div v-if="submitError" class="text-red-600 mt-2">{{ submitError }}</div>
      <div v-if="showSuccess" class="text-green-600 mt-2">
        新增成功！你可以繼續新增或返回魚類頁。
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import Breadcrumb from '@/Components/Global/Breadcrumb.vue'
import { router } from '@inertiajs/vue3'
import FishImage from '@/Components/FishImage.vue'
import FishName from '@/Components/FishName.vue'

const props = defineProps({
  fish: Object, // { id, name, image }
})

const locates = [
  { value: 'Imorod', label: 'Imorod' },
  { value: 'Iratay', label: 'Iratay' },
  { value: 'Yayo', label: 'Yayo' },
  { value: 'Iraraley', label: 'Iraraley' },
  { value: 'Iranmeylek', label: 'Iranmeylek' },
  { value: 'Ivalino', label: 'Ivalino' },
]
const noteTypes = ['外觀特徵', '分布地區', '傳統價值', '經驗分享', '相關故事', '游棲生態']

const selectedLocate = ref('')
const selectedType = ref('')
const note = ref('')
const submitting = ref(false)
const showSuccess = ref(false)
const submitError = ref('')
const locateError = ref(false)
const typeError = ref(false)
const noteError = ref(false)

async function submitNote() {
  locateError.value = !selectedLocate.value
  typeError.value = !selectedType.value
  noteError.value = !note.value.trim()
  submitError.value = ''
  showSuccess.value = false

  if (locateError.value || typeError.value || noteError.value) return

  submitting.value = true
  try {
    const res = await fetch(`/prefix/api/fish/${props.fish.id}/note`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        note: note.value,
        note_type: selectedType.value,
        locate: selectedLocate.value.toLowerCase(),
      }),
    })
    const data = await res.json()
    if (!res.ok) throw new Error(data.message || '知識新增失敗')
    showSuccess.value = true
    // 清空表單
    note.value = ''
    selectedType.value = ''
    selectedLocate.value = ''
  } catch (e) {
    submitError.value = e.message || '知識新增失敗'
  } finally {
    submitting.value = false
  }
}

function goToFishDetail() {
  router.visit(`/fish/${props.fish.id}`)
}
</script>

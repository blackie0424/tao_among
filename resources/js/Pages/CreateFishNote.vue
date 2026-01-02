<template>
  <div class="container mx-auto p-4 relative">
    <TopNavBar
      :goBack="goBack"
      :submitNote="submitNote"
      :submitting="form.processing"
      title="新增魚類知識"
    />
    <div class="pt-16">
      <!-- 魚圖片與名稱 -->
      <div class="flex flex-col items-center mb-8">
        <div class="w-256 h-256 mb-2">
          <FishImage
            :image="fish.image"
            :name="fish.name"
            class="w-full h-full object-cover rounded-full"
          />
        </div>
        <FishName :fish-name="fish.name" class="text-2xl font-bold" />
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
                form.locate === loc.value
                  ? 'bg-yellow-500 text-white border-yellow-500'
                  : 'bg-gray-100 border-gray-300 hover:bg-yellow-100',
              ]"
              @click="form.locate = loc.value"
            >
              {{ loc.label }}
            </button>
          </div>
          <div v-if="form.errors.locate" class="text-red-500 text-sm mt-1">
            {{ form.errors.locate }}
          </div>
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
                form.note_type === type
                  ? 'bg-blue-500 text-white border-blue-500'
                  : 'bg-gray-100 border-gray-300 hover:bg-blue-100',
              ]"
              @click="form.note_type = type"
            >
              {{ type }}
            </button>
          </div>
          <div v-if="form.errors.note_type" class="text-red-500 text-sm mt-1">
            {{ form.errors.note_type }}
          </div>
        </div>

        <!-- 筆記輸入 -->
        <div>
          <div class="font-semibold mb-2">知識內容 <span class="text-red-500">*</span></div>
          <textarea
            v-model="form.note"
            class="w-full border rounded p-3 focus:outline-none focus:ring-2 focus:ring-blue-200"
            rows="4"
            placeholder="請輸入知識內容"
          ></textarea>
          <div v-if="form.errors.note" class="text-red-500 text-sm mt-1">
            {{ form.errors.note }}
          </div>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import FishImage from '@/Components/FishImage.vue'
import FishName from '@/Components/FishName.vue'
import TopNavBar from '@/Components/Global/TopNavBar.vue'

const props = defineProps({
  fish: Object, // { id, name, image }
  noteTypes: Array, // 從後端傳來的知識類別
})

const locates = [
  { value: 'Imorod', label: 'Imorod' },
  { value: 'Iratay', label: 'Iratay' },
  { value: 'Yayo', label: 'Yayo' },
  { value: 'Iraraley', label: 'Iraraley' },
  { value: 'Iranmeylek', label: 'Iranmeylek' },
  { value: 'Ivalino', label: 'Ivalino' },
]

// 使用 Inertia form
const form = useForm({
  locate: '',
  note_type: '',
  note: '',
})

function submitNote() {
  form.post(`/fish/${props.fish.id}/knowledge`, {
    onSuccess: () => {
      // 表單會自動 reset 或跳轉到列表頁
    },
  })
}

function goBack() {
  window.history.length > 1 ? window.history.back() : (window.location.href = '/')
}
</script>

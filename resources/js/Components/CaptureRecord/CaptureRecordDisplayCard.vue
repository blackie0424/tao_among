<template>
  <div
    class="rounded-xl border-2 overflow-hidden"
    :class="index % 2 === 0 ? 'bg-white border-teal-200' : 'bg-slate-50 border-slate-200'"
  >
    <!-- 卡片標題區：編號 -->
    <div
      class="px-4 py-3 flex items-center justify-between"
      :class="
        index % 2 === 0
          ? 'bg-teal-50 border-b border-teal-100'
          : 'bg-slate-100 border-b border-slate-200'
      "
    >
      <div class="flex items-center gap-3">
        <span
          class="inline-flex items-center justify-center w-8 h-8 rounded-full text-white font-bold text-lg"
          :class="index % 2 === 0 ? 'bg-teal-500' : 'bg-slate-500'"
        >
          {{ index + 1 }}
        </span>
        <span class="text-lg font-semibold text-gray-800">第 {{ index + 1 }} 筆紀錄</span>
      </div>
      <Link
        v-if="canEdit"
        :href="`/fish/${fishId}/capture-records/${record.id}/edit`"
        class="inline-flex items-center min-h-touch-primary px-3 text-elder-body text-blue-700 underline"
        >編輯</Link
      >
    </div>

    <!-- 卡片內容區 -->
    <div class="p-4 flex flex-col gap-4">
      <!-- 捕獲時間 -->
      <div v-if="record.capture_date" class="flex items-center text-base text-gray-900 font-medium">
        <svg
          class="w-5 h-5 mr-2 shrink-0 stroke-current"
          fill="none"
          viewBox="0 0 24 24"
          stroke-width="1.5"
          aria-hidden="true"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M6.75 3v2.25M17.25 3v2.25M3.75 8.25h16.5M4.5 5.25h15A.75.75 0 0 1 20.25 6v13.5a.75.75 0 0 1-.75.75h-15a.75.75 0 0 1-.75-.75V6a.75.75 0 0 1 .75-.75Z"
          />
        </svg>
        捕獲時間：{{ formatDate(record.capture_date) }}
      </div>

      <!-- 捕獲地點 -->
      <div
        v-if="record.location"
        class="flex flex-wrap items-center text-base text-gray-900 font-medium gap-1"
      >
        <svg
          class="w-5 h-5 mr-1 shrink-0 stroke-current"
          fill="none"
          viewBox="0 0 24 24"
          stroke-width="1.5"
          aria-hidden="true"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="m15 11.25-3 3m0 0-3-3m3 3V4.5M5.25 19.5h13.5"
          />
        </svg>
        捕獲地點：
        <span v-if="record.tribe" class="bg-gray-200 text-sm px-2 py-0.5 rounded">{{
          record.tribe
        }}</span>
        <span class="break-all">{{ record.location }}</span>
      </div>

      <!-- 捕獲方式 -->
      <div
        v-if="record.capture_method"
        class="flex items-center text-base text-gray-900 font-medium"
      >
        <svg
          class="w-5 h-5 mr-2 shrink-0 stroke-current"
          fill="none"
          viewBox="0 0 24 24"
          stroke-width="1.5"
          aria-hidden="true"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M12 21a8.25 8.25 0 0 0 8.25-8.25V6.108c0-.473-.384-.858-.858-.858H4.608a.858.858 0 0 0-.858.858v6.642A8.25 8.25 0 0 0 12 21Zm0-8.25h.008v.008H12v-.008Z"
          />
        </svg>
        捕獲方式：{{ record.capture_method }}
      </div>

      <!-- 捕獲照片 -->
      <LazyImage
        :src="record.image_url"
        :alt="`${fishName} 捕獲紀錄 ${index + 1}`"
        class="w-full h-auto object-cover rounded-lg shadow-sm border border-gray-200"
      />

      <!-- 捕獲說明 -->
      <div v-if="record.notes" class="bg-amber-50 rounded-lg p-4 border border-amber-200">
        <div class="flex items-start gap-2">
          <svg
            class="w-5 h-5 mt-0.5 shrink-0 stroke-current text-amber-800"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            aria-hidden="true"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M16.862 4.487 18.75 2.6a2.121 2.121 0 1 1 3 3L10.582 16.77a4.5 4.5 0 0 1-1.897 1.13l-2.263.754.754-2.263a4.5 4.5 0 0 1 1.13-1.897l8.556-8.556ZM18 14.25v4.5A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H9.75"
            />
          </svg>
          <div>
            <span class="text-base font-medium text-amber-800 block mb-1">捕獲說明</span>
            <p class="text-base text-gray-800 leading-relaxed whitespace-pre-line break-words">
              {{ record.notes }}
            </p>
          </div>
        </div>
      </div>
      <div
        v-if="record.session_notes"
        class="mt-3 bg-teal-50 rounded-lg p-4 border border-teal-200"
      >
        <div class="flex items-start gap-2">
          <svg
            class="w-5 h-5 mt-0.5 shrink-0 stroke-current text-teal-800"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            aria-hidden="true"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5a3.375 3.375 0 0 0-3.375 3.375v2.625m8.25 0H21a.75.75 0 0 1 .75.75v3a.75.75 0 0 1-.75.75h-1.5m0-4.5h-3m-5.25 0H9.75a.75.75 0 0 0-.75.75v3a.75.75 0 0 0 .75.75h1.5m0-4.5h3"
            />
          </svg>
          <div>
            <span class="text-base font-medium text-teal-800 block mb-1">情境備註</span>
            <p class="text-base text-gray-800 leading-relaxed whitespace-pre-line break-words">
              {{ record.session_notes }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import LazyImage from '@/Components/UI/LazyImage.vue'
import { formatDate } from '@/utils/formatDate'

defineProps({
  fishId: { type: [Number, String], default: null },
  canEdit: { type: Boolean, default: false },
  record: { type: Object, required: true },
  index: { type: Number, required: true },
  fishName: { type: String, default: '' },
})
</script>

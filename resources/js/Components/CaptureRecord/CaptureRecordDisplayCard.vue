<template>
  <div class="border border-rule-strong bg-paper">
    <!-- 卡片標題：捕獲日期 -->
    <div
      v-if="record.capture_date"
      data-testid="capture-card-title"
      class="px-4 pt-4 font-serif text-elder-body font-bold text-elder-text"
    >
      {{ formatDate(record.capture_date) }}
    </div>

    <!-- 卡片內容區 -->
    <div class="p-4 flex flex-col gap-4">
      <!-- 捕獲地點 -->
      <div
        v-if="record.location"
        class="flex flex-wrap items-center text-elder-body text-elder-text font-medium gap-1"
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
        <span
          v-if="record.tribe"
          class="rounded-full border border-rule-strong px-2 py-0.5 text-elder-body"
          >{{ record.tribe }}</span
        >
        <span class="break-all">{{ record.location }}</span>
      </div>

      <!-- 捕獲方式 -->
      <div
        v-if="record.capture_method"
        class="flex items-center text-elder-body text-elder-text font-medium"
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
      <FramedImage
        :src="record.image_url"
        :alt="`${fishName} 捕獲紀錄 ${index + 1}`"
        :framing="record.framing"
        ratio="photo"
        imgClass=""
        class="w-full h-auto object-cover border border-rule-strong"
      />

      <!-- 捕獲說明 -->
      <div v-if="record.notes" class="border border-rule p-4">
        <div class="flex items-start gap-2">
          <svg
            class="w-5 h-5 mt-0.5 shrink-0 stroke-current text-elder-subtext"
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
            <span class="text-elder-body font-bold text-elder-text block mb-1">捕獲說明</span>
            <p
              class="text-elder-body text-elder-text leading-relaxed whitespace-pre-line break-words"
            >
              {{ record.notes }}
            </p>
          </div>
        </div>
      </div>
      <div v-if="record.session_notes" class="mt-3 border border-rule p-4">
        <div class="flex items-start gap-2">
          <svg
            class="w-5 h-5 mt-0.5 shrink-0 stroke-current text-elder-subtext"
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
            <span class="text-elder-body font-bold text-elder-text block mb-1">情境備註</span>
            <p
              class="text-elder-body text-elder-text leading-relaxed whitespace-pre-line break-words"
            >
              {{ record.session_notes }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- 編輯（僅編輯者）-->
    <div
      v-if="canEdit"
      data-testid="capture-card-footer"
      class="flex justify-end border-t border-rule px-4"
    >
      <Link
        :href="`/fish/${fishId}/capture-records/${record.id}/edit`"
        class="inline-flex items-center min-h-touch-secondary text-elder-body text-brand underline underline-offset-4 hover:text-brand-dark"
        >編輯</Link
      >
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import FramedImage from '@/Components/UI/FramedImage.vue'
import { formatDate } from '@/utils/formatDate'

defineProps({
  fishId: { type: [Number, String], default: null },
  canEdit: { type: Boolean, default: false },
  record: { type: Object, required: true },
  index: { type: Number, required: true },
  fishName: { type: String, default: '' },
})
</script>

<template>
  <!-- 固定於畫面頂部的操作列 -->
  <div
    class="fixed top-0 left-0 w-full flex justify-between items-center px-2 py-2 bg-white z-20 shadow"
  >
    <button
      type="button"
      class="bg-gray-400 text-white px-4 py-2 rounded font-bold hover:bg-gray-500 transition"
      @click="goBack"
    >
      取消
    </button>

    <span class="text-base font-semibold text-[#0e171b]">{{ title }}</span>

    <button
      type="submit"
      class="bg-green-600 text-white px-4 py-2 rounded font-bold hover:bg-green-700 transition flex items-center"
      :disabled="submitting || showLoading"
      @click="submitNote"
    >
      <span v-if="showLoading" class="mr-2">
        <!-- 這裡是簡易 spinner 動畫，可依需求換成你喜歡的動畫 -->
        <svg class="animate-spin h-5 w-5 text-white" viewBox="0 0 24 24">
          <circle
            class="opacity-25"
            cx="12"
            cy="12"
            r="10"
            stroke="currentColor"
            stroke-width="4"
            fill="none"
          />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
        </svg>
      </span>
      {{ submitLabel || '送出' }}
    </button>
  </div>
</template>

<script setup>
defineProps({
  goBack: {
    type: Function,
    required: true,
  },
  submitNote: {
    type: Function,
    required: true,
  },
  submitting: {
    type: Boolean,
    default: false,
  },
  showLoading: {
    type: Boolean,
    default: false,
  },
  submitLabel: {
    type: String,
    default: '送出',
  },
  title: {
    type: String,
    default: '請新增標題',
  },
})
</script>

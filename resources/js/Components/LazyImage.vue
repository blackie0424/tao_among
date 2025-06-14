<template>
  <div :class="['relative flex items-center justify-center bg-gray-100 rounded-lg overflow-hidden', wrapperClass]" :style="wrapperStyle">
    <template v-if="loading">
      <svg class="animate-spin h-8 w-8 text-gray-400" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
      </svg>
    </template>
    <template v-else-if="error">
      <span class="text-gray-400 text-sm">圖片載入失敗</span>
    </template>
    <img
      v-show="!loading && !error"
      :src="src"
      :alt="alt"
      loading="lazy"
      :class="['object-contain rounded-lg', imgClass]"
      :style="imgStyle"
      @load="onLoad"
      @error="onError"
    />
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  src: String,
  alt: String,
  wrapperClass: { type: String, default: '' },
  wrapperStyle: { type: [String, Object], default: '' },
  imgClass: { type: String, default: '' },
  imgStyle: { type: [String, Object], default: '' },
});

const loading = ref(true);
const error = ref(false);

function onLoad() {
  loading.value = false;
}
function onError() {
  loading.value = false;
  error.value = true;
}

// 當 src 改變時重設 loading 狀態
watch(() => props.src, () => {
  loading.value = true;
  error.value = false;
});
</script>
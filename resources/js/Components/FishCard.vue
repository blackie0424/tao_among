<template>
  <div class="card flex flex-col items-center">
    <div class="image w-full h-48 overflow-hidden flex items-center justify-center bg-gray-100 rounded-lg relative">
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
        :src="fish.image"
        :alt="fish.name"
        loading="lazy"
        class="w-full h-full object-contain rounded-lg"
        @load="onLoad"
        @error="onError"
      />
    </div>
    <div class="info w-full flex justify-center">
      <div class="textFrame">
        <a
          :href="`/fish/${fish.id}`"
          class="text-lg font-bold text-gray-800 dark:text-gray-100"
        >
          {{ fish.name }}
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
const props = defineProps({ fish: Object });

const loading = ref(true);
const error = ref(false);

function onLoad() {
  loading.value = false;
}

function onError() {
  loading.value = false;
  error.value = true;
}

// 若圖片已快取，mounted 時直接關閉 loading
onMounted(() => {
  const img = new window.Image();
  img.src = props.fish.image;
  img.onload = onLoad;
  img.onerror = onError;
});
</script>
<template>
  <div :class="['relative flex items-center justify-center bg-gray-100 rounded-lg overflow-hidden', wrapperClass]" :style="wrapperStyle">
    <LoadingBar :loading="loading" :error="error" type="image" loading-text="資料載入中..." />
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
import LoadingBar from '@/Components/LoadingBar.vue';

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
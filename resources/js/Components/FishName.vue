<template>
  <div class="section section-name w-full max-w-3xl p-4 mb-4 flex flex-col items-end">
    <div class="flex justify-between w-full mb-4">
      <div class="text text-xl text-secondary">ngaran no among</div>
      <OverflowMenu :apiUrl="`/prefix/api/fish/${fishId}`" :redirectUrl="`/`" />
    </div>
    <!-- 魚名與 icon 水平排列 -->
    <div class="section-title text-2xl font-bold text-primary flex justify-between w-full">
      <span>{{ fishName }}</span>
      <Volume :audioUrl="props.audios.url" />
    </div>
  </div>
</template>

<script setup>
import Volume from '@/Components/Volume.vue'
import OverflowMenu from '@/Components/OverflowMenu.vue'

const props = defineProps({
  fishName: String,
  fishId: String,
  audios: Object,
})
</script>

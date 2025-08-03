<template>
  <div class="section section-name w-full max-w-3xl p-4 mb-4 flex flex-col items-end">
    <div class="flex justify-between w-full mb-4">
      <div class="text text-xl text-secondary">ngaran no among</div>
      <OverflowMenu :apiUrl="`/prefix/api/fish/${fishId}`" :redirectUrl="`/`" />
    </div>
    <!-- 魚名與 icon 水平排列 -->
    <div class="section-title text-2xl font-bold text-primary flex justify-between w-full">
      <span>{{ fishName }}</span>
      <Volume
        :audioUrl="'https://etycehppghhlxqpdvlga.supabase.co/storage/v1/object/public/tao_among_storage/audio/717bcc9f-8b42-4cf4-aef2-be248c8aa652.webm'"
      />
    </div>
  </div>
</template>

<script setup>
import Volume from '@/Components/Volume.vue'
import OverflowMenu from '@/Components/OverflowMenu.vue'

defineProps({
  fishName: String,
  fishId: String,
})
</script>

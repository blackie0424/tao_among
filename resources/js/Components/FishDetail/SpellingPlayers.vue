<template>
  <ul class="flex w-full flex-wrap items-center gap-x-6 gap-y-1" data-testid="spelling-players" aria-label="各拼字的發音">
    <li v-for="{ spelling, audio } in recordings" :key="spelling" class="flex items-center gap-1">
      <span class="font-serif text-elder-name text-elder-text">{{ spelling }}</span>
      <Volume :audioUrl="audio.url" :audioId="audio.id" />
    </li>
  </ul>
</template>

<script setup>
import Volume from '@/Components/UI/Volume.vue'

defineProps({
  // [{ spelling, audio }]：每個有錄音的拼字播放的那一段
  recordings: { type: Array, required: true },
})
</script>

<template>
  <span
    class="inline-flex items-center justify-center rounded-full bg-gray-200 ml-2"
    style="width: 2.2rem; height: 2.2rem"
  >
    <img :src="volumeIcon" alt="volume" class="w-5 h-5 opacity-50" style="filter: grayscale(1)" />
  </span>
</template>

<script setup>
const volumeIcon = '/images/volume.png'
</script>

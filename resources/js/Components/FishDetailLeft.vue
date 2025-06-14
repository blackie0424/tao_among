<!-- filepath: resources/js/Components/FishDetailLeft.vue -->
<template>
  <div class="w-full flex flex-col items-center">
    <div class="fish-section-card">
      <FishName :name="fish.name" class="w-full max-w-2xl text-2xl font-bold mb-4" />
      <LazyImage
        :src="fish.image"
        :alt="fish.name"
        wrapperClass="fish-image-wrapper"
        imgClass="fish-image"
      />
    </div>
  </div>
</template>

<script setup>
import FishName from '@/Components/FishName.vue';
import LazyImage from '@/Components/LazyImage.vue';

defineProps({
  fish: Object
});
</script>
<!-- filepath: resources/js/Components/FishDetailLeft.vue -->
<template>
  <div class="w-full md:w-2/3 flex flex-col items-center">
    <div class="fish-section-card">
      <FishName :name="fishName" class="w-full max-w-2xl text-2xl font-bold mb-4" />
      <LazyImage
        :src="fishImage"
        :alt="fishName"
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
  fishName: String,
  fishImage: String,
});
</script>
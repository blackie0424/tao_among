<template>
  <div class="show_image w-full max-w-3xl mx-auto flex items-center justify-center mb-6 p-4 rounded-lg shadow-custom h-60 md:h-[32rem]">
    <img :src="image" :alt="name" loading="lazy" class="max-h-full max-w-full object-contain rounded-lg" />
  </div>
</template>
<script setup>
defineProps({
  image: String,
  name: String
});
</script>
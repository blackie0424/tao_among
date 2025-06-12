<template>
  <div class="show_image w-full max-w-3xl mx-auto mb-6 p-4 rounded-lg shadow-custom">
    <img :src="image" :alt="name" loading="lazy" class="w-full h-auto rounded-lg object-contain" />
  </div>
</template>
<script setup>
defineProps({
  image: String,
  name: String
});
</script>
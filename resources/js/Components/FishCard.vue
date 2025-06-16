<template>
  <div class="card flex flex-col items-center">
    <LazyImage
      :src="fish.image"
      :alt="fish.name"
      wrapperClass="image w-full h-48 overflow-hidden flex items-center justify-center bg-gray-100 rounded-lg"
      imgClass="w-full h-full object-contain rounded-lg"
    />
    <div class="info w-full flex justify-center">
      <div class="textFrame">
        <a :href="`/fish/${fish.id}`" class="text-lg font-bold text-gray-800 dark:text-gray-100">
          {{ fish.name }}
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
import LazyImage from '@/Components/LazyImage.vue'
const props = defineProps({ fish: Object })
</script>

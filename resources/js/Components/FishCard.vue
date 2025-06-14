<template>
  <div class="card flex flex-col items-center">
    <div class="image w-full h-48 overflow-hidden">
      <img
        :src="fish.image"
        :alt="fish.name"
        loading="lazy"
        class="w-full h-full object-contain rounded-lg"
      />
    </div>
    <div class="info w-full flex justify-center">
      <div class="textFrame">
        <a
          :href="`/fish/${fish.id}`"
          class="text-lg font-bold text-gray-800 dark:text-gray-100"
        >
          {{ fish.name }}
        </a>
      </div>
    </div>
  </div>
</template>
<script setup>
defineProps({ fish: Object });
</script>
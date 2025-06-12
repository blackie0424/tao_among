<template>
    <div class="button-container">
        <div class="section-buttons flex flex-wrap md:flex-nowrap gap-2 md:gap-4 my-4 p-4 rounded-lg">
          <a
            v-for="locate in locates"
            :key="locate.value"
            :href="`?locate=${locate.value}`"
            class="locate-filter block w-full sm:w-1/2 md:w-auto md:min-w-[120px] px-6 py-2 min-h-[48px] flex items-center justify-center bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-200 rounded-full hover:bg-blue-200 dark:hover:bg-blue-700"
            :class="{'bg-yellow-500 dark:bg-yellow-600': currentLocate === locate.value}"
          >
            {{ locate.label }}
          </a>
        </div>
      </div>
</template>

<script setup>
defineProps({
  locates: Array
});
</script>
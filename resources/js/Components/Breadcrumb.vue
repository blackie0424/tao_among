<!-- filepath: /Users/chungyueh/Herd/tao_among/resources/js/Components/Breadcrumb.vue -->
<template>
  <nav class="w-full mb-6" aria-label="Breadcrumb">
    <ol class="flex items-center space-x-2 text-gray-500 text-sm">
      <li>
        <a
          href="/"
          class="px-4 py-1 rounded-full bg-blue-100 text-blue-700 font-semibold hover:bg-blue-200 transition"
        >
          nivasilan ko a among
        </a>
      </li>
      <li>
        <span class="mx-2 text-gray-400">/</span>
      </li>
      <li>
        <span class="px-4 py-1 rounded-full bg-gray-200 text-gray-700 font-bold">
          {{ second }}
        </span>
      </li>
    </ol>
  </nav>
</template>

<script setup>
defineProps({
  second: {
    type: String,
    required: true
  }
});
</script>
<template>
    <div class="section section-name w-full max-w-3xl text-center p-4 rounded-lg shadow-custom mb-4">
        <div class="text text-xl text-secondary">ngaran no among</div>
        <div class="section-title text-2xl font-bold text-primary mb-2">{{name}}</div>
    </div>
</template>

<script setup>
defineProps({
  name: String
});
</script>
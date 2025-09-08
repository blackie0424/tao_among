<template>
  <h1 :style="{ fontSize: `${fontSize}px` }" class="flex justify-center">
    <span
      v-for="(char, idx) in textArr"
      :key="idx"
      :style="{ animationDelay: `${idx * 0.08}s`, marginRight: '0.3em' }"
      class="animate-outline-fill"
    >
      {{ char }}
    </span>
  </h1>
</template>

<script setup>
import { computed } from 'vue'
const props = defineProps({
  text: String,
  fontSize: { type: Number, default: 48 },
})
const textArr = computed(() => props.text.split(''))
</script>

<style scoped>
@keyframes outline-fill {
  0% {
    color: transparent;
    -webkit-text-stroke: 2px #fff;
    text-stroke: 2px #fff;
    background: transparent;
    opacity: 1;
  }
  60% {
    color: transparent;
    -webkit-text-stroke: 2px #0e171b;
    text-stroke: 2px #0e171b;
    background: transparent;
    opacity: 1;
  }
  100% {
    color: #0e171b;
    -webkit-text-stroke: 0px #0e171b;
    text-stroke: 0px #0e171b;
    background: transparent;
    opacity: 1;
  }
}
.animate-outline-fill {
  display: inline-block;
  opacity: 1;
  color: transparent;
  background: transparent;
  -webkit-text-stroke: 2px #fff;
  animation: outline-fill 1s forwards;
  animation-fill-mode: forwards;
  margin-right: 0.3em;
}

/* 手持式裝置字體與間距調整，並移除粗體 */
@media (max-width: 400px) {
  h1 {
    font-size: 24px !important;
    font-weight: normal !important;
  }
  .animate-outline-fill {
    margin-right: 0.15em;
    font-weight: normal !important;
  }
}
</style>

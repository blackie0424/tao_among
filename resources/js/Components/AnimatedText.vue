<template>
  <h1 :style="{ fontSize: `${fontSize}px` }" class="font-bold flex justify-center">
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
  -webkit-text-stroke: 2px #fff; /* 白色簍空 */
  animation: outline-fill 1s forwards;
  animation-fill-mode: forwards;
}
</style>

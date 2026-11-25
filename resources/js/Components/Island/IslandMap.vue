<template>
  <!-- 蘭嶼平面地圖：島形參考 Components/Homepage/LanyuMap.vue，部落位置不沿用它（它的對照是錯的） -->
  <svg viewBox="0 0 400 460" class="w-full" role="group" aria-label="蘭嶼地圖">
    <rect width="400" height="460" rx="18" class="fill-island-sea" />
    <path
      d="M 50,50 L 100,12 L 210,8 L 310,50 L 355,125
         L 325,178 L 348,220
         L 390,318 L 382,368 L 338,442 L 288,445
         L 260,442 L 242,430 L 158,408 L 95,388 L 45,335
         L 28,285 L 30,262 L 5,228 L 28,185 L 30,175
         L 48,105 Z"
      class="fill-island-land stroke-island-land-line"
      stroke-width="3"
      stroke-linejoin="round"
    />

    <g
      v-for="tribe in tribes"
      :key="tribe"
      :data-tribe="tribe"
      role="button"
      tabindex="0"
      class="cursor-pointer focus:outline-none"
      :aria-label="`走到 ${islandTribeLabel(tribe)}`"
      :aria-current="tribe === currentTribe ? 'location' : undefined"
      @click="walkTo(tribe)"
      @keydown.enter.prevent="walkTo(tribe)"
      @keydown.space.prevent="walkTo(tribe)"
    >
      <!-- 透明的大圓是觸控範圍（約 48px），看得到的是中間的小圓點 -->
      <circle :cx="point(tribe).x" :cy="point(tribe).y" r="26" fill="transparent" />
      <circle
        :cx="point(tribe).x"
        :cy="point(tribe).y"
        r="10"
        :class="tribe === currentTribe ? 'fill-island-accent' : 'fill-white stroke-island-ink'"
        stroke-width="3"
      />
      <text
        :x="point(tribe).x"
        :y="point(tribe).y + 32"
        :text-anchor="point(tribe).anchor"
        class="fill-island-ink text-[15px] font-bold"
      >
        {{ islandTribeLabel(tribe) }}
      </text>
    </g>

    <!-- 角色：從所在部落走到點選的部落 -->
    <g
      data-testid="island-character"
      :style="{
        transform: `translate(${point(markerTribe).x}px, ${point(markerTribe).y - 24}px)`,
        transition: walking ? `transform ${WALK_MS}ms ease-in-out` : 'none',
      }"
      aria-hidden="true"
      @transitionend="arrive"
    >
      <circle r="12" class="fill-island-accent stroke-white" stroke-width="3" />
    </g>
  </svg>
</template>

<script setup>
import { ref } from 'vue'
import { islandTribeLabel } from '@/constants/island'

const props = defineProps({
  tribes: { type: Array, required: true },
  currentTribe: { type: String, required: true },
})

const emit = defineEmits(['arrive'])

// 6 個部落點在地圖上的位置（viewBox 400×460）；anchor 是部落名稱靠哪邊對齊，避免超出地圖
const TRIBE_POINTS = {
  iraraley: { x: 160, y: 46, anchor: 'middle' },
  yayo: { x: 52, y: 212, anchor: 'start' },
  iratay: { x: 82, y: 322, anchor: 'start' },
  imowrod: { x: 200, y: 376, anchor: 'middle' },
  ivalino: { x: 340, y: 360, anchor: 'end' },
  iranmeilek: { x: 318, y: 168, anchor: 'end' },
}
const UNKNOWN_POINT = { x: 200, y: 230, anchor: 'middle' }
const WALK_MS = 700
// transitionend 沒觸發時（例如分頁被切到背景）的保險
const WALK_FALLBACK_MS = WALK_MS + 300

const point = (tribe) => TRIBE_POINTS[tribe] ?? UNKNOWN_POINT

const markerTribe = ref(props.currentTribe)
const walking = ref(false)
let destination = null
let fallbackTimer = null

const prefersReducedMotion = () =>
  window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false

function walkTo(tribe) {
  if (destination !== null) {
    return
  }
  destination = tribe
  if (tribe === markerTribe.value || prefersReducedMotion()) {
    arrive()
    return
  }
  walking.value = true
  markerTribe.value = tribe
  fallbackTimer = setTimeout(arrive, WALK_FALLBACK_MS)
}

function arrive() {
  if (destination === null) {
    return
  }
  clearTimeout(fallbackTimer)
  const tribe = destination
  destination = null
  walking.value = false
  emit('arrive', tribe)
}
</script>

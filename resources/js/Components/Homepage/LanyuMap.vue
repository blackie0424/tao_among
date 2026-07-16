<template>
  <!--
    蘭嶼六部落地圖（內政部村里界真實邊界）
    官方行政區劃有 4 個村：朗島、椰油、紅頭、東清
    漁人（iranmeilek）屬椰油村、野銀（iratay）屬東清村，無獨立村界
    可點選：iraraley (朗島村)、imowrod (紅頭村)
  -->
  <div class="w-full max-w-sm">
    <svg
      :viewBox="`0 0 ${W} ${H}`"
      class="w-full"
      aria-label="蘭嶼部落地圖"
      role="img"
    >
      <g v-for="v in rendered" :key="v.villcode">
        <path
          :d="v.d"
          :fill="v.active ? '#0d9488' : '#d1d5db'"
          :stroke="v.active ? '#0f766e' : '#9ca3af'"
          stroke-width="1.5"
          :class="v.active ? 'cursor-pointer hover:brightness-110 focus:outline-none' : 'cursor-not-allowed opacity-70'"
          :tabindex="v.active ? 0 : -1"
          :role="v.active ? 'button' : undefined"
          :aria-label="v.active ? `${v.label}部落` : undefined"
          @click="v.active && emit('tribe-click', v.tribe)"
          @keydown.enter="v.active && emit('tribe-click', v.tribe)"
          @keydown.space.prevent="v.active && emit('tribe-click', v.tribe)"
        />
        <!-- Village label -->
        <text
          :x="v.cx"
          :y="v.cy - 6"
          text-anchor="middle"
          font-size="11"
          font-weight="700"
          :fill="v.active ? 'white' : '#4b5563'"
        >{{ v.label }}</text>
        <text
          :x="v.cx"
          :y="v.cy + 8"
          text-anchor="middle"
          font-size="9"
          :fill="v.active ? '#ccfbf1' : '#9ca3af'"
        >{{ v.tribe }}</text>
      </g>

      <!-- Centre label -->
      <text :x="W / 2" :y="H - 12" text-anchor="middle" font-size="11" fill="#6b7280">蘭嶼 Ponso no Tao</text>
    </svg>

    <!-- Legend for missing tribes -->
    <p class="mt-1 text-center text-xs text-gray-400">
      漁人（椰油村內）・野銀（東清村內）無獨立行政界
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { geoMercator, geoPath } from 'd3-geo'
import villages from '@/data/lanyuVillages.json'

const emit = defineEmits(['tribe-click'])

const W = 360
const H = 360

// villname → { tribe key, 中文 label, clickable }
const TRIBE_MAP = {
  '朗島村': { tribe: 'iraraley', label: '朗島', active: true },
  '椰油村': { tribe: 'ivalino',  label: '椰油', active: false },
  '紅頭村': { tribe: 'imowrod',  label: '紅頭', active: true },
  '東清村': { tribe: 'yayo',     label: '東清', active: false },
}

const padding = 16

const features = villages.map(v => ({ type: 'Feature', geometry: v.geometry, properties: v }))

const projection = geoMercator().fitExtent(
  [[padding, padding], [W - padding, H - padding - 20]],
  { type: 'FeatureCollection', features }
)

const pathGen = geoPath(projection)

const rendered = computed(() =>
  villages.map(v => {
    const feature = { type: 'Feature', geometry: v.geometry }
    const [cx, cy] = pathGen.centroid(feature)
    const info = TRIBE_MAP[v.villname] ?? { tribe: v.villname, label: v.villname, active: false }
    return {
      villcode: v.villcode,
      d: pathGen(feature),
      cx,
      cy,
      ...info,
    }
  })
)
</script>

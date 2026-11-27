<template>
  <!-- 魚島 3D（#216）：這個地點的魚照片，點一張就是問那條魚。照片不寫魚名（要問的就是「這是什麼」）。
       電腦（寬度 800 以上）排成一排、兩端有箭頭；手機一次一張、左右滑停在一張上（原生橫向捲動＋捲動對齊） -->
  <div class="relative">
    <ul
      ref="strip"
      data-testid="fish-strip"
      class="flex snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain pb-1"
      @scroll="updateArrows"
    >
      <li
        v-for="(item, index) in fish"
        :key="item.id"
        class="w-4/5 shrink-0 snap-start min-[801px]:w-[170px]"
      >
        <button
          type="button"
          data-testid="fish-photo"
          :aria-label="`第 ${index + 1} 條魚`"
          class="relative block w-full overflow-hidden rounded-island bg-island-line focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-island-ink/40"
          @click="emit('pick', item.id)"
        >
          <img
            :src="item.image_url"
            alt=""
            width="170"
            height="128"
            loading="lazy"
            class="aspect-[4/3] w-full object-cover"
          />
          <span
            v-if="heard.has(item.id)"
            class="absolute left-2 top-2 rounded-lg bg-island-ink px-2 py-0.5 text-sm font-bold text-white"
          >
            聽過了
          </span>
        </button>
      </li>
    </ul>
    <button
      v-if="canPrev"
      type="button"
      data-testid="strip-prev"
      aria-label="往左看"
      :class="arrowClass"
      class="left-0"
      @click="page(-1)"
    >
      ←
    </button>
    <button
      v-if="canNext"
      type="button"
      data-testid="strip-next"
      aria-label="往右看"
      :class="arrowClass"
      class="right-0"
      @click="page(1)"
    >
      →
    </button>
  </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

defineProps({
  // 這個地點的魚（依地點清單的順序），每筆至少有 id 與 image_url
  fish: { type: Array, required: true },
  // 這次打開頁面已經問過的魚編號
  heard: { type: Set, required: true },
})
const emit = defineEmits(['pick'])

// 箭頭只在電腦顯示（手機用滑的）
const arrowClass =
  'absolute top-1/2 hidden h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full border-2 border-island-line bg-white text-elder-body font-extrabold shadow min-[801px]:flex'

const strip = ref(null)
const canPrev = ref(false)
const canNext = ref(false)

// 捲到頭的那一側不顯示箭頭
function updateArrows() {
  const el = strip.value
  if (!el) return
  canPrev.value = el.scrollLeft > 0
  canNext.value = el.scrollLeft + el.clientWidth < el.scrollWidth
}

// 一次捲一個畫面寬；「減少動態效果」時不用平滑動畫
function page(direction) {
  const el = strip.value
  const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false
  el.scrollBy({ left: direction * el.clientWidth, behavior: reduced ? 'auto' : 'smooth' })
}

onMounted(async () => {
  await nextTick()
  updateArrows()
  window.addEventListener('resize', updateArrows)
})
onBeforeUnmount(() => window.removeEventListener('resize', updateArrows))
</script>

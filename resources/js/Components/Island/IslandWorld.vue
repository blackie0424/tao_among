<template>
  <!-- 魚島 3D（#199、#200）：畫面由 island3d/scene.js 畫在 stage 裡；這個元件只管載入、標示與說明文字。
       tribe 是目前所在部落：一開始決定角色站在哪，之後由上層依 tribe-change 更新；
       角色走進或離開家屋某一件的範圍時發出 spot-change、點到那一件的家人時發出 member-tap（#201） -->
  <div class="relative overflow-hidden rounded-island bg-island-sea">
    <p
      v-if="unsupported"
      data-testid="world-unsupported"
      role="alert"
      class="flex h-[70vh] min-h-[320px] items-center justify-center px-4 text-center text-elder-body font-bold"
    >
      這台裝置無法顯示魚島
    </p>
    <template v-else>
      <div
        ref="stage"
        data-testid="world-stage"
        class="h-[70vh] min-h-[320px] w-full touch-none"
        role="img"
        :aria-label="`${islandTribeLabel(tribe)} 的 3D 畫面`"
      />
      <p
        data-testid="world-tribe"
        class="pointer-events-none absolute left-3 top-3 rounded-island bg-white px-3 py-1.5 text-elder-body font-extrabold"
      >
        {{ tribe }}
      </p>
      <p
        v-if="debug && stats"
        data-testid="world-debug"
        class="pointer-events-none absolute bottom-3 right-3 rounded-island bg-white px-3 py-1.5 text-elder-aux"
      >
        畫質：{{ QUALITY_NAMES[stats.level] }}　每秒 {{ stats.framesPerSecond }} 張
      </p>
    </template>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { islandTribeLabel } from '@/constants/island'

const props = defineProps({
  tribe: { type: String, required: true },
  // 後端給的地點清單（部落 → 12 個地點）：四件上方招牌的名稱用它（#201）
  spots: { type: Object, required: true },
})
// tribe-change：角色走進另一個部落的格子，帶那個部落的代碼（左上角只顯示族語，#201）；
// spot-change：角色走進某一件的範圍帶 { tribe, hex, slot }，離開帶 null；
// member-tap：點到目前範圍那一件的家人（規格修訂 2）；
// beach-change：角色走進海邊的範圍帶部落代碼，離開帶 null（#232）
const emit = defineEmits(['tribe-change', 'spot-change', 'member-tap', 'beach-change'])

const QUALITY_NAMES = { high: '高', medium: '中', low: '低' }

const stage = ref(null)
const unsupported = ref(false)
const stats = ref(null)
// 網址帶 ?debug=1 時顯示畫質級別與每秒張數，給實機測試記錄用
const debug = new URLSearchParams(window.location.search).get('debug') === '1'

let world = null
let unmounted = false

const prefersReducedMotion = () =>
  window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false

onMounted(async () => {
  try {
    // three.js 與 3D 程式只在這裡才下載，其他頁面不受影響
    const { createIslandScene } = await import('@/island3d/scene')
    if (unmounted) {
      return
    }
    world = createIslandScene(stage.value, {
      startTribe: props.tribe,
      reducedMotion: prefersReducedMotion(),
      onTribeChange: (tribe) => emit('tribe-change', tribe),
      onSpotChange: (spot) => emit('spot-change', spot),
      onMemberTap: () => emit('member-tap'),
      onBeachChange: (beach) => emit('beach-change', beach),
      spots: props.spots,
      onStats: (next) => (stats.value = next),
    })
  } catch (error) {
    // 3D 程式下載失敗或瀏覽器無法建立 3D 畫面：改顯示說明文字
    console.warn('魚島 3D 無法顯示', error)
    unsupported.value = true
  }
})

onBeforeUnmount(() => {
  unmounted = true
  world?.dispose()
})
</script>

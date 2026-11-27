<template>
  <Head title="魚島" />

  <IslandLayout wide>
    <template #header>
      <h1 class="text-elder-name font-extrabold">魚島</h1>
      <p class="rounded-island bg-white px-3 py-1.5 text-elder-aux">{{ character.name }}</p>
    </template>

    <div class="relative">
      <IslandWorld
        :tribe="currentTribe"
        :spots="spots"
        @tribe-change="enterTribe"
        @spot-change="reachSpot"
        @member-tap="toggleDialog"
      />
      <IslandSpotPrompt
        :spot="currentSpot"
        :open="dialogOpen"
        :speaker="character.name"
        :touch="isTouch"
        :tribe="reachedSpot?.tribe ?? currentTribe"
        :fish="currentSpotFish"
        :heard="currentHeard"
        :tribes="tribes"
        :result-options="result_options"
        :unknown-processing-method="unknown_processing_method"
        @activate="toggleDialog"
        @ask="markHeard"
      />
    </div>
    <p class="mt-2 px-1 text-elder-aux text-island-muted">
      用方向鍵走路，或點地面走過去；拖曳畫面轉鏡頭，滾輪或雙指拉遠拉近
    </p>
    <!-- 海岸線、公路、山的位置取自 OpenStreetMap，授權要求標示來源（#207） -->
    <p data-testid="map-credit" class="px-1 text-elder-aux text-island-muted">
      地圖資料 © OpenStreetMap contributors
    </p>

    <section class="mt-3 rounded-island bg-white p-4">
      <dl class="space-y-3">
        <div>
          <dt class="text-elder-aux text-island-muted">你現在在</dt>
          <dd class="text-elder-name font-extrabold text-island-accent">
            {{ islandTribeLabel(currentTribe) }}
          </dd>
        </div>
        <div>
          <dt class="text-elder-aux text-island-muted">你的部落</dt>
          <dd class="text-elder-body font-bold">{{ islandTribeLabel(character.tribe) }}</dd>
        </div>
      </dl>
    </section>
  </IslandLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import IslandLayout from '@/Layouts/IslandLayout.vue'
import IslandWorld from '@/Components/Island/IslandWorld.vue'
import IslandSpotPrompt from '@/Components/Island/IslandSpotPrompt.vue'
import { islandTribeLabel } from '@/constants/island'

const props = defineProps({
  character: { type: Object, required: true },
  tribes: { type: Array, required: true },
  current_tribe: { type: String, required: true },
  // 每個部落 12 個地點（3 戶 × 4 件）的名稱、稱呼、問好語（#201）
  spots: { type: Object, required: true },
  // #216：每條魚一次（編號、照片、魚名、各部落結果人數）；每個部落 12 個地點列的魚（編號、家人說的話、有沒有可以看的說法）
  island_fish: { type: Array, required: true },
  spot_fish: { type: Object, required: true },
  result_options: { type: Object, required: true },
  unknown_processing_method: { type: String, required: true },
})

const LOCATION_URL = '/island/location'

// 目前所在部落：一開始是伺服器記的，之後是角色最後走進的部落格子
const currentTribe = ref(props.current_tribe)

// 記到伺服器一次只送一筆：前一筆還沒回來時只留最新的部落，回來後（不論成敗）再送，
// 所以最後送出的一定是最後走進的部落，不會被較早的請求蓋掉
let sending = false
let waitingTribe = null

async function rememberLocation(tribe) {
  if (sending) {
    waitingTribe = tribe
    return
  }
  sending = true
  try {
    await window.axios.post(LOCATION_URL, { tribe })
  } catch (error) {
    // 畫面照常顯示新部落、不重送這一筆；重新整理會回到上一次成功記住的部落
    console.warn('魚島：所在部落沒有記到伺服器', error)
  }
  sending = false
  if (waitingTribe !== null) {
    const next = waitingTribe
    waitingTribe = null
    rememberLocation(next)
  }
}

// ── 走近的提示與問好（#201）──
// 每戶 4 件，spots 依戶、再依件排：第 hex 格那一戶的第 slot 件是第 hex × 4 + slot 筆
const SPOTS_PER_HOUSEHOLD = 4
const reachedSpot = ref(null)
const dialogOpen = ref(false)
const currentSpot = computed(() => {
  const spot = reachedSpot.value
  return spot && props.spots[spot.tribe][spot.hex * SPOTS_PER_HOUSEHOLD + spot.slot]
})
const isTouch = window.matchMedia?.('(pointer: coarse)').matches ?? false

// ── 對話裡的魚（#216）──
const fishById = new Map(props.island_fish.map((fish) => [fish.id, fish]))
const currentSpotFish = computed(() => {
  const spot = reachedSpot.value
  const entries = spot
    ? (props.spot_fish[spot.tribe]?.[spot.hex * SPOTS_PER_HOUSEHOLD + spot.slot] ?? [])
    : []
  return entries.map((entry) => ({ ...fishById.get(entry.id), ...entry }))
})

// 「聽過了」只記在這次打開的頁面，依部落分開
const heardByTribe = ref({})
const currentHeard = computed(() => new Set(heardByTribe.value[reachedSpot.value?.tribe] ?? []))

function markHeard(id) {
  const tribe = reachedSpot.value.tribe
  heardByTribe.value = {
    ...heardByTribe.value,
    [tribe]: [...(heardByTribe.value[tribe] ?? []), id],
  }
}

function reachSpot(spot) {
  reachedSpot.value = spot
  dialogOpen.value = false
}

function toggleDialog() {
  if (currentSpot.value) {
    dialogOpen.value = !dialogOpen.value
  }
}

// 電腦按空白鍵開關對話框（規格修訂 2）；有提示時不讓空白鍵捲動網頁，沒有提示時照瀏覽器原本的行為。
// 焦點在對話框或細節視窗裡時不攔，空白鍵照那顆按鈕的行為（#216）
function focusInDialog() {
  return Boolean(document.activeElement?.closest?.('[data-island-dialog], [role="dialog"]'))
}

function onKeydown(event) {
  if (event.key === ' ' && currentSpot.value && !focusInDialog()) {
    event.preventDefault()
    toggleDialog()
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))

function enterTribe(tribe) {
  currentTribe.value = tribe
  rememberLocation(tribe)
}
</script>

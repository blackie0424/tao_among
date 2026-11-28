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
        @beach-change="reachBeach"
      />
      <IslandSpotPrompt
        ref="spotPrompt"
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
        :beach="reachedBeach !== null && !currentSpot"
        :caught="caught"
        :beach-actions="beachActions"
        @activate="toggleDialog"
        @ask="markHeard"
        @beach-action="onBeachAction"
      />
    </div>
    <p class="mt-2 px-1 text-elder-aux text-island-muted">
      用方向鍵走路，或點地面走過去；拖曳畫面轉鏡頭，滾輪或雙指拉遠拉近
    </p>
    <!-- 海岸線、公路、溪流取自 OpenStreetMap（#207）、山的地形取自 Terrain Tiles（#223），授權要求標示來源 -->
    <p data-testid="map-credit" class="px-1 text-elder-aux text-island-muted">
      地圖資料 © OpenStreetMap contributors；地形資料：U.S. Geological
      Survey（SRTM、GMTED2010）、NOAA（ETOPO1）
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
        <!-- #232 手上的魚：只存在這台瀏覽器 -->
        <div data-testid="held-fish">
          <dt class="text-elder-aux text-island-muted">手上的魚</dt>
          <dd class="text-elder-body font-bold">
            <div v-if="heldFish" class="flex items-center gap-3">
              <img
                :src="heldFish.image_url"
                alt="手上的魚"
                width="96"
                height="72"
                class="aspect-[4/3] w-24 rounded-island bg-island-line object-cover"
              />
              <button type="button" :class="actionButton" @click="openRecord(heldId)">
                {{ heldActionLabel }}
              </button>
            </div>
            <template v-else>還沒有，去海邊找一條</template>
          </dd>
        </div>
      </dl>
      <button
        type="button"
        data-testid="open-collection"
        :class="[actionButton, 'mt-3 w-full']"
        @click="collectionOpen = true"
      >
        我的圖鑑（已收集 {{ collection.length }} 條）
      </button>
    </section>

    <IslandRecordPanel
      v-if="recordFish"
      :fish="recordFish"
      :tribe="character.tribe"
      :questions="recordFishId === recordTarget ? record_questions : null"
      :name-max-length="name_max_length"
      :reload-only="RECORD_RELOAD_ONLY"
      :collectable="collectable"
      :collect-notice="collectNotice"
      :collecting="collecting"
      @close="recordTarget = null"
      @sent="onRecordSent"
      @collect="collect(recordTarget)"
    />
    <IslandCollection
      v-if="collectionOpen"
      :collection="collection"
      :fish-by-id="fishById"
      @close="collectionOpen = false"
      @edit="editFromCollection"
    />
  </IslandLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import IslandLayout from '@/Layouts/IslandLayout.vue'
import IslandWorld from '@/Components/Island/IslandWorld.vue'
import IslandSpotPrompt from '@/Components/Island/IslandSpotPrompt.vue'
import IslandRecordPanel from '@/Components/Island/IslandRecordPanel.vue'
import IslandCollection from '@/Components/Island/IslandCollection.vue'
import { islandTribeLabel } from '@/constants/island'
import { createHeldFish, heldFishKey } from '@/island3d/heldFish'

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
  // #232：角色編號（手上的魚依角色分開存）、圖鑑、每個部落每條魚家人說的話、記錄視窗的三題（延後載入）
  character_id: { type: Number, required: true },
  collection: { type: Array, required: true },
  fish_speech: { type: Object, required: true },
  record_questions: { type: Array, default: null },
  name_max_length: { type: Number, required: true },
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
const spotPrompt = ref(null)
const currentSpot = computed(() => {
  const spot = reachedSpot.value
  return spot && props.spots[spot.tribe][spot.hex * SPOTS_PER_HOUSEHOLD + spot.slot]
})
const isTouch = window.matchMedia?.('(pointer: coarse)').matches ?? false

// ── 對話裡的魚（#216）──
// 跟著 island_fish 更新（寫入後 partial reload 拿到新的結果、照片）
const fishById = computed(() => new Map(props.island_fish.map((fish) => [fish.id, fish])))
const currentSpotFish = computed(() => {
  const spot = reachedSpot.value
  const entries = spot
    ? (props.spot_fish[spot.tribe]?.[spot.hex * SPOTS_PER_HOUSEHOLD + spot.slot] ?? [])
    : []
  return entries.map((entry) => ({ ...fishById.value.get(entry.id), ...entry }))
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

// ── 海邊找魚、手上的魚（#232）──
const reachedBeach = ref(null)
const held = createHeldFish({
  storage: browserStorage(),
  key: heldFishKey(props.character_id),
  fishIds: props.island_fish.map((fish) => fish.id),
})
const heldId = ref(held.get())
const heldFish = computed(() => fishById.value.get(heldId.value) ?? null)
// 打開海邊對話框時找到的結果 { id, released }（id 沒有任何魚時為 null）；recordFishId 是 record_questions 目前是哪一條魚的
const caughtResult = ref(null)
const caught = computed(() => {
  const result = caughtResult.value
  if (result === null) return null
  return {
    fish: fishById.value.get(result.id) ?? null,
    released: result.released,
    collectedNumber: collectedNumberOf(result.id),
  }
})
const recordFishId = ref(null)
const collectedNumberOf = (id) =>
  props.collection.find((entry) => entry.fish_id === id)?.number ?? null
const hasOwnRecord = () =>
  (props.record_questions ?? []).some((question) => (question.my_answer ?? '') !== '')

// 找到魚後的按鈕：在圖鑑裡 → 改記錄；不在圖鑑裡要等記錄載入，有自己部落的回答 → 收進圖鑑，沒有 → 記下來
const beachActions = computed(() => {
  const fish = caught.value?.fish
  if (!fish) return null
  if (caught.value.collectedNumber !== null) {
    return [
      { key: 'record', label: '改我的記錄' },
      { key: 'close', label: '好' },
    ]
  }
  if (recordFishId.value !== fish.id) return null
  return [
    { key: 'record', label: hasOwnRecord() ? '收進圖鑑' : '記下來' },
    { key: 'close', label: '先不記' },
  ]
})

function browserStorage() {
  try {
    return window.localStorage
  } catch {
    return null
  }
}

function reachBeach(tribe) {
  reachedBeach.value = tribe
  if (!currentSpot.value) {
    dialogOpen.value = false
  }
}

// 載入這條魚自己部落的三題（記錄視窗、判斷「收進圖鑑」用）
function loadRecord(id) {
  router.reload({
    only: ['record_questions'],
    data: { record_fish: id },
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => (recordFishId.value = id),
  })
}

function catchFish() {
  const result = held.catchFish()
  if (result === null) {
    caughtResult.value = { id: null, released: false }
    return
  }
  heldId.value = result.id
  caughtResult.value = { id: result.id, released: result.released !== null }
  loadRecord(result.id)
}

function onBeachAction(key) {
  if (key === 'close') {
    dialogOpen.value = false
  } else {
    openRecord(caught.value.fish.id)
  }
}

// ── 記錄視窗與圖鑑（#232）──
// 寫入（記錄、收進圖鑑）後重新拿的資料：對話框、細節視窗、圖鑑都用新的
const RECORD_RELOAD_ONLY = ['record_questions', 'island_fish', 'fish_speech', 'collection']
const COLLECT_FAILED = '記錄已存好，還沒收進圖鑑'
const actionButton =
  'flex min-h-touch-primary items-center justify-center rounded-island border-2 border-island-line bg-white px-4 text-elder-body font-bold'
const recordTarget = ref(null)
const recordFish = computed(() => fishById.value.get(recordTarget.value) ?? null)
const collecting = ref(false)
const collectNotice = ref(null)
const collectionOpen = ref(false)
const isCollected = (id) => collectedNumberOf(id) !== null
const recordLoaded = (id) => recordFishId.value === id
// 只有手上（從海邊拿到）的魚才收得進圖鑑；已經有自己部落的回答、或上一次收進圖鑑失敗時才顯示「收進圖鑑」
const collectable = computed(
  () =>
    recordTarget.value === heldId.value &&
    !isCollected(heldId.value) &&
    recordLoaded(heldId.value) &&
    (hasOwnRecord() || collectNotice.value !== null)
)
const heldActionLabel = computed(() => {
  if (isCollected(heldId.value)) return '改我的記錄'
  return recordLoaded(heldId.value) && hasOwnRecord() ? '收進圖鑑' : '記下來'
})

function openRecord(id) {
  dialogOpen.value = false
  collectNotice.value = null
  recordTarget.value = id
  if (!recordLoaded(id)) loadRecord(id)
}

function editFromCollection(id) {
  collectionOpen.value = false
  openRecord(id)
}

// 手上的魚第一題記好就收進圖鑑（驗收條件 6）
function onRecordSent() {
  if (recordTarget.value === heldId.value && !isCollected(heldId.value)) {
    collect(heldId.value)
  }
}

// 收進圖鑑；被拒（自己部落沒有回答）顯示伺服器的訊息，其餘失敗（限流、逾時）顯示「記錄已存好，還沒收進圖鑑」可再按一次
function collect(id) {
  let rejected = null
  collecting.value = true
  router.post(
    `/island/collection/${id}`,
    {},
    {
      preserveState: true,
      preserveScroll: true,
      only: RECORD_RELOAD_ONLY,
      onError: (errors) => (rejected = errors.fish ?? null),
      onFinish: () => {
        collecting.value = false
        collectNotice.value = isCollected(id) ? null : (rejected ?? COLLECT_FAILED)
      },
    }
  )
}

// 重新整理後手上的魚還沒收進圖鑑：先載入它的記錄，決定按鈕是「記下來」還是「收進圖鑑」
if (heldId.value !== null && !isCollected(heldId.value)) {
  loadRecord(heldId.value)
}

function toggleDialog() {
  if (currentSpot.value) {
    dialogOpen.value = !dialogOpen.value
  } else if (reachedBeach.value !== null) {
    if (!dialogOpen.value) catchFish()
    dialogOpen.value = !dialogOpen.value
  }
}

// 電腦按空白鍵開對話框（規格修訂 2）；開著時是下一句、最後一句再按就關掉（#216 規格修訂 1）。
// 有提示時不讓空白鍵捲動網頁，沒有提示時照瀏覽器原本的行為。
// 焦點在對話框或細節視窗裡時不攔，空白鍵照那顆按鈕的行為（#216）
function focusInDialog() {
  return Boolean(document.activeElement?.closest?.('[data-island-dialog], [role="dialog"]'))
}

function onKeydown(event) {
  if (event.key === ' ' && (currentSpot.value || reachedBeach.value !== null) && !focusInDialog()) {
    event.preventDefault()
    if (dialogOpen.value) {
      spotPrompt.value.next()
    } else {
      toggleDialog()
    }
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))

function enterTribe(tribe) {
  currentTribe.value = tribe
  rememberLocation(tribe)
}
</script>

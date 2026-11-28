<template>
  <!-- 魚島 3D（#201）：走近家屋某一件時，畫面下緣的提示橫條；打開後是對話框。
       spot 是這一件的資料（名稱、稱呼、問好語）；橫條只顯示稱呼與操作說明，地點名稱在建物上方的招牌（規格修訂 2）。
       點橫條發出 activate，由上層切換 open。
       #216：對話一句一句來（規格修訂 1）：① 玩家問好 → ② 家人「{角色名}，你好」→ ③「你想認識哪一條魚？」＋照片
       → 點照片 ④ 玩家「ikongo o ya?」→ ⑤ 家人講那條魚。①②④ 按空白鍵或點對話框空白的地方是下一句，③⑤ 是關掉（發出 activate）
       #232：在海邊（beach）時，橫條是「海邊」＋找魚的說明；對話是（放回手上的魚 →）找到一條魚＋照片＋按鈕（發出 beach-action） -->
  <div>
    <!-- 螢幕報讀器只讀這一行的整句（規格修訂 3）。這一行一直都在、對話框沒開時是空的，
         打開後換成第一句，報讀器才會讀到（live region 要先存在，內容改變才會報讀） -->
    <p data-testid="line-for-reader" class="sr-only" aria-live="polite">
      {{ active && open ? line : '' }}
    </p>
    <div v-if="active" class="pointer-events-none absolute inset-x-3 bottom-3 flex justify-center">
      <div
        v-if="open"
        data-testid="spot-dialog"
        data-island-dialog
        class="pointer-events-auto w-full max-w-3xl rounded-island bg-white px-4 py-3 text-left shadow-lg"
        @click="onDialogClick"
      >
        <!-- ④⑤ 旁邊放選的那條魚的照片（規格修訂 2）：電腦在句子左邊、手機在句子上面 -->
        <div
          :class="
            showsAskedPhoto
              ? 'flex flex-col gap-3 min-[801px]:flex-row min-[801px]:items-start'
              : ''
          "
        >
          <img
            v-if="showsAskedPhoto"
            data-testid="asked-photo"
            :src="askedFish.image_url"
            alt=""
            width="170"
            height="128"
            class="aspect-[4/3] w-full rounded-island bg-island-line object-cover min-[801px]:w-[170px]"
          />
          <div class="flex-1">
            <p
              v-if="isPlayerStep"
              data-testid="spot-dialog-speaker"
              class="text-elder-aux font-bold text-island-muted"
            >
              {{ speaker }}
            </p>
            <p v-else-if="spot" class="text-elder-aux font-bold text-island-muted">
              {{ spot.npc }}
            </p>
            <!-- 字一個一個跑（規格修訂 3），報讀器讀上面那一行整句 -->
            <p
              :data-testid="isPlayerStep ? 'player-line' : 'npc-line'"
              aria-hidden="true"
              class="mt-1"
              :class="lineClass"
            >
              {{ shownLine }}
            </p>

            <!-- #232 找到的那條魚：照片跟著這一句出現，已經在圖鑑裡時多一行、句子跑完才出現按鈕 -->
            <template v-if="step === STEPS.found">
              <img
                data-testid="found-photo"
                :src="caught.fish.image_url"
                alt=""
                width="170"
                height="128"
                class="mt-2 aspect-[4/3] w-full rounded-island bg-island-line object-cover min-[801px]:w-[170px]"
              />
              <p v-if="caught.collectedNumber !== null" class="mt-2 text-elder-body font-bold">
                圖鑑裡已經有這條（第 {{ caught.collectedNumber }} 條）
              </p>
              <div
                v-if="!typing && beachActions"
                data-testid="beach-actions"
                class="mt-3 flex flex-wrap gap-2"
              >
                <button
                  v-for="action in beachActions"
                  :key="action.key"
                  type="button"
                  :class="actionButton"
                  @click="emit('beach-action', action.key)"
                >
                  {{ action.label }}
                </button>
              </div>
            </template>

            <!-- 句子跑完才出現：③ 的照片、⑤ 的按鈕 -->
            <template v-if="!typing">
              <IslandFishPicker
                v-if="step === STEPS.pick"
                class="mt-3"
                :fish="fish"
                :heard="heard"
                @pick="ask"
              />
              <div v-else-if="step === STEPS.answer && askedFish" class="mt-3 flex flex-wrap gap-2">
                <button
                  v-if="askedFish.has_detail"
                  ref="whoSaidButton"
                  type="button"
                  data-testid="who-said"
                  :class="actionButton"
                  @click="detailOpen = true"
                >
                  誰說的？
                </button>
                <button
                  type="button"
                  data-testid="ask-another"
                  :class="actionButton"
                  @click="step = STEPS.pick"
                >
                  問別條
                </button>
              </div>
            </template>
          </div>
        </div>
        <p
          data-testid="next-hint"
          class="mt-2 text-right text-elder-aux font-bold text-island-accent"
        >
          {{ touch ? '點一下' : '按空白鍵' }}
        </p>
      </div>
      <button
        v-else
        type="button"
        data-testid="spot-prompt"
        class="pointer-events-auto flex w-full max-w-xl flex-wrap items-center justify-between gap-x-4 gap-y-1 rounded-island bg-white px-4 py-2.5 text-left shadow-lg"
        @click="emit('activate')"
      >
        <span class="text-elder-body font-extrabold">{{ spot ? spot.npc : BEACH_NAME }}</span>
        <span class="text-elder-aux font-bold text-island-accent">{{ hint }}</span>
      </button>
    </div>

    <ResultDetailDialog
      v-if="spot && detailOpen && askedFish"
      :fish="askedFish"
      :tribe="tribe"
      :tribes="tribes"
      :result-options="resultOptions"
      :unknown-processing-method="unknownProcessingMethod"
      :answerable="false"
      @close="closeDetail"
    />
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import IslandFishPicker from '@/Components/Island/IslandFishPicker.vue'
import ResultDetailDialog from '@/Components/Island/ResultDetailDialog.vue'

const props = defineProps({
  spot: { type: Object, default: null },
  open: { type: Boolean, default: false },
  speaker: { type: String, required: true },
  touch: { type: Boolean, default: false },
  // #216：這一戶所在的部落、這個地點的魚（依序，每筆有 id、name、image_url、results、speech、has_detail）、已經問過的魚編號
  tribe: { type: String, required: true },
  fish: { type: Array, default: () => [] },
  heard: { type: Set, default: () => new Set() },
  tribes: { type: Array, required: true },
  resultOptions: { type: Object, required: true },
  unknownProcessingMethod: { type: String, required: true },
  // #232 海邊：beach 走進了海邊的範圍；caught 打開對話框時找到的結果
  // { fish 那條魚（沒有任何魚為 null）, released 有沒有先放回手上的魚, collectedNumber 已在圖鑑時是第幾條 }；
  // beachActions 找到魚後的按鈕 [{ key, label }]，記錄還沒載入完成時為 null
  beach: { type: Boolean, default: false },
  caught: { type: Object, default: null },
  beachActions: { type: Array, default: null },
})
const emit = defineEmits(['activate', 'ask', 'beach-action'])

// 玩家問「這是什麼？」的族語
const ASK = 'ikongo o ya?'
const WHICH_FISH = '你想認識哪一條魚？'
// #232 海邊
const BEACH_NAME = '海邊'
const BEACH_LINES = {
  released: '你把手上的魚放回海裡。',
  found: '你找到一條魚！',
  empty: '海裡現在沒有魚。',
}
// 字一個一個跑時每個字的間隔（毫秒，規格修訂 3）
const TYPING_INTERVAL_MS = 50
// 對話的步驟：① 問好、② 你好、③ 選魚、④ 問、⑤ 講那條魚
// #232 海邊：released 放回手上的魚、found 找到一條魚、empty 海裡沒有魚
const STEPS = {
  greet: 'greet',
  hello: 'hello',
  pick: 'pick',
  ask: 'ask',
  answer: 'answer',
  released: 'released',
  found: 'found',
  empty: 'empty',
}
const actionButton =
  'flex min-h-touch-primary items-center justify-center rounded-island border-2 border-island-line bg-white px-4 text-elder-body font-bold'

// 有家人的地點或海邊才有提示（同時只會有一個）
const active = computed(() => Boolean(props.spot) || props.beach)
// 電腦按空白鍵；觸控裝置點那位家人（海邊是點提示找魚）
const hint = computed(() => {
  if (!props.spot) return props.touch ? '點一下找魚' : '按空白鍵找魚'
  return props.touch ? '點一下對方' : '按空白鍵'
})
const hello = computed(() =>
  props.fish.length ? `${props.speaker}，你好` : `${props.speaker}，你好。這裡還沒有魚。`
)

// 打開時的第一句：家人是 ① 問好；海邊依找到的結果
function firstStep() {
  if (props.spot || !props.caught) return STEPS.greet
  if (!props.caught.fish) return STEPS.empty
  return props.caught.released ? STEPS.released : STEPS.found
}

const step = ref(firstStep())
const askedId = ref(null)
const askedFish = computed(() => props.fish.find((item) => item.id === askedId.value) ?? null)
const showsAskedPhoto = computed(
  () => askedFish.value !== null && (step.value === STEPS.ask || step.value === STEPS.answer)
)
const detailOpen = ref(false)
const whoSaidButton = ref(null)

// 這一步的那一句：①④ 玩家說、②③⑤ 家人說
const isPlayerStep = computed(() => step.value === STEPS.greet || step.value === STEPS.ask)
const line = computed(() => {
  switch (step.value) {
    case STEPS.greet:
      return props.spot?.greeting ?? ''
    case STEPS.hello:
      return hello.value
    case STEPS.pick:
      return WHICH_FISH
    case STEPS.ask:
      return ASK
    case STEPS.answer:
      return askedFish.value?.speech ?? ''
    default:
      return BEACH_LINES[step.value]
  }
})
const lineClass = computed(() => {
  if (isPlayerStep.value) return 'text-elder-name font-extrabold'
  return step.value === STEPS.answer ? 'text-elder-body' : 'text-elder-body font-bold'
})

// ── 字一個一個跑（規格修訂 3）──

// 以完整的字切開（擴充漢字、emoji、分解的韓文字不會只出現半個）；
// 瀏覽器不支援 Intl.Segmenter 時無法確定怎樣算一個字，就不跑字、整句直接出現
const segmenter =
  typeof Intl.Segmenter === 'function'
    ? new Intl.Segmenter('zh-Hant', { granularity: 'grapheme' })
    : null
const lineCharacters = computed(() =>
  segmenter ? Array.from(segmenter.segment(line.value), (part) => part.segment) : [line.value]
)
const typedCount = ref(0)
const typing = computed(() => typedCount.value < lineCharacters.value.length)
const shownLine = computed(() => lineCharacters.value.slice(0, typedCount.value).join(''))
let typingTimer = null

function stopTyping() {
  clearInterval(typingTimer)
  typingTimer = null
}

function finishTyping() {
  stopTyping()
  typedCount.value = lineCharacters.value.length
}

// 換一句就從頭跑；「減少動態效果」時整句直接出現
function startTyping() {
  stopTyping()
  const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false
  if (!props.open || reduced || !segmenter) {
    typedCount.value = lineCharacters.value.length
    return
  }
  typedCount.value = 0
  typingTimer = setInterval(() => {
    typedCount.value += 1
    if (!typing.value) stopTyping()
  }, TYPING_INTERVAL_MS)
}

watch([line, () => props.open], startTyping, { immediate: true })
onBeforeUnmount(stopTyping)

function ask(id) {
  askedId.value = id
  step.value = STEPS.ask
  emit('ask', id)
}

// 空白鍵或點對話框空白的地方：字還在跑就先整句顯示；否則有下一句就往下，沒有就關掉
function next() {
  if (typing.value) {
    finishTyping()
  } else if (step.value === STEPS.greet) {
    step.value = STEPS.hello
  } else if (step.value === STEPS.hello && props.fish.length > 0) {
    step.value = STEPS.pick
  } else if (step.value === STEPS.ask) {
    step.value = STEPS.answer
  } else if (step.value === STEPS.released) {
    step.value = STEPS.found
  } else {
    emit('activate')
  }
}
defineExpose({ next })

// 關掉對話框或換到別的地方：下次從 ① 重新開始
watch(
  () => [props.open, props.spot, props.beach],
  () => {
    step.value = firstStep()
    askedId.value = null
    detailOpen.value = false
  }
)

// 點裡面的按鈕不算
function onDialogClick(event) {
  if (!event.target.closest('button')) {
    next()
  }
}

// 關閉細節視窗後焦點回到「誰說的？」
async function closeDetail() {
  detailOpen.value = false
  await nextTick()
  whoSaidButton.value?.focus()
}
</script>

<template>
  <!-- 魚島 3D（#201）：走近家屋某一件時，畫面下緣的提示橫條；打開後是對話框。
       spot 是這一件的資料（名稱、稱呼、問好語）；橫條只顯示稱呼與操作說明，地點名稱在建物上方的招牌（規格修訂 2）。
       點橫條或對話框空白的地方發出 activate，由上層切換 open。
       #216：對話框裡玩家問好、家人回「{角色名}，你好」並列出這個地點的魚照片；點照片＝問「ikongo o ya?」，家人講那條魚 -->
  <div v-if="spot">
    <div class="pointer-events-none absolute inset-x-3 bottom-3 flex justify-center">
      <div
        v-if="open"
        data-testid="spot-dialog"
        data-island-dialog
        class="pointer-events-auto w-full max-w-3xl rounded-island bg-white px-4 py-3 text-left shadow-lg"
        @click="onDialogClick"
      >
        <p data-testid="spot-dialog-speaker" class="text-elder-aux font-bold text-island-muted">
          {{ speaker }}
        </p>
        <p class="mt-1 text-elder-name font-extrabold">{{ askedFish ? ASK : spot.greeting }}</p>

        <p class="mt-3 text-elder-aux font-bold text-island-muted">{{ spot.npc }}</p>
        <template v-if="askedFish">
          <div class="mt-1 flex flex-col gap-3 min-[801px]:flex-row min-[801px]:items-start">
            <img
              data-testid="asked-photo"
              :src="askedFish.image_url"
              alt=""
              width="170"
              height="128"
              class="aspect-[4/3] w-full rounded-island bg-island-line object-cover min-[801px]:w-[170px]"
            />
            <div class="flex-1">
              <p data-testid="npc-line" class="text-elder-body">{{ askedFish.speech }}</p>
              <div class="mt-3 flex flex-wrap gap-2">
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
                  @click="askedId = null"
                >
                  問別條
                </button>
              </div>
            </div>
          </div>
        </template>
        <template v-else>
          <p data-testid="npc-line" class="mt-1 text-elder-body font-bold">{{ hello }}</p>
          <IslandFishPicker
            v-if="fish.length"
            class="mt-3"
            :fish="fish"
            :heard="heard"
            @pick="ask"
          />
        </template>
      </div>
      <button
        v-else
        type="button"
        data-testid="spot-prompt"
        class="pointer-events-auto flex w-full max-w-xl flex-wrap items-center justify-between gap-x-4 gap-y-1 rounded-island bg-white px-4 py-2.5 text-left shadow-lg"
        @click="emit('activate')"
      >
        <span class="text-elder-body font-extrabold">{{ spot.npc }}</span>
        <span class="text-elder-aux font-bold text-island-accent">{{ hint }}</span>
      </button>
    </div>

    <ResultDetailDialog
      v-if="detailOpen && askedFish"
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
import { computed, nextTick, ref, watch } from 'vue'
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
})
const emit = defineEmits(['activate', 'ask'])

// 玩家問「這是什麼？」的族語
const ASK = 'ikongo o ya?'
const actionButton =
  'flex min-h-touch-primary items-center justify-center rounded-island border-2 border-island-line bg-white px-4 text-elder-body font-bold'

// 電腦按空白鍵；觸控裝置點那位家人
const hint = computed(() => (props.touch ? '點一下對方' : '按空白鍵'))
const hello = computed(() =>
  props.fish.length ? `${props.speaker}，你好` : `${props.speaker}，你好。這裡還沒有魚。`
)

const askedId = ref(null)
const askedFish = computed(() => props.fish.find((item) => item.id === askedId.value) ?? null)
const detailOpen = ref(false)
const whoSaidButton = ref(null)

function ask(id) {
  askedId.value = id
  emit('ask', id)
}

// 關掉對話框或換到別的地方：下次從問好重新開始
watch(
  () => [props.open, props.spot],
  () => {
    askedId.value = null
    detailOpen.value = false
  }
)

// 點對話框空白的地方關掉（#201）；點裡面的按鈕不算
function onDialogClick(event) {
  if (!event.target.closest('button')) {
    emit('activate')
  }
}

// 關閉細節視窗後焦點回到「誰說的？」
async function closeDetail() {
  detailOpen.value = false
  await nextTick()
  whoSaidButton.value?.focus()
}
</script>

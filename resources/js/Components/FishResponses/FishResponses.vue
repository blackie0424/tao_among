<template>
  <section class="border border-rule-strong bg-paper-raised p-4" data-testid="fish-responses">
    <h3 class="mb-3 pb-2 border-b border-rule font-serif text-elder-name font-bold text-elder-text">大家怎麼說</h3>

    <!-- 繼續上次做到一半的回答 -->
    <div v-if="step === 'resume'" data-testid="step-resume">
      <p class="mb-3 font-serif text-elder-name font-bold text-elder-text">{{ incomplete.tribe }}</p>
      <button type="button" class="w-full min-h-touch-secondary bg-brand text-elder-body font-bold text-white" @click="continueIncomplete">繼續 ›</button>
      <button type="button" :class="[bigButton, 'mt-3']" @click="startOver">繼續提供說法</button>
    </div>

    <!-- 1 你們也叫它 X 嗎？ -->
    <div v-else-if="step === 'confirm'" data-testid="step-confirm">
      <p class="mb-3 font-serif text-elder-name font-bold text-elder-text">你們也叫它 {{ fishName }} 嗎？</p>
      <button type="button" class="w-full min-h-[4rem] bg-green-700 text-3xl text-white" aria-label="是" @click="agree">✔</button>
      <button type="button" class="mt-3 w-full min-h-[4rem] border-2 border-red-700 bg-white text-3xl text-red-700" aria-label="不是" @click="step = 'method'">✖</button>
    </div>

    <!-- 2 用說的或用打的 -->
    <div v-else-if="step === 'method'" data-testid="step-method">
      <button v-if="fishName" type="button" aria-label="返回" :class="backButton" @click="step = 'confirm'"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7" /></svg></button>
      <p class="my-3 font-serif text-elder-name font-bold text-elder-text">{{ nameQuestion }}</p>
      <button type="button" :class="methodButton" data-testid="method-speak" @click="chooseSpeak">
        <svg class="h-9 w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="9" y="2" width="6" height="12" rx="3" /><path d="M5 11a7 7 0 0 0 14 0M12 18v4" /></svg>
        用說的
      </button>
      <button type="button" :class="[methodButton, 'mt-3']" data-testid="method-type" @click="step = 'name'">
        <svg class="h-9 w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2" /><path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M7 14h10" /></svg>
        用打的
      </button>
      <button type="button" :class="linkButton" @click="skipName">略過 ›</button>
    </div>

    <!-- 2a 錄音 -->
    <div v-else-if="step === 'record'" data-testid="step-record">
      <button type="button" aria-label="返回" :class="backButton" @click="leaveRecording"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7" /></svg></button>
      <p class="my-3 font-serif text-elder-name font-bold text-elder-text">請說出你們的叫法</p>
      <template v-if="recorder.isRecording.value">
        <p class="text-center text-4xl font-bold text-red-700" data-testid="recording-time">{{ elapsedLabel }}</p>
        <p class="mb-3 text-center text-elder-aux text-elder-subtext">最長 {{ RECORDING_MAX_SECONDS }} 秒，還剩 {{ recorder.remaining.value }} 秒</p>
        <button type="button" class="w-full min-h-[4rem] bg-red-700 text-elder-name font-bold text-white" data-testid="record-stop" @click="recorder.stop()">錄好了</button>
      </template>
      <template v-else>
        <p v-if="!micAsked" class="mb-3 text-elder-body text-elder-text" data-testid="mic-notice">要用麥克風錄你的聲音，等一下請按「允許」。</p>
        <button type="button" aria-label="開始錄音" class="mx-auto flex h-28 w-28 items-center justify-center rounded-full bg-brand text-white" data-testid="record-start" @click="startRecord">
          <svg class="h-14 w-14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="9" y="2" width="6" height="12" rx="3" /><path d="M5 11a7 7 0 0 0 14 0M12 18v4" /></svg>
        </button>
        <p class="mt-2 text-center text-elder-body text-elder-text">按一下開始，說完再按一下</p>
        <p class="text-center text-elder-aux text-elder-subtext">最長 {{ RECORDING_MAX_SECONDS }} 秒</p>
      </template>
      <template v-if="recorder.error.value">
        <p role="alert" class="mt-3 text-elder-body text-red-700" data-testid="record-error">{{ recorder.error.value }}</p>
        <button type="button" :class="[bigButton, 'mt-3']" data-testid="switch-to-typing" @click="switchToTyping">改用打的</button>
      </template>
    </div>

    <!-- 2b 試聽 -->
    <div v-else-if="step === 'review'" data-testid="step-review">
      <button type="button" aria-label="返回" :class="backButton" @click="leaveRecording"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7" /></svg></button>
      <p class="my-3 font-serif text-elder-name font-bold text-elder-text">這樣可以嗎？</p>
      <p v-if="recorder.autoStopped.value" aria-live="polite" class="mb-2 text-elder-aux text-elder-subtext" data-testid="auto-stopped">已錄滿 {{ RECORDING_MAX_SECONDS }} 秒，自動停止。</p>
      <RecordingPlayButton :src="recorder.audioUrl.value" variant="bar" :known-seconds="recorder.recordedSeconds.value" idle-label="聽聽看" ended-label="再聽一次" error-label="無法播放，請重錄" data-testid="preview-play" />
      <button type="button" :disabled="uploading" class="mt-3 w-full min-h-touch-secondary bg-brand text-elder-body font-bold text-white disabled:opacity-60" data-testid="review-ok" @click="confirmRecording">{{ uploading ? '上傳中…' : 'OK' }}</button>
      <button type="button" :disabled="uploading" :class="[bigButton, 'mt-3']" data-testid="re-record" @click="reRecord">重錄</button>
      <p v-if="uploadError" role="alert" class="mt-2 text-elder-body text-red-700" data-testid="upload-error">{{ uploadError }}</p>
      <p v-if="form.errors.recording" role="alert" class="mt-2 text-elder-body text-red-700" data-testid="recording-error">{{ form.errors.recording }}</p>
      <button v-if="uploadError" type="button" :class="linkButton" data-testid="switch-to-typing" @click="switchToTyping">改用打的</button>
      <button v-if="!rerecordTribe" type="button" :class="linkButton" data-testid="add-typed" @click="step = 'name'">用打的補上</button>
    </div>

    <!-- 2 叫什麼（保留文字輸入） -->
    <div v-else-if="step === 'name'" data-testid="step-name">
      <button type="button" aria-label="返回" :class="backButton" @click="step = recorder.audioBlob.value ? 'review' : 'method'"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7" /></svg></button>
      <p class="my-3 font-serif text-elder-name font-bold text-elder-text">{{ nameQuestion }}</p>
      <input v-model="typedName" type="text" class="w-full min-h-touch-secondary border border-rule-strong bg-white px-3 text-elder-body" aria-label="叫法" />
      <p class="mt-1 text-elder-aux text-elder-subtext">羅馬拼音、接近的中文字都可以</p>
      <p v-if="form.errors.name" role="alert" class="mt-2 text-elder-body text-red-700">{{ form.errors.name }}</p>
      <p v-if="uploadError" role="alert" class="mt-2 text-elder-body text-red-700" data-testid="upload-error">{{ uploadError }}</p>
      <button v-if="uploadError" type="button" :class="linkButton" data-testid="send-text-only" @click="sendTextOnly">只送文字，不傳錄音</button>
      <button type="button" :disabled="!typedName.trim() || uploading" class="mt-3 w-full min-h-touch-secondary bg-brand text-elder-body font-bold text-white disabled:opacity-60" @click="confirmTyped">OK</button>
      <button type="button" :class="linkButton" @click="skipName">略過 ›</button>
    </div>

    <!-- 3 哪個部落 -->
    <div v-else-if="step === 'tribe'" data-testid="step-tribe">
      <button type="button" aria-label="返回" :class="backButton" @click="step = agreed ? 'confirm' : recorder.audioBlob.value ? 'review' : 'name'"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7" /></svg></button>
      <p class="my-3 font-serif text-elder-name font-bold text-elder-text">你要提供哪個部落的說法？</p>
      <div class="grid grid-cols-2 gap-3">
        <button v-for="tribe in options.tribes" :key="tribe" type="button" :disabled="form.processing" :class="bigButton" @click="chooseTribe(tribe)">
          {{ tribe }}<span v-if="answeredTribes.has(tribe)" aria-label="已回答"> ✓</span>
        </button>
      </div>
      <p v-if="form.errors.name" role="alert" class="mt-2 text-elder-body text-red-700">{{ form.errors.name }}</p>
      <template v-if="form.errors.recording">
        <p role="alert" class="mt-2 text-elder-body text-red-700" data-testid="recording-error">{{ form.errors.recording }}</p>
        <button type="button" :class="[bigButton, 'mt-3']" data-testid="re-record-failed" @click="reRecord">重錄</button>
      </template>
    </div>

    <!-- 4 分類（可略過） -->
    <div v-else-if="step === 'category'" data-testid="step-category">
      <p class="mb-3 font-serif text-elder-name font-bold text-elder-text">{{ options.food_categories.slice(0, 2).join('？ ') }}？</p>
      <div class="grid grid-cols-2 gap-3">
        <button v-for="category in options.food_categories" :key="category" type="button" :class="[bigButton, category === previousCategory && 'border-brand font-bold']" @click="saveCategory(category)">{{ category }}</button>
      </div>
      <p v-if="previousCategory" class="mt-3 text-elder-aux text-elder-subtext" data-testid="previous-category">原本：{{ previousCategory }}</p>
      <p v-if="form.errors.food_category" role="alert" class="mt-2 text-elder-body text-red-700">{{ form.errors.food_category }}</p>
      <button type="button" :class="linkButton" @click="skipCategory">略過 ›</button>
    </div>

    <!-- 5 大家怎麼說：答完直接看，自己的選擇特別標出；下方可繼續提供說法 -->
    <div v-else data-testid="step-others">
      <p class="mb-2 text-elder-body text-elder-text" data-testid="intro">以下是不同部落的說法，提供參考。</p>
      <button v-if="backStep" type="button" aria-label="返回" :class="backButton" @click="step = backStep"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7" /></svg></button>
      <ul class="mt-2 grid grid-cols-1 gap-x-5 md:grid-cols-3">
        <li
          v-for="item in respondedTribes"
          :key="item.tribe"
          class="min-w-0 border-b border-rule py-3"
          :data-mine="mineOf(item) ? 'true' : undefined"
          data-testid="response-tribe"
        >
          <span class="font-serif text-elder-body font-bold text-elder-text">{{ item.tribe }}</span>
          <span v-if="mineOf(item)" class="ml-2 text-elder-aux font-bold text-amber-700" aria-label="我選的">✔</span>
          <span v-if="item.has_variants" class="ml-2 text-elder-aux text-elder-subtext" aria-label="有不同說法">≠</span>
          <p class="mt-1 break-words text-elder-body text-elder-text" data-testid="tribe-names">
            <span class="mr-2 text-elder-subtext">叫法</span>
            <template v-if="item.names.length">
              <template v-for="(n, i) in item.names" :key="n.name">
                <span v-if="i">、</span>
                <span :class="isMyName(item, n.name) && 'font-bold text-amber-700'" :data-mine="isMyName(item, n.name) ? 'true' : undefined">{{ n.name }}<template v-if="isMyName(item, n.name)"> ✔</template></span>
                <RecordingPlayButton v-if="n.recording_url" :src="n.recording_url" data-testid="name-recording" />
              </template>
            </template>
            <template v-else-if="!item.recordings_only?.length">—</template>
            <span v-if="item.recordings_only?.length" class="ml-2" data-testid="recordings-only">
              <span class="text-elder-subtext">只有錄音</span>
              <RecordingPlayButton v-for="r in item.recordings_only" :key="r.recording_url" :src="r.recording_url" data-testid="only-recording" />
            </span>
          </p>
          <div v-if="mineOf(item)?.recording_url" class="mt-2" data-testid="my-recording">
            <button type="button" class="inline-flex min-h-touch-secondary items-center gap-2 border-2 border-rule-strong bg-white px-3 text-elder-body text-elder-text" :aria-expanded="editingTribe === item.tribe" data-testid="my-edit" @click="toggleEdit(item.tribe)">
              <svg class="h-5 w-5 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z" /></svg>
              編輯我的錄音
            </button>
            <div v-if="editingTribe === item.tribe" class="mt-2 border-2 border-brand bg-white p-3" data-testid="my-edit-panel">
              <p class="mb-2 text-elder-body text-elder-subtext">你的錄音</p>
              <RecordingPlayButton :src="mineOf(item).recording_url" data-testid="my-play" />
              <template v-if="confirmingDelete === item.tribe">
                <p class="mt-3 text-elder-body font-bold text-elder-text" data-testid="delete-message">{{ onlyRecording(item) ? DELETE_ONLY_RECORDING_MESSAGE : DELETE_RECORDING_MESSAGE }}</p>
                <div class="mt-2 flex gap-3">
                  <button type="button" class="min-h-touch-secondary flex-1 border-2 border-red-700 bg-red-700 px-3 text-elder-body font-bold text-white" data-testid="delete-confirm" @click="deleteRecording(item.tribe)">{{ onlyRecording(item) ? '刪除說法' : '刪除' }}</button>
                  <button type="button" class="min-h-touch-secondary flex-1 border-2 border-rule-strong px-3 text-elder-body" data-testid="delete-cancel" @click="confirmingDelete = null">不要</button>
                </div>
                <p v-if="deleteForm.errors.recording" role="alert" class="mt-2 text-elder-body text-red-700" data-testid="delete-error">{{ deleteForm.errors.recording }}</p>
              </template>
              <template v-else>
                <div class="mt-3 flex gap-3">
                  <button type="button" class="min-h-touch-secondary flex-1 border-2 border-rule-strong px-3 text-elder-body" data-testid="my-re-record" @click="startReRecord(item.tribe)">重錄</button>
                  <button type="button" class="min-h-touch-secondary flex-1 border-2 border-rule-strong px-3 text-elder-body" data-testid="my-delete" @click="confirmingDelete = item.tribe">刪除</button>
                </div>
                <button type="button" :class="linkButton" data-testid="my-collapse" @click="toggleEdit(item.tribe)">收起</button>
              </template>
            </div>
          </div>
          <p class="mt-1 break-words text-elder-aux text-elder-text" data-testid="tribe-categories">
            <span class="mr-2 text-elder-subtext">分類</span>
            <template v-if="selectedCategories(item).length">
              <template v-for="(category, i) in selectedCategories(item)" :key="category.value">
                <span v-if="i">、</span>
                <span :class="isMyCategory(item, category.value) && 'font-bold text-amber-700'" :data-mine="isMyCategory(item, category.value) ? 'true' : undefined">{{ category.value }}<template v-if="isMyCategory(item, category.value)"> ✔</template> {{ category.count }} 人</span>
              </template>
            </template>
            <template v-else>—</template>
          </p>
        </li>
      </ul>
      <button v-if="mine.length" type="button" :class="[bigButton, 'mt-4']" @click="startOver">繼續提供說法</button>
    </div>

    <button v-if="respondedTribes.length && ['resume', 'confirm', 'name', 'tribe'].includes(step)" type="button" :class="linkButton" @click="openOthers">看大家怎麼說</button>
    <p class="mt-3 text-elder-aux text-elder-subtext" data-testid="responders">{{ responders }} 人回應</p>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import RecordingPlayButton from '@/Components/FishResponses/RecordingPlayButton.vue'
import { apiFetch } from '@/utils/apiFetch'
import { useAudioRecorder } from '@/composables/useAudioRecorder.js'

const props = defineProps({
  fishId: { type: Number, required: true },
  options: { type: Object, required: true },
  summary: { type: Array, default: () => [] },
  mine: { type: Array, default: () => [] },
  responders: { type: Number, default: 0 },
})

// 錄音最長秒數（與驗收條件一致：30 秒）
const RECORDING_MAX_SECONDS = 30
const SECONDS_PER_MINUTE = 60
const DELETE_RECORDING_MESSAGE = '確定刪除這段錄音？叫法與分類會保留。'
const DELETE_ONLY_RECORDING_MESSAGE = '這個部落你只有這段錄音，刪除後這筆說法也會一起刪除。確定嗎？'
const UPLOAD_FAILED_MESSAGE = '錄音上傳失敗，請再按一次 OK，或改用打的'

const bigButton = 'w-full min-h-[3.5rem] border-2 border-rule-strong bg-white px-2 text-elder-body text-elder-text'
const methodButton = 'flex w-full min-h-[6rem] flex-col items-center justify-center gap-1 border-2 border-brand bg-white text-elder-name font-bold text-brand'
const backButton = 'mt-1 inline-flex h-11 w-11 items-center justify-center rounded-full border border-brand text-brand hover:bg-paper'
const linkButton = 'mt-3 min-h-touch-secondary text-elder-body text-brand underline'

const form = useForm({ tribe: '', name: '', food_category: '' })
const deleteForm = useForm({ tribe: '' })
const recorder = useAudioRecorder({ maxSeconds: RECORDING_MAX_SECONDS })
const typedName = ref('')
const draftName = ref(null) // null＝還沒決定；''＝略過叫法；其他＝已決定的叫法
const agreed = ref(false)
const savedName = ref('')
const savedHasRecording = ref(false) // 目前這個部落的回答已經有錄音（分類那步略過時判斷用）
const previousCategory = ref('')
const backStep = ref(null) // 從問題畫面點「看大家怎麼說」進來時，可以返回原本的問題
const pendingRecording = ref(null) // 已上傳到暫存資料夾、還沒送出的錄音檔名
const uploading = ref(false)
const uploadError = ref('')
const micAsked = ref(false) // 第一次錄音前先說明會用到麥克風
const rerecordTribe = ref(null) // 從結果重錄某個部落的錄音時，部落已固定
const confirmingDelete = ref(null)
const editingTribe = ref(null) // 結果頁展開「編輯我的錄音」面板的部落（一次只開一個）
let uploadSeq = 0 // 每次取消就加一；舊的上傳回來發現編號不同就放棄，不再往下走

// 魚目前的名字；「我不知道」不列為選項，這時第一題直接問叫什麼
const fishName = computed(() => props.options.name_options[0] ?? null)
const nameQuestion = computed(() => (fishName.value ? '你們會怎麼稱呼呢？' : '這條魚，你們會怎麼稱呼呢？'))
const answeredTribes = computed(() => new Set(props.mine.map((r) => r.tribe)))
const respondedTribes = computed(() => props.summary.filter((item) => item.has_responses))
// 做到一半：已經有叫法或錄音、但還沒選分類的回答
const incomplete = computed(() => props.mine.find((r) => (r.name || r.recording_url) && !r.food_category) ?? null)
const elapsedLabel = computed(() => {
  const elapsed = RECORDING_MAX_SECONDS - recorder.remaining.value
  return `${Math.floor(elapsed / SECONDS_PER_MINUTE)}:${String(elapsed % SECONDS_PER_MINUTE).padStart(2, '0')}`
})

const firstQuestion = () => (fishName.value ? 'confirm' : 'method')
// 進來時：做到一半就提示繼續；答過的直接看大家怎麼說；沒答過就是第一題
const homeStep = () => (incomplete.value ? 'resume' : props.mine.length ? 'others' : firstQuestion())
const step = ref(homeStep())

// 錄完就自動進入試聽
watch(
  () => recorder.audioBlob.value,
  (blob) => {
    if (blob && step.value === 'record') step.value = 'review'
  }
)

// 自己的選擇（用來在結果中特別標出）。寫法比對要和後端分組規則（PHP 的 trim() 加 strtolower()）一致：
// 只去掉 PHP trim 預設的空白（空格、換行、Tab、垂直 Tab、NUL），不用 JS 的 trim()（它還會去掉 U+FEFF、不換行空格等）；
// 只有英文字母 A–Z 不分大小寫，其他文字不折。
const PHP_TRIM_CHARS = /^[ \n\r\t\x0B\x00]+|[ \n\r\t\x0B\x00]+$/g
const asciiLower = (text) => text.replace(PHP_TRIM_CHARS, '').replace(/[A-Z]/g, (c) => c.toLowerCase())
const mineOf = (item) => props.mine.find((r) => r.tribe === item.tribe)
const isMyName = (item, name) => {
  const mineName = mineOf(item)?.name
  return Boolean(mineName) && asciiLower(mineName) === asciiLower(name)
}
const isMyCategory = (item, category) => mineOf(item)?.food_category === category

// 字數以 Unicode 字元計（去前後空白後），與後端一致
const codePointLength = (text) => Array.from(text).length

function openOthers() {
  backStep.value = step.value
  step.value = 'others'
}

function showResults() {
  backStep.value = null
  step.value = 'others'
}

function agree() {
  agreed.value = true
  draftName.value = fishName.value
  step.value = 'tribe'
}

// ── 錄音 ──
function chooseSpeak() {
  cancelUpload()
  uploadError.value = ''
  finishRecording()
  step.value = 'record'
}

async function startRecord() {
  micAsked.value = true
  await recorder.start()
}

// 重錄：丟掉還沒送出的錄音，回到錄音畫面
function reRecord() {
  cancelUpload()
  uploadError.value = ''
  form.clearErrors()
  finishRecording()
  step.value = 'record'
}

// 取消進行中的上傳（返回、重錄、改用打的）：舊的上傳完成後不會再記下檔名或繼續送出
function cancelUpload() {
  uploadSeq++
  uploading.value = false
}

// 錄音畫面或試聽畫面按返回：不改動任何已存的回答（錄音還沒送出）
function leaveRecording() {
  cancelUpload()
  finishRecording()
  uploadError.value = ''
  step.value = rerecordTribe.value ? 'others' : 'method'
  rerecordTribe.value = null
}

// 不能錄或不想錄：丟掉錄音，改用打的
function switchToTyping() {
  cancelUpload()
  finishRecording()
  uploadError.value = ''
  step.value = 'name'
}

// 打字頁上傳失敗：不傳錄音，已輸入的叫法照常送出
function sendTextOnly() {
  cancelUpload()
  finishRecording()
  uploadError.value = ''
  step.value = 'tribe'
}

// 取得上傳網址後直傳到暫存資料夾；成功才記下暫存檔名
async function uploadRecording() {
  const mySeq = ++uploadSeq
  uploading.value = true
  uploadError.value = ''
  try {
    const res = await apiFetch(`/fish/${props.fishId}/responses/recording-url`, {
      method: 'POST',
      body: JSON.stringify({ ext: recorder.ext.value }),
    })
    const data = await res.json()
    if (!res.ok || !data.url) throw new Error('取得上傳網址失敗')
    const put = await fetch(data.url, { method: 'PUT', body: recorder.audioBlob.value })
    if (!put.ok) throw new Error('上傳失敗')
    if (mySeq !== uploadSeq) return false // 上傳途中已取消
    pendingRecording.value = data.filename
    return true
  } catch (e) {
    if (mySeq === uploadSeq) uploadError.value = UPLOAD_FAILED_MESSAGE
    return false
  } finally {
    if (mySeq === uploadSeq) uploading.value = false
  }
}

// 有錄音又還沒上傳時，離開叫法步驟前先上傳；上傳失敗就留在原畫面
async function readyForTribe() {
  if (recorder.audioBlob.value && !pendingRecording.value && !(await uploadRecording())) return
  step.value = 'tribe'
}

async function confirmRecording() {
  if (rerecordTribe.value) {
    if (!pendingRecording.value && !(await uploadRecording())) return
    const row = props.mine.find((r) => r.tribe === rerecordTribe.value)
    form.tribe = rerecordTribe.value
    submit(row?.name ?? '', row?.food_category ?? '', showResultsAfterRerecord, pendingRecording.value, discardPendingRecording)
    return
  }
  await readyForTribe()
}

// 送出被伺服器拒絕：這個暫存檔不能再用，下次按 OK 重新上傳（錄音還在）
function discardPendingRecording() {
  pendingRecording.value = null
}

function showResultsAfterRerecord() {
  finishRecording()
  rerecordTribe.value = null
  showResults()
}

function finishRecording() {
  recorder.reset()
  pendingRecording.value = null
}

function toggleEdit(tribe) {
  editingTribe.value = editingTribe.value === tribe ? null : tribe
  confirmingDelete.value = null
}

// 刪除後叫法、分類、錄音都沒有時，整筆說法會一起刪除
function onlyRecording(item) {
  const row = mineOf(item)
  return !row?.name && !row?.food_category
}

function startReRecord(tribe) {
  rerecordTribe.value = tribe
  editingTribe.value = null
  confirmingDelete.value = null
  reRecord()
}

function deleteRecording(tribe) {
  deleteForm.tribe = tribe
  deleteForm.delete(`/fish/${props.fishId}/responses/recording`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null
      editingTribe.value = null
    },
  })
}

// ── 打字 ──
async function confirmTyped() {
  const name = typedName.value.trim()
  if (codePointLength(name) > props.options.name_max_length) {
    form.setError('name', `叫法最多 ${props.options.name_max_length} 字`)
    return
  }
  form.clearErrors()
  draftName.value = name
  await readyForTribe()
}

// 只填分類：叫法留空（若先前有叫法，選了分類送出時一併清空）；已錄的音檔一併丟掉
function skipName() {
  form.clearErrors()
  typedName.value = ''
  draftName.value = ''
  finishRecording()
  step.value = 'tribe'
}

// 選好部落：有叫法或錄音就先存（分類是下一步，另外送）；兩者都沒有就直接問分類
// 修改時先沿用原本的分類，使用者中途離開也不會遺失；要清掉分類得在分類那步按略過。
function chooseTribe(tribe) {
  form.clearErrors()
  form.tribe = tribe
  const existing = props.mine.find((r) => r.tribe === tribe)
  previousCategory.value = existing?.food_category ?? ''
  if (!draftName.value && !pendingRecording.value) {
    savedName.value = ''
    savedHasRecording.value = Boolean(existing?.recording_url)
    step.value = 'category'
    return
  }
  submit(
    draftName.value ?? '',
    previousCategory.value,
    () => {
      savedName.value = draftName.value ?? ''
      savedHasRecording.value = Boolean(pendingRecording.value || existing?.recording_url)
      finishRecording()
      step.value = 'category'
    },
    pendingRecording.value
  )
}

function continueIncomplete() {
  form.clearErrors()
  form.tribe = incomplete.value.tribe
  savedName.value = incomplete.value.name ?? ''
  savedHasRecording.value = Boolean(incomplete.value.recording_url)
  previousCategory.value = ''
  step.value = 'category'
}

function startOver() {
  form.reset()
  form.clearErrors()
  typedName.value = ''
  draftName.value = null
  agreed.value = false
  backStep.value = null
  rerecordTribe.value = null
  confirmingDelete.value = null
  editingTribe.value = null
  uploadError.value = ''
  finishRecording()
  step.value = firstQuestion()
}

function saveCategory(category) {
  form.food_category = category
  submit(savedName.value, category, showResults)
}

// 略過分類＝不填分類：原本有分類就送出空的把它清掉；叫法與錄音都沒有的話無法送出，提示至少填一項
function skipCategory() {
  if (!savedName.value && !savedHasRecording.value) {
    form.setError('food_category', '至少要填一項')
    return
  }
  if (previousCategory.value) {
    submit(savedName.value, '', () => {
      previousCategory.value = ''
      showResults()
    })
    return
  }
  showResults()
}

// recording 只有帶新錄音時才送；沒帶＝保留舊錄音
function submit(name, foodCategory, onSuccess, recording = null, onError = undefined) {
  form
    .transform((data) => ({ ...data, name, food_category: foodCategory, ...(recording ? { recording } : {}) }))
    .post(`/fish/${props.fishId}/responses`, { preserveScroll: true, onSuccess, onError })
}

// 分類與叫法分開顯示，避免暗示兩者一一對應。
const selectedCategories = (item) => item.categories.filter((category) => category.count > 0)
</script>

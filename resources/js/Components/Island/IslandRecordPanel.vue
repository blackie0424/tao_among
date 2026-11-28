<template>
  <!-- #232 記錄視窗：玩家用自己的方式記這條魚（叫法、分類、處理方式），算進角色自己的部落；
       只列選項、不顯示這個部落的結果。點選項就送出那一題（同作答頁），成功發出 sent。
       collectable 時上方有「收進圖鑑」（發出 collect）；collectNotice 是收進圖鑑失敗或被拒的提示 -->
  <div
    data-testid="record-panel"
    role="dialog"
    aria-modal="true"
    aria-labelledby="island-record-title"
    class="fixed inset-0 z-40 flex items-end justify-center bg-black/40 p-3 min-[801px]:items-center"
    @keydown.esc="emit('close')"
  >
    <div class="max-h-full w-full max-w-xl overflow-y-auto rounded-island bg-island-sea p-4">
      <div class="flex items-center gap-3">
        <img
          :src="fish.image_url"
          alt=""
          width="96"
          height="72"
          class="aspect-[4/3] w-24 rounded-island bg-island-line object-cover"
        />
        <div>
          <h2 id="island-record-title" class="text-elder-title font-extrabold">
            記到 {{ islandTribeLabel(tribe) }}
          </h2>
          <p class="text-elder-aux">你自己怎麼說這條魚？點一下就記下</p>
        </div>
      </div>

      <div v-if="collectable || collectNotice" class="mt-3 rounded-island bg-white p-3">
        <p v-if="collectNotice" role="alert" class="mb-2 text-elder-body font-bold text-red-700">
          {{ collectNotice }}
        </p>
        <button
          v-if="collectable"
          type="button"
          :disabled="locked"
          :class="primaryButton"
          @click="emit('collect')"
        >
          {{ waitSeconds > 0 ? submitLabel : '收進圖鑑' }}
        </button>
      </div>

      <p v-if="questions === null" class="mt-4 text-elder-body">載入中…</p>
      <template v-else>
        <div role="tablist" aria-label="題目" class="mt-3 flex gap-1 rounded-island bg-white p-1">
          <button
            v-for="q in questions"
            :key="q.key"
            type="button"
            role="tab"
            :aria-selected="q.key === activeKey ? 'true' : 'false'"
            class="min-h-touch-primary flex-1 rounded-island text-elder-body font-extrabold"
            :class="q.key === activeKey ? 'bg-island-ink text-white' : 'text-island-ink'"
            @click="openTab(q.key)"
          >
            {{ TAB_LABELS[q.key] }}{{ q.my_answer ? ' ✓' : '' }}
          </button>
        </div>

        <p v-if="waitSeconds > 0" role="alert" class="mt-2 text-elder-body font-bold text-red-700">
          <span class="sr-only">送出太多次了，請等 {{ initialWaitSeconds }} 秒再試</span>
          <span aria-hidden="true">送出太多次了，請等 {{ waitSeconds }} 秒再試</span>
        </p>

        <IslandAnswerOptions
          :key="activeKey"
          v-model:supplementing="supplementing"
          class="mt-3"
          input-id="island-record-supplement"
          :options="current.options"
          :my-answer="current.my_answer"
          :allow-supplement="activeKey === 'name'"
          :locked="locked"
          :processing="form.processing"
          :wait-seconds="waitSeconds"
          :submit-label="submitLabel"
          :error-message="errorMessage"
          :name-max-length="nameMaxLength"
          @choose="send"
          @reset="form.clearErrors()"
        />
      </template>

      <button type="button" :class="[secondaryButton, 'mt-4 w-full']" @click="emit('close')">
        好了
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import IslandAnswerOptions from '@/Components/Island/IslandAnswerOptions.vue'
import { useRateLimitCountdown } from '@/composables/useRateLimitCountdown'
import { islandTribeLabel } from '@/constants/island'

const props = defineProps({
  // 這條魚（編號、照片）；記到哪個部落（角色自己的部落）
  fish: { type: Object, required: true },
  tribe: { type: String, required: true },
  // 三題的選項與我的回答（record_questions）；還沒載入是 null
  questions: { type: Array, default: null },
  nameMaxLength: { type: Number, required: true },
  // 寫入後要重新拿的資料
  reloadOnly: { type: Array, required: true },
  collectable: { type: Boolean, default: false },
  collectNotice: { type: String, default: null },
})
const emit = defineEmits(['close', 'sent', 'collect'])

const TAB_LABELS = { name: '叫法', food_category: '分類', processing_method: '處理' }
const primaryButton =
  'flex min-h-touch-primary w-full items-center justify-center rounded-island bg-island-accent px-4 text-elder-body font-bold text-white disabled:opacity-50'
const secondaryButton =
  'flex min-h-touch-primary items-center justify-center rounded-island border-2 border-island-line bg-white px-4 text-elder-body font-bold'

const activeKey = ref('name')
const supplementing = ref(false)
const form = useForm({ question: '', value: '' })
const { waitSeconds, initialWaitSeconds } = useRateLimitCountdown()

const current = computed(() => props.questions.find((q) => q.key === activeKey.value))
const locked = computed(() => form.processing || waitSeconds.value > 0)
const errorMessage = computed(() => form.errors.value || form.errors.question || null)
const submitLabel = computed(() => {
  if (form.processing) return '送出中…'
  return waitSeconds.value > 0 ? `${waitSeconds.value} 秒後可以再試` : '送出'
})

function openTab(key) {
  activeKey.value = key
  supplementing.value = false
  form.clearErrors()
}

/** 點選項（或送出補充）就送出這一題，算進角色自己的部落 */
function send(value) {
  if (locked.value) return
  form.question = activeKey.value
  form.value = value
  form.post(`/island/tribes/${props.tribe}/fish/${props.fish.id}/answers`, {
    preserveScroll: true,
    preserveState: true,
    only: props.reloadOnly,
    onSuccess: () => {
      supplementing.value = false
      emit('sent')
    },
  })
}
</script>

<template>
  <Head :title="title" />

  <FishAppLayout
    :pageTitle="title"
    :mobileBackUrl="`/fish/${fish.id}/audio-list`"
    mobileBackText="管理發音"
  >
    <div class="mx-auto max-w-xl">
      <h1 class="mb-6 font-serif text-elder-title text-elder-text">{{ title }}</h1>

      <AudioRecordForm
        :tribes="tribes"
        :initialTribe="audio.locate"
        :maxSeconds="maxSeconds"
        :requireRecording="false"
        :submitting="submitting"
        :error="error"
        submitLabel="存檔"
        @submit="save"
      >
        <template #current-recording>
          <div v-if="audio.url" class="mb-3 flex items-center gap-3">
            <Volume :audioUrl="audio.url" :audioId="audio.id" class="!ml-0 border border-brand" />
            <span class="text-elder-body text-elder-text">目前的錄音（重新錄音會換掉它）</span>
          </div>
        </template>
        <template #footer>
          <div class="border-t border-rule pt-3">
            <div class="mb-2 text-elder-aux text-elder-subtext">危險操作</div>
            <button
              type="button"
              class="min-h-touch-secondary border border-red-600 px-4 text-elder-body text-red-700 hover:bg-red-50"
              @click="remove"
            >
              刪除這段發音
            </button>
          </div>
        </template>
      </AudioRecordForm>
    </div>
  </FishAppLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import AudioRecordForm from '@/Components/FishAudio/AudioRecordForm.vue'
import Volume from '@/Components/UI/Volume.vue'
import { markFishStale } from '@/utils/fishListCache'
import { uploadPendingAudio } from '@/composables/useFishAudioUpload.js'

const props = defineProps({
  fish: { type: Object, required: true },
  audio: { type: Object, required: true },
  tribes: { type: Array, default: () => [] },
  maxSeconds: { type: Number, default: 5 },
})

const title = computed(() => `${props.audio.spoken_name || props.fish.name}・編輯發音`)
const submitting = ref(false)
const error = ref('')
const audioPath = computed(() => `/fish/${props.fish.id}/audio/${props.audio.id}`)

// 只能改部落、換音檔；名稱與檔名不可編輯。換音檔時先上傳到暫存，再由編輯請求確認。
async function save({ tribe, blob, ext, durationMs }) {
  submitting.value = true
  error.value = ''
  try {
    const payload = { tribe }
    if (blob) {
      payload.pending_filename = await uploadPendingAudio(props.fish.id, blob, ext)
      payload.duration_ms = durationMs
    }
    markFishStale(props.fish.id)
    router.put(audioPath.value, payload, {
      onError: (errors) => (error.value = Object.values(errors)[0] || '儲存失敗，請重試'),
      onFinish: () => (submitting.value = false),
    })
  } catch (e) {
    error.value = e.message || '音訊上傳失敗'
    submitting.value = false
  }
}

function remove() {
  if (!window.confirm('確定要刪除這段發音嗎？')) return
  router.delete(audioPath.value)
}
</script>

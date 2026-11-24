<template>
  <Head :title="`${title}`" />

  <FishAppLayout
    :pageTitle="title"
    :mobileBackUrl="afterSaveUrl"
    :mobileBackText="fromFishPage ? '回魚類資料' : '管理發音'"
  >
    <div class="mx-auto max-w-xl">
      <h1 class="font-serif text-elder-title text-elder-text">{{ title }}</h1>
      <p class="mb-6 mt-1 text-elder-aux text-elder-subtext">錄新的一段，不會蓋掉已有的錄音</p>

      <p v-if="names.length === 0" class="text-elder-body text-elder-text">
        這隻魚還沒有名稱，請先
        <Link :href="`/fish/${fish.id}/edit`" class="text-brand underline">編輯名稱</Link>。
      </p>

      <template v-else>
        <fieldset v-if="!selectedName" class="mb-6">
          <legend class="mb-2 text-elder-body font-bold text-elder-text">要錄哪個名稱</legend>
          <div class="grid grid-cols-1 gap-2">
            <button
              v-for="name in names"
              :key="name"
              type="button"
              class="min-h-touch-secondary border border-rule px-3 text-left font-serif text-elder-name"
              @click="selectedName = name"
            >
              {{ name }}
            </button>
          </div>
        </fieldset>

        <AudioRecordForm
          v-else
          :tribes="tribes"
          :maxSeconds="maxSeconds"
          :submitting="submitting"
          :error="error"
          submitLabel="存檔"
          @submit="save"
        />
      </template>
    </div>
  </FishAppLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import AudioRecordForm from '@/Components/FishAudio/AudioRecordForm.vue'
import { markFishStale } from '@/utils/fishListCache'
import { apiFetch } from '@/utils/apiFetch'
import { uploadPendingAudio } from '@/composables/useFishAudioUpload.js'

const props = defineProps({
  fish: { type: Object, required: true },
  names: { type: Array, default: () => [] },
  spokenName: { type: String, default: null },
  from: { type: String, default: null },
  tribes: { type: Array, default: () => [] },
  maxSeconds: { type: Number, default: 5 },
})

// 帶了名稱就直接用；只有一個名稱自動帶入；多個名稱而沒帶時先請使用者選
const selectedName = ref(
  props.names.includes(props.spokenName) ? props.spokenName : props.names.length === 1 ? props.names[0] : null
)
// 從魚頁麥克風進來（from=fish）存檔後回魚頁；其他來源回管理發音頁
const fromFishPage = props.from === 'fish'
const afterSaveUrl = fromFishPage ? `/fish/${props.fish.id}` : `/fish/${props.fish.id}/audio-list`
const title = computed(() => `${selectedName.value ?? props.fish.name}・新增發音`)
const submitting = ref(false)
const error = ref('')

async function save({ tribe, blob, ext, durationMs }) {
  submitting.value = true
  error.value = ''
  try {
    const filename = await uploadPendingAudio(props.fish.id, blob, ext)
    const res = await apiFetch(`/prefix/api/fish/${props.fish.id}/audio/confirm`, {
      method: 'POST',
      body: JSON.stringify({ filename, spoken_name: selectedName.value, tribe, duration_ms: durationMs }),
    })
    if (!res.ok) {
      const body = await res.json().catch(() => ({}))
      throw new Error(Object.values(body.errors ?? {})[0]?.[0] || '儲存失敗，請重試')
    }
    markFishStale(props.fish.id)
    router.visit(afterSaveUrl)
  } catch (e) {
    error.value = e.message || '音訊上傳失敗'
  } finally {
    submitting.value = false
  }
}
</script>

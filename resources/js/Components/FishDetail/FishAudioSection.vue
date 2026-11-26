<template>
  <div class="bg-paper-raised border border-rule-strong p-6 space-y-4">
    <h2 class="text-elder-name font-bold text-elder-text">發音</h2>

    <ul v-if="showSpellingRows" class="divide-y divide-rule">
      <li
        v-for="{ spelling, audio } in spellingItems"
        :key="spelling"
        data-testid="audio-spelling-row"
        class="flex min-h-touch-primary items-center gap-3 py-2"
      >
        <span data-testid="spelling" class="min-w-0 break-words font-serif text-elder-name font-bold text-elder-text">{{ spelling }}</span>
        <Volume v-if="audio" :audioUrl="audio.url" :audioId="audio.id" />
        <span v-else data-testid="no-recording" class="text-elder-body text-elder-subtext">還沒有錄音</span>
      </li>
    </ul>

    <div v-else-if="showCurrentFile" class="flex items-center gap-4 p-4 border border-rule">
      <Volume :audioUrl="fish.audio_url" />
      <span class="text-elder-body text-elder-text">目前發音檔</span>
    </div>

    <p v-else class="text-elder-body text-elder-subtext">尚無發音資料</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import Volume from '@/Components/UI/Volume.vue'
import { pickSpellingRecordings, splitFishName } from '@/utils/fishSpellings'

const props = defineProps({
  fish: { type: Object, required: true },
})

const spellings = computed(() => splitFishName(props.fish.name))
const recordings = computed(() => pickSpellingRecordings(props.fish))
// [{ spelling, audio }]：audio 為 null 表示這個拼字沒有可播的錄音
const spellingItems = computed(() =>
  spellings.value.map((spelling) => ({
    spelling,
    audio: recordings.value.find((recording) => recording.spelling === spelling)?.audio ?? null,
  }))
)
// 舊資料：錄音都還沒對應到拼字、魚卻有目前發音檔時，沿用一列「目前發音檔」（至少一個拼字有錄音就不用）
const showCurrentFile = computed(() => recordings.value.length === 0 && !!props.fish.audio_url)
const showSpellingRows = computed(() => spellings.value.length > 0 && !showCurrentFile.value)
</script>

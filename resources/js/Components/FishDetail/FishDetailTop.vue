<!-- filepath: resources/js/Components/FishDetailLeft.vue -->
<template>
  <div class="w-full flex flex-col items-center">
    <!-- 手機使用較小底距，桌面維持較大空間以保持排版 -->
    <div class="w-full relative overflow-hidden">
      <!-- 圖片區域：移除個別圓角，由外層容器控制。調整為 16:9 比例以完整呈現圖片 -->
      <div data-testid="fish-detail-photo" class="relative w-full aspect-video bg-paper-raised border border-rule-strong">
        <LazyImage
          :src="fish.display_image_url || fish.image_url"
          :alt="fish.name"
          wrapperClass="w-full h-full"
          imgClass="w-full h-full object-cover object-center"
          :imgStyle="displayImgStyle"
        />
      </div>

      <!-- 魚名與發音區塊 -->
      <div class="flex flex-wrap items-center gap-2 py-3 border-b border-rule">
         
         <!-- 魚名 + 發音（綁在一起，優先佔據空間） -->
         <div class="flex min-w-0 items-center gap-3 flex-grow">
           <h1 class="min-w-0 break-words font-serif text-elder-title font-bold text-elder-text tracking-tight">{{ fish.name }}</h1>
           
           <!-- Audio Player -->
           <div v-if="showSingleAudio" class="flex-shrink-0">
               <Volume :audioUrl="singleAudioUrl" />
           </div>
         </div>

         <!-- 多個拼字：每個有錄音的拼字一個播放鈕 -->
         <SpellingPlayers v-if="spellingRecordings.length" :recordings="spellingRecordings" />

          <!-- 管理區 (新增錄音) - 空間不足時換行 -->
          <div v-if="hasAudioAccess" class="flex items-center gap-1 flex-shrink-0">
             <!-- Add Audio Button (保留供田調快速操作) -->
             <Link :href="`/fish/${fish.id}/audio/create`" class="inline-flex items-center justify-center min-w-touch-secondary min-h-touch-secondary rounded-full text-brand hover:text-brand-dark hover:bg-paper-raised transition" title="新增錄音" aria-label="新增錄音">
                <svg class="w-6 h-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path></svg>
             </Link>
          </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import Volume from '@/Components/UI/Volume.vue'
import SpellingPlayers from '@/Components/FishDetail/SpellingPlayers.vue'
import { pickSpellingRecordings, splitFishName } from '@/utils/fishSpellings'
import LazyImage from '@/Components/UI/LazyImage.vue'
import { usePage, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { buildImageDisplayStyle } from '@/composables/useImageDisplayStyle'
import { canAccessAudio } from '@/constants/roles'

const props = defineProps({
  fish: Object,
})

const page = usePage()
const user = computed(() => page.props.auth?.user)
const hasAudioAccess = computed(() => canAccessAudio(user.value?.role))

// 每個拼字播它的主要發音（沒有指定就是最早的一段）。多個拼字：魚名下方各一個鈕；
// 只有一個名稱：魚名旁一個鈕。所有拼字都對不到錄音（舊資料尚未對應）時，回退到魚的目前發音檔。
const allRecordings = computed(() => (hasAudioAccess.value ? pickSpellingRecordings(props.fish) : []))
const isMultiSpelling = computed(() => splitFishName(props.fish?.name).length > 1)
const spellingRecordings = computed(() => (isMultiSpelling.value ? allRecordings.value : []))
const singleAudioUrl = computed(() => {
  if (!hasAudioAccess.value) return null
  if (isMultiSpelling.value && allRecordings.value.length > 0) return null // 已改成各拼字一個鈕
  return allRecordings.value[0]?.audio.url ?? props.fish?.audio_url ?? null
})
const showSingleAudio = computed(() => !!singleAudioUrl.value)

const displayImgStyle = computed(() =>
  buildImageDisplayStyle(
    props.fish?.display_capture_record?.image_position,
    props.fish?.display_capture_record?.image_scale,
  )
)
</script>

<template>
  <div class="bg-white rounded-xl shadow-md overflow-hidden">
    <Link
      :href="`/fish/${fish.id}`"
      class="block h-full group focus:outline-none focus:ring-2 focus:ring-blue-500"
    >
      <!-- 圖片區域 - 完整貼合上半部 -->
      <div class="relative">
        <LazyImage
          :src="fish.image_url"
          :alt="fish.name"
          wrapperClass="w-full h-[170px] flex items-center justify-center bg-gray-100"
          imgClass="w-full h-full object-cover"
        />
      </div>
      <!-- 文字資訊區域 - 白色背景帶 padding -->
      <div class="p-4 bg-white">
        <div class="flex items-center justify-between mb-2 h-9">
          <div
            class="text-base font-semibold truncate tracking-wide group-hover:text-blue-600 flex-1 min-w-0"
          >
            {{ fish.name }}
          </div>
          <!-- 音檔播放按鈕靠右 -->
          <div v-if="fish.audio_url" class="ml-2 flex-shrink-0" @click.stop.prevent>
            <Volume :audioUrl="fish.audio_url" />
          </div>
        </div>
        <!-- 部落分類資訊：固定顯示 iraraley 和 imowrod -->
        <div class="space-y-1">
          <!-- iraraley -->
          <div class="text-base">
            <span class="font-semibold text-purple-700 dark:text-purple-400">iraraley</span>
            <template v-if="getTribalData('iraraley')">
              <span class="mx-1 text-gray-400 dark:text-gray-500">·</span>
              <span class="font-medium text-emerald-700 dark:text-emerald-400">{{
                getTribalData('iraraley')
              }}</span>
            </template>
          </div>
          <!-- imowrod -->
          <div class="text-base">
            <span class="font-semibold text-purple-700 dark:text-purple-400">imowrod</span>
            <template v-if="getTribalData('imowrod')">
              <span class="mx-1 text-gray-400 dark:text-gray-500">·</span>
              <span class="font-medium text-emerald-700 dark:text-emerald-400">{{
                getTribalData('imowrod')
              }}</span>
            </template>
          </div>
        </div>
      </div>
    </Link>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import LazyImage from '@/Components/LazyImage.vue'
import Volume from '@/Components/Volume.vue'

const props = defineProps({
  fish: {
    type: Object,
    required: true,
  },
})

// 取得特定部落的 food_category
const getTribalData = (tribeName) => {
  if (!props.fish.tribal_classifications || !Array.isArray(props.fish.tribal_classifications)) {
    return null
  }
  const tribeData = props.fish.tribal_classifications.find((tc) => tc.tribe === tribeName)
  return tribeData ? tribeData.food_category : null
}
</script>

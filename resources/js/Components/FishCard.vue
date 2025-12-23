<template>
  <div class="bg-white rounded-xl shadow-md">
    <Link
      :href="`/fish/${fish.id}`"
      class="block h-full p-4 group focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-xl"
    >
      <div class="relative mb-3">
        <LazyImage
          :src="fish.image_url"
          :alt="fish.name"
          wrapperClass="w-full h-40 overflow-hidden flex items-center justify-center bg-gray-100 rounded-lg"
          imgClass="w-full h-full object-cover rounded-lg"
        />
      </div>
      <div>
        <div class="text-base font-semibold truncate tracking-wide group-hover:text-blue-600 mb-2">
          {{ fish.name }}
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

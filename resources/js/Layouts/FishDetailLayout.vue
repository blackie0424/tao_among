<template>
  <div class="min-h-screen bg-gray-50">
    <!-- 全局 Flash Message -->
    <FlashMessage />

    <!-- 魚類圖片區塊（持久化，不會因頁面切換而重新渲染） -->
    <div v-if="fish" class="container mx-auto p-4">
      <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <div class="flex flex-col md:flex-row items-center gap-4">
          <!-- 魚類圖片 -->
          <div class="w-full md:w-1/3">
            <LazyImage
              :src="fish.display_image_url || fish.image_url"
              :alt="fish.name"
              wrapperClass="w-full h-48 bg-gray-100 rounded-lg"
              imgClass="w-full h-full object-contain"
            />
          </div>

          <!-- 魚類基本資訊 -->
          <div class="w-full md:w-2/3">
            <h2 class="text-2xl font-bold mb-2">{{ fish.name }}</h2>
            <p class="text-gray-600 mb-4">{{ pageDescription }}</p>

            <!-- 統計資訊插槽 -->
            <slot name="stats"></slot>
          </div>
        </div>
      </div>
    </div>

    <!-- 頁面內容插槽 -->
    <slot :fish="fish" />

    <!-- 底部導航列（持久化） -->
    <BottomNavBar
      v-if="fish"
      :fishBasicInfo="`/fish/${fish.id}`"
      :tribalKnowledge="`/fish/${fish.id}/tribal-classifications`"
      :captureRecords="`/fish/${fish.id}/capture-records`"
      :knowledge="`/fish/${fish.id}/knowledge`"
      :audioList="`/fish/${fish.id}/audio-list`"
      :currentPage="currentPage"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import LazyImage from '@/Components/LazyImage.vue'
import BottomNavBar from '@/Components/Global/BottomNavBar.vue'

const props = defineProps({
  currentPage: {
    type: String,
    default: 'fishBasicInfo',
  },
  pageDescription: {
    type: String,
    default: '',
  },
})

// 從 Inertia page props 取得 fish 資料
const page = usePage()
const fish = computed(() => page.props.fish)
</script>

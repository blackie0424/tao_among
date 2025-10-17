<template>
  <Head :title="`${fish.name}的知識管理`" />
  <div class="container mx-auto p-4 relative">
    <div class="pb-20">
      <!-- 魚類資訊 -->
      <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <div class="flex flex-col md:flex-row items-center gap-4">
          <!-- 魚類圖片 -->
          <div class="w-full md:w-1/3">
            <LazyImage
              :src="fish.image"
              :alt="fish.name"
              wrapperClass="w-full h-48 bg-gray-100 rounded-lg"
              imgClass="w-full h-full object-contain"
            />
          </div>

          <!-- 魚類資訊 -->
          <div class="w-full md:w-2/3">
            <h2 class="text-2xl font-bold mb-2">{{ fish.name }}</h2>
            <p class="text-gray-600 mb-4">知識管理</p>

            <!-- 統計資訊 -->
            <div class="flex flex-wrap gap-4 text-sm">
              <div class="flex items-center">
                <span class="inline-block w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                <span class="text-gray-700">
                  已記錄 {{ tribalClassificationsCount }} 筆地方知識
                </span>
              </div>
              <div class="flex items-center">
                <span class="inline-block w-3 h-3 bg-blue-500 rounded-full mr-2"></span>
                <span class="text-gray-700"> 已記錄 {{ notes }} 筆進階知識 </span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="flex flex-col md:flex-row items-center gap-4">
        <div class="w-full md:w-1/2">
          <!-- 地方知識 -->
          <div class="bg-white rounded-lg shadow-md p-4">
            <h3 class="text-lg font-semibold mb-4">地方知識</h3>
            <p class="text-gray-500 mb-6 max-w-md mx-auto">
              記錄這條魚的在蘭嶼各部落的食用分類及處理方式。
            </p>
            <p class="text-gray-500 mb-6 max-w-md mx-auto">
              食用分類可以分為：oyod,rahet,x(不食用),?(不確定)
            </p>
            <p class="text-gray-500 mb-6 max-w-md mx-auto">
              處理方式可以分為：去魚鱗,不去魚鱗,剝皮,x(不食用),?(不確定)
            </p>
            <a
              :href="`/fish/${fish.id}/tribal-classifications`"
              class="block w-full bg-blue-600 text-white text-center py-2 rounded-lg font-semibold hover:bg-blue-700 transition"
              >進入地方知識列表</a
            >
          </div>
        </div>

        <div class="w-full md:w-1/2">
          <!-- 進階知識 -->
          <div class="bg-white rounded-lg shadow-md p-4">
            <h3 class="text-lg font-semibold mb-4">進階知識</h3>
            <p class="text-gray-500 mb-6 max-w-md mx-auto">
              除了各部落的食用分類與處理方式外的資料都可以在這裡紀錄
            </p>
            <p class="text-gray-500 mb-6 max-w-md mx-auto">
              紀錄時請選擇要新增的分類標籤，針對該標籤書寫內容
            </p>
            <p class="text-gray-500 mb-6 max-w-md mx-auto">
              預設的知識提供地區為iraraley，其餘部落分階段開放。
            </p>
            <a
              :href="`/fish/${fish.id}/knowledge-list`"
              class="block w-full bg-blue-600 text-white text-center py-2 rounded-lg font-semibold hover:bg-blue-700 transition"
              >進入進階知識列表</a
            >
          </div>
        </div>
      </div>
      <BottomNavBar
        :fishBasicInfo="`/fish/${fish.id}`"
        :tribalKnowledge="`/fish/${fish.id}/tribal-classifications`"
        :captureRecords="`/fish/${fish.id}/capture-records`"
        :knowledge="`/fish/${fish.id}/knowledge`"
        :audioList="`/fish/${fish.id}/audio-list`"
        :currentPage="'knowledge'"
      />
    </div>
  </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import BottomNavBar from '../Components/Global/BottomNavBar.vue'
import LazyImage from '../Components/LazyImage.vue'

const props = defineProps({
  fish: Object,
  notes: Number,
  tribalClassificationsCount: Number,
})
</script>

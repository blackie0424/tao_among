<template>
  <div class="container mx-auto p-4 relative">
    <TopNavBar :goBack="goBack" title="部落飲食分類" />
    <div class="pt-16">
      <!-- 魚類資訊 -->
      <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <h2 class="text-xl font-bold mb-2">{{ fish.name }}</h2>
        <p class="text-gray-600">管理不同部落的飲食分類與處理方式</p>
      </div>

      <!-- 新增部落分類表單 -->
      <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <h3 class="text-lg font-semibold mb-4">新增部落分類</h3>
        <TribalClassificationForm
          :tribes="tribes"
          :foodCategories="foodCategories"
          :processingMethods="processingMethods"
          :fishId="fish.id"
          @submitted="onClassificationSubmitted"
        />
      </div>

      <!-- 現有部落分類列表 -->
      <div class="bg-white rounded-lg shadow-md p-4">
        <h3 class="text-lg font-semibold mb-4">現有部落分類</h3>
        <TribalClassificationList
          :classifications="fish.tribal_classifications"
          :tribes="tribes"
          :foodCategories="foodCategories"
          :processingMethods="processingMethods"
          :fishId="fish.id"
          @updated="onClassificationUpdated"
          @deleted="onClassificationDeleted"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import TopNavBar from '../Components/Global/TopNavBar.vue'
import TribalClassificationForm from '../Components/TribalClassificationForm.vue'
import TribalClassificationList from '../Components/TribalClassificationList.vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  fish: Object,
  tribes: Array,
  foodCategories: Array,
  processingMethods: Array,
})

function goBack() {
  router.visit(`/fish/${props.fish.id}`)
}

function onClassificationSubmitted() {
  // 重新載入頁面以顯示新增的分類
  router.reload()
}

function onClassificationUpdated() {
  // 重新載入頁面以顯示更新的分類
  router.reload()
}

function onClassificationDeleted() {
  // 重新載入頁面以移除刪除的分類
  router.reload()
}
</script>

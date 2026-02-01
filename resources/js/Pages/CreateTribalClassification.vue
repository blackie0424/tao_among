<template>
  <div class="container mx-auto p-4 relative">
    <TopNavBar
      :goBack="goBack"
      :title="`新增${fish.name}的地方知識`"
      :showSubmit="true"
      :submitNote="submitForm"
      :submitLabel="'儲存'"
    />
    <div class="pt-16">
      <!-- 已記錄部落提示 -->
      <div
        v-if="usedTribes && usedTribes.length > 0"
        class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg"
      >
        <p class="text-sm text-blue-800">
          <span class="font-semibold">已記錄的部落：</span>
          <span class="ml-1">{{ usedTribes.join('、') }}</span>
        </p>
        <p class="text-xs text-blue-600 mt-1">下方選單已自動過濾已記錄的部落</p>
      </div>

      <!-- 無可用部落提示 -->
      <div
        v-if="tribes.length === 0"
        class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-center"
      >
        <p class="text-yellow-800 font-medium">所有部落的地方知識皆已記錄完成</p>
        <p class="text-sm text-yellow-600 mt-2">您可以返回列表頁面編輯現有記錄</p>
        <button
          @click="goBack"
          class="mt-3 px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 transition-colors"
        >
          返回列表
        </button>
      </div>

      <TribalClassificationForm
        v-if="tribes.length > 0"
        :tribes="tribes"
        :foodCategories="foodCategories"
        :processingMethods="processingMethods"
        :fishId="fish.id"
        :fishName="fish.name"
        :fishImage="fish.display_image_url || fish.image_url"
        @submitted="onClassificationSubmitted"
        ref="formRef"
      />
    </div>
  </div>
</template>

<script setup>
import TopNavBar from '../Components/Global/TopNavBar.vue'
import TribalClassificationForm from '../Components/TribalClassificationForm.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
  fish: Object,
  tribes: Array,
  usedTribes: Array,
  foodCategories: Array,
  processingMethods: Array,
})

const formRef = ref(null)

function goBack() {
  router.visit(`/fish/${props.fish.id}/knowledge-manager`)
}

function onClassificationSubmitted() {
  // 返回地方知識列表頁面
  router.visit(`/fish/${props.fish.id}/knowledge-manager`)
}

// 整合送出到 TopNavBar 的 @submit 事件
function submitForm() {
  if (formRef.value) {
    formRef.value.submitForm()
  }
}
</script>

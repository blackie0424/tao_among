<template>
  <div class="border border-rule-strong bg-paper">
    <!-- 捕獲照片 -->
    <div class="relative overflow-hidden bg-paper-raised">
      <LazyImage
        :src="recordImageUrl"
        :alt="`${record.tribe} 捕獲紀錄`"
        wrapperClass="w-full h-48 bg-paper-raised"
        imgClass="w-full h-full object-cover"
        :imgStyle="recordImgStyle"
      />
      <span
        v-if="isDisplayImage"
        data-testid="display-image-badge"
        class="absolute left-2 top-2 bg-brand px-3 py-1 text-elder-aux font-bold text-white"
        >圖鑑主圖</span
      >
    </div>

    <!-- 紀錄資訊 -->
    <div class="flex flex-col gap-1 p-4 text-elder-body text-elder-text">
      <div class="font-serif font-bold">{{ record.capture_method }}</div>
      <div>{{ displayLabel }}</div>
      <div v-if="record.notes" class="break-words">備註：{{ record.notes }}</div>
      <div v-if="record.session_notes" class="break-words">
        情境備註：{{ record.session_notes }}
      </div>
    </div>

    <!-- 操作：文字連結 -->
    <div class="flex flex-wrap gap-x-6 border-t border-rule px-4">
      <Link :href="`/fish/${fishId}/capture-records/${record.id}/edit`" :class="actionClass"
        >編輯</Link
      >
      <button
        v-if="record.image_url"
        type="button"
        :class="actionClass"
        @click="rotateModalOpen = true"
      >
        旋轉照片
      </button>
      <button
        v-if="!isDisplayImage"
        type="button"
        :class="actionClass"
        :disabled="isUpdating"
        @click="setAsDisplayImage"
      >
        設為圖鑑主圖
      </button>
    </div>
  </div>

  <!-- 旋轉 Modal -->
  <ImageRotateModal
    v-if="record.image_url"
    :open="rotateModalOpen"
    :imageUrl="recordImageUrl"
    :fishId="fishId"
    :recordId="record.id"
    @close="rotateModalOpen = false"
    @rotated="onRotated"
  />
</template>

<script setup>
import LazyImage from '@/Components/UI/LazyImage.vue'
import { Link } from '@inertiajs/vue3'
import ImageRotateModal from '@/Components/UI/ImageRotateModal.vue'
import { computed, ref } from 'vue'
import { buildImageDisplayStyle } from '@/composables/useImageDisplayStyle'
import { router } from '@inertiajs/vue3'
import { markFishStale } from '@/utils/fishListCache'

const props = defineProps({
  record: Object,
  fishId: Number,
  displayCaptureRecordId: Number,
})

const emit = defineEmits(['updated'])

const actionClass =
  'inline-flex items-center min-h-touch-secondary text-elder-body text-brand underline underline-offset-4 hover:text-brand-dark disabled:opacity-50'

const isUpdating = ref(false)
const rotateModalOpen = ref(false)
const rotatedImageUrl = ref(null)

// 判斷此捕獲紀錄是否為當前圖鑑主圖
const isDisplayImage = computed(() => {
  return props.displayCaptureRecordId === props.record.id
})

// 設定為圖鑑主圖
function setAsDisplayImage() {
  if (isUpdating.value) return

  isUpdating.value = true

  router.put(
    `/fish/${props.fishId}/display-image`,
    { capture_record_id: props.record.id },
    {
      preserveScroll: true,
      onSuccess: () => {
        // 標記此魚類需要在 Fishs 頁面更新（因為主圖改變）
        markFishStale(props.fishId)
        emit('updated')
      },
      onError: (errors) => {
        console.error('設定圖鑑主圖失敗:', errors)
        alert('設定失敗，請稍後再試')
      },
      onFinish: () => {
        isUpdating.value = false
      },
    }
  )
}

// 處理捕獲紀錄圖片 URL（旋轉後使用 cache-busted URL）
const recordImageUrl = computed(() => {
  return rotatedImageUrl.value || props.record.image_url || '/images/default-capture.png'
})

const recordImgStyle = computed(() =>
  buildImageDisplayStyle(props.record.image_position, props.record.image_scale)
)

function onRotated(newUrl) {
  rotatedImageUrl.value = newUrl
  emit('updated')
}

// 顯示標籤：若 location 為空則只顯示 tribe
const displayLabel = computed(() => {
  const loc = (props.record.location || '').toString().trim()
  return loc ? `${props.record.tribe} => ${loc}` : props.record.tribe
})
</script>

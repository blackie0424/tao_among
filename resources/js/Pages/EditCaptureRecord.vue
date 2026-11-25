<template>
  <Head :title="title" />
  <FishAppLayout :pageTitle="title" :mobileBackUrl="manageUrl" mobileBackText="回管理捕獲紀錄">
    <div class="mx-auto max-w-2xl pb-8">
      <h1 class="font-serif text-elder-title text-elder-text">{{ title }}</h1>

      <div class="mt-6">
        <CaptureRecordForm
          :record="record"
          :tribes="tribes"
          :capture_methods="capture_methods"
          :fishName="fish.name"
          :fishImage="fish.display_image_url || fish.image_url"
          @submit="onFormSubmit"
          @statusChange="onStatusChange"
          ref="formRef"
        />
      </div>

      <button
        type="button"
        data-testid="save-btn"
        class="mt-6 inline-flex min-h-touch-secondary items-center bg-brand px-6 text-elder-body font-bold text-white hover:bg-brand-dark disabled:opacity-50"
        :disabled="!canSubmit || uploading"
        @click="submitForm"
      >
        {{ uploading ? '上傳中...' : '儲存' }}
      </button>

      <div class="mt-10 border-t border-rule pt-3" data-testid="danger-zone">
        <div class="mb-2 text-elder-aux text-elder-subtext">危險操作</div>
        <button
          type="button"
          data-testid="delete-btn"
          class="min-h-touch-secondary border border-red-600 px-4 text-elder-body text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="isDisplayImage"
          @click="remove"
        >
          刪除這筆捕獲紀錄
        </button>
        <p
          v-if="isDisplayImage"
          data-testid="delete-blocked"
          class="mt-2 text-elder-aux text-elder-subtext"
        >
          這是圖鑑主圖，請先把別張設為主圖才能刪除
        </p>
      </div>
    </div>
  </FishAppLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import CaptureRecordForm from '../Components/CaptureRecord/CaptureRecordForm.vue'

const props = defineProps({
  fish: Object,
  record: Object,
  tribes: Array,
  capture_methods: Array,
})

const formRef = ref(null)
const canSubmit = ref(true)
const uploading = ref(false)

const title = computed(() => `${props.fish.name}・編輯捕獲紀錄`)
const manageUrl = computed(() => `/fish/${props.fish.id}/capture-records`)
const isDisplayImage = computed(() => props.fish.display_capture_record_id === props.record.id)

function onStatusChange(status) {
  canSubmit.value = status.canSubmit
  uploading.value = status.uploading
}

function onFormSubmit(formData) {
  router.post(`/fish/${props.fish.id}/capture-records/${props.record.id}`, formData, {
    onSuccess: () => router.visit(manageUrl.value),
    onError: (e) => {
      formRef.value?.setErrors?.(e)
    },
  })
}

function submitForm() {
  formRef.value?.submitForm()
}

function remove() {
  if (isDisplayImage.value) return
  if (!window.confirm('確定要刪除這筆捕獲紀錄嗎？')) return
  router.delete(`/fish/${props.fish.id}/capture-records/${props.record.id}`)
}
</script>

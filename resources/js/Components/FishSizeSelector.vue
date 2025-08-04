<template>
  <div class="bg-white p-6 rounded shadow-md max-w-md mx-auto">
    <h3 class="text-xl font-bold mb-4">選擇魚的尺寸</h3>
    <ArmSelector v-model="selectedParts" :readonly="isReadonly" />
    <div v-if="sizeSubmitError" class="text-red-600 mt-2">{{ sizeSubmitError }}</div>
    <div v-if="sizeSubmitSuccess" class="text-green-600 mt-2">
      {{ isEditMode ? '尺寸更新成功！' : '尺寸新增成功！' }}
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import ArmSelector from '@/Components/ArmSelector.vue'

const props = defineProps({
  fishId: Number,
  fishSize: {
    type: Array,
    default: () => [],
  },
  modelValue: {
    type: Array,
    default: () => [],
  },
  mode: {
    type: String,
    default: 'create', // 'create'（建立）或 'edit'（編輯）
  },
})
const emit = defineEmits(['finished', 'update:modelValue'])

// 判斷是否為編輯模式
const isEditMode = computed(() => props.mode === 'edit')
// 建立模式可選擇，編輯模式也可選擇
const isReadonly = computed(() => false)

// 初始化選擇的部分
const selectedParts = ref([])

// 建立模式：預設空陣列，讓使用者自行選擇
// 編輯模式：預設為 fishSize 或 modelValue（舊資料），可再選擇
watch(
  () => props.mode,
  (mode) => {
    if (mode === 'create') {
      selectedParts.value = []
    } else if (mode === 'edit') {
      selectedParts.value = props.modelValue.length ? [...props.modelValue] : [...props.fishSize]
    }
  },
  { immediate: true }
)

// 雙向綁定 v-model
watch(selectedParts, (val) => emit('update:modelValue', val), { deep: true })

const sizeSubmitting = ref(false)
const sizeSubmitError = ref('')
const sizeSubmitSuccess = ref(false)

async function submitSize() {
  if (!props.fishId || !selectedParts.value.length) {
    sizeSubmitError.value = '請選擇尺寸'
    return
  }
  sizeSubmitting.value = true
  sizeSubmitError.value = ''
  sizeSubmitSuccess.value = false
  try {
    const url = isEditMode.value
      ? `/prefix/api/fish/${props.fishId}/editSize`
      : '/prefix/api/fishSize'
    const method = isEditMode.value ? 'PUT' : 'POST'
    const res = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        fish_id: props.fishId,
        parts: selectedParts.value,
      }),
    })
    const data = await res.json()
    if (!res.ok)
      throw new Error(data.message || (isEditMode.value ? '尺寸更新失敗' : '尺寸新增失敗'))
    sizeSubmitSuccess.value = true
    setTimeout(() => {
      emit('finished')
    }, 1000)
  } catch (e) {
    sizeSubmitError.value = e.message || (isEditMode.value ? '尺寸更新失敗' : '尺寸新增失敗')
  } finally {
    sizeSubmitting.value = false
  }
}

defineExpose({ submitSize })
</script>

<template>
  <form @submit.prevent="submitForm" class="space-y-4">
    <!-- 魚類提醒 -->
    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
      <LazyImage
        :src="fishImage"
        :alt="fishName"
        wrapperClass="fish-image-wrapper"
        imgClass="fish-image"
      />
    </div>
    <!-- 部落選擇 -->
    <div>
      <label for="tribe" class="block text-sm font-medium text-gray-700 mb-1">
        部落 <span class="text-red-500">*</span>
      </label>
      <select
        id="tribe"
        v-model="form.tribe"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        required
      >
        <option value="">請選擇部落</option>
        <option v-for="tribe in tribes" :key="tribe" :value="tribe">
          {{ tribe }}
        </option>
      </select>
      <div v-if="errors.tribe" class="text-red-500 text-sm mt-1">{{ errors.tribe }}</div>
    </div>

    <!-- 飲食分類選擇 -->
    <div>
      <label for="food_category" class="block text-sm font-medium text-gray-700 mb-1">
        飲食分類
      </label>
      <select
        id="food_category"
        v-model="form.food_category"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        <option value="">尚未紀錄</option>
        <option v-for="category in filteredFoodCategories" :key="category" :value="category">
          {{ category }}
        </option>
      </select>
      <div v-if="errors.food_category" class="text-red-500 text-sm mt-1">
        {{ errors.food_category }}
      </div>
    </div>

    <!-- 處理方式選擇 -->
    <div>
      <label for="processing_method" class="block text-sm font-medium text-gray-700 mb-1">
        處理方式
      </label>
      <select
        id="processing_method"
        v-model="form.processing_method"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        <option value="">尚未紀錄</option>
        <option v-for="method in filteredProcessingMethods" :key="method" :value="method">
          {{ method }}
        </option>
      </select>
      <div v-if="errors.processing_method" class="text-red-500 text-sm mt-1">
        {{ errors.processing_method }}
      </div>
    </div>

    <!-- 調查備註 -->
    <div>
      <label for="notes" class="block text-sm font-medium text-gray-700 mb-1"> 調查備註 </label>
      <textarea
        id="notes"
        v-model="form.notes"
        rows="4"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        placeholder="請記錄調查者、調查時間、調查過程、不同觀點等資訊"
      ></textarea>
      <div v-if="errors.notes" class="text-red-500 text-sm mt-1">{{ errors.notes }}</div>
    </div>
  </form>
</template>

<script setup>
import { reactive, ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import LazyImage from '@/Components/UI/LazyImage.vue'
import { markFishStale } from '@/utils/fishListCache'

const props = defineProps({
  initialData: { type: Object, default: null },
  tribes: Array,
  foodCategories: Array,
  processingMethods: Array,
  fishId: Number,
  fishName: String,
  fishImage: String,
})

const isEditMode = computed(() => props.initialData !== null)

const filteredFoodCategories = computed(() =>
  (props.foodCategories || []).filter((c) => c !== null && c !== '')
)

const filteredProcessingMethods = computed(() =>
  (props.processingMethods || []).filter((m) => m !== null && m !== '')
)

const emit = defineEmits(['submitted'])

const form = reactive({
  tribe: props.initialData?.tribe ?? '',
  food_category: props.initialData?.food_category ?? '',
  processing_method: props.initialData?.processing_method ?? '',
  notes: props.initialData?.notes ?? '',
})

const errors = ref({})
const processing = ref(false)

function submitForm() {
  processing.value = true
  errors.value = {}

  const url = isEditMode.value
    ? `/fish/${props.fishId}/tribal-classifications/${props.initialData.id}`
    : `/fish/${props.fishId}/tribal-classifications`

  const request = isEditMode.value ? router.put : router.post

  request(url, form, {
    onSuccess: () => {
      markFishStale(props.fishId)
      if (!isEditMode.value) {
        form.tribe = ''
        form.food_category = ''
        form.processing_method = ''
        form.notes = ''
      }
      emit('submitted')
    },
    onError: (errorResponse) => {
      errors.value = errorResponse
    },
    onFinish: () => {
      processing.value = false
    },
  })
}

defineExpose({ submitForm })
</script>

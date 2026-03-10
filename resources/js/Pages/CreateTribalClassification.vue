<template>
  <div class="container mx-auto p-4 md:p-6 max-w-7xl relative pb-20">
    <TopNavBar
      :goBack="goBack"
      :title="`${fish.name} - 新增地方知識`"
      :showSubmit="true"
      :submitNote="submitForm"
      :submitLabel="'儲存'"
    />
    <div class="pt-16 space-y-8">
      <!-- 魚類資訊簡介 -->
      <div class="flex items-center gap-4 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <div class="w-16 h-16 flex-shrink-0 bg-gray-100 rounded-lg overflow-hidden border border-gray-200">
          <LazyImage
            :src="fish.display_image_url || fish.image_url"
            :alt="fish.name"
            wrapperClass="w-full h-full"
            imgClass="w-full h-full object-cover"
          />
        </div>
        <div>
          <h2 class="text-xl font-bold text-gray-800">批次修改地方知識</h2>
          <p class="text-sm text-gray-500">快速設定所有部落的食用分類與魚鱗處理方式</p>
        </div>
      </div>

      <!-- 食用分類表格 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="bg-indigo-50 px-4 py-3 border-b border-indigo-100">
          <h3 class="font-bold text-indigo-800 flex items-center gap-2">
            <span>🍽️</span> 食用分類
          </h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse min-w-[600px]">
            <thead>
              <tr class="bg-gray-50 text-gray-600 text-sm">
                <th class="p-3 border-b border-gray-200 font-medium min-w-[100px] bg-gray-100 sticky left-0 z-10">部落</th>
                <th class="p-3 border-b border-gray-200 font-medium text-center" v-for="cat in filteredFoodCategories" :key="cat">
                  {{ cat }}
                </th>
                <th class="p-3 border-b border-gray-200 font-medium text-center text-gray-400 bg-gray-50">尚未紀錄</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in form.classifications" :key="'food-'+item.tribe" class="border-b border-gray-100 last:border-0 hover:bg-indigo-50/30 transition-colors">
                <td class="p-3 font-medium text-gray-800 bg-white sticky left-0 z-10 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]">{{ item.tribe }}</td>
                <td class="p-3 text-center" v-for="cat in filteredFoodCategories" :key="cat">
                  <input type="radio" :name="`food-${item.tribe}`" :value="cat" v-model="item.food_category" class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 cursor-pointer">
                </td>
                <td class="p-3 text-center bg-gray-50/50">
                  <input type="radio" :name="`food-${item.tribe}`" :value="''" v-model="item.food_category" class="w-4 h-4 text-gray-400 focus:ring-gray-400 border-gray-300 cursor-pointer">
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 魚鱗處理分類表格 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="bg-teal-50 px-4 py-3 border-b border-teal-100">
          <h3 class="font-bold text-teal-800 flex items-center gap-2">
            <span>🐟</span> 魚鱗處理分類
          </h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse min-w-[600px]">
            <thead>
              <tr class="bg-gray-50 text-gray-600 text-sm">
                <th class="p-3 border-b border-gray-200 font-medium min-w-[100px] bg-gray-100 sticky left-0 z-10">部落</th>
                <th class="p-3 border-b border-gray-200 font-medium text-center" v-for="method in filteredProcessingMethods" :key="method">
                  {{ method }}
                </th>
                <th class="p-3 border-b border-gray-200 font-medium text-center text-gray-400 bg-gray-50">尚未紀錄</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in form.classifications" :key="'proc-'+item.tribe" class="border-b border-gray-100 last:border-0 hover:bg-teal-50/30 transition-colors">
                <td class="p-3 font-medium text-gray-800 bg-white sticky left-0 z-10 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]">{{ item.tribe }}</td>
                <td class="p-3 text-center" v-for="method in filteredProcessingMethods" :key="method">
                  <input type="radio" :name="`proc-${item.tribe}`" :value="method" v-model="item.processing_method" class="w-4 h-4 text-teal-600 focus:ring-teal-500 border-gray-300 cursor-pointer">
                </td>
                <td class="p-3 text-center bg-gray-50/50">
                  <input type="radio" :name="`proc-${item.tribe}`" :value="''" v-model="item.processing_method" class="w-4 h-4 text-gray-400 focus:ring-gray-400 border-gray-300 cursor-pointer">
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- 備註表格 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-8">
        <div class="bg-amber-50 px-4 py-3 border-b border-amber-100">
          <h3 class="font-bold text-amber-800 flex items-center gap-2">
            <span>📝</span> 調查備註 <span class="text-xs text-amber-600 font-normal ml-2">紀錄調查者、時間等資訊 (選填)</span>
          </h3>
        </div>
        <div class="p-4 space-y-3">
          <div v-for="item in form.classifications" :key="'note-'+item.tribe" class="flex flex-col sm:flex-row gap-2 items-start sm:items-center">
            <label class="w-24 flex-shrink-0 font-medium text-gray-700 font-mono text-sm bg-gray-100 px-3 py-2 rounded text-center">{{ item.tribe }}</label>
            <input type="text" v-model="item.notes" placeholder="未填寫..." class="flex-1 w-full px-3 py-2 border border-gray-200 rounded-md focus:ring-amber-500 focus:border-amber-500 text-sm transition-colors focus:bg-amber-50/30">
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { reactive, computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import TopNavBar from '@/Components/Global/TopNavBar.vue'
import LazyImage from '@/Components/LazyImage.vue'
import { markFishStale } from '@/utils/fishListCache'

const props = defineProps({
  fish: Object,
  tribes: Array,
  foodCategories: Array,
  processingMethods: Array,
  classifications: {
    type: Array,
    default: () => []
  }
})

// 過濾空值選項
const filteredFoodCategories = computed(() => {
  return (props.foodCategories || []).filter(c => c !== null && c !== '')
})

const filteredProcessingMethods = computed(() => {
  return (props.processingMethods || []).filter(m => m !== null && m !== '')
})

// 建立表單初始資料
const form = reactive({
  classifications: props.tribes.map(tribe => {
    const existing = props.classifications.find(c => c.tribe === tribe) || {}
    return {
      tribe: tribe,
      food_category: existing.food_category || '',
      processing_method: existing.processing_method || '',
      notes: existing.notes || ''
    }
  })
})

const processing = ref(false)

function goBack() {
  router.visit(`/fish/${props.fish.id}/knowledge-manager`)
}

function submitForm() {
  if (processing.value) return
  processing.value = true
  
  router.post(`/fish/${props.fish.id}/tribal-classifications`, form, {
    onSuccess: () => {
      markFishStale(props.fish.id)
    },
    onFinish: () => {
      processing.value = false
    }
  })
}
</script>

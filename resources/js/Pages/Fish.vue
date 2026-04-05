<template>
  <Head :title="`${fish.name}的基本資料`" />
  
  <FishAppLayout
    :pageTitle="fish.name"
    mobileBackUrl="/fishs"
    :mobileBackText="mobileBackText"
    :showBottomNav="!!user"
  >
    <FishGridLayout>
      <!-- 頂部額外內容：地方知識摘要 -->
      <template #top-extra>
        <section>
          <TribalClassificationSummary 
            :classifications="tribalClassifications"
            :tribes="tribes"
            :fishId="fish.id" 
          />
        </section>
      </template>
  
      <!-- 中欄：捕獲紀錄 -->
      <template #middle>
        <section v-if="captureRecords.length || user">
          <div class="rounded-xl bg-white shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
              <div class="flex items-center gap-3">
                <h3 class="text-2xl font-bold text-gray-900">捕獲紀錄</h3>
                <span class="text-sm font-bold bg-gray-100 text-gray-800 px-3 py-1 rounded-full">{{ captureRecords.length }}</span>
              </div>
            </div>
  
            <div v-if="captureRecords.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <div 
                v-for="(record, index) in captureRecords" 
                :key="record.id" 
                class="rounded-xl border-2 overflow-hidden"
                :class="index % 2 === 0 ? 'bg-white border-teal-200' : 'bg-slate-50 border-slate-200'"
              >
                <!-- 卡片標題區：編號 -->
                <div 
                  class="px-4 py-3 flex items-center justify-between"
                  :class="index % 2 === 0 ? 'bg-teal-50 border-b border-teal-100' : 'bg-slate-100 border-b border-slate-200'"
                >
                  <div class="flex items-center gap-3">
                    <span 
                      class="inline-flex items-center justify-center w-8 h-8 rounded-full text-white font-bold text-lg"
                      :class="index % 2 === 0 ? 'bg-teal-500' : 'bg-slate-500'"
                    >
                      {{ index + 1 }}
                    </span>
                    <span class="text-lg font-semibold text-gray-800">第 {{ index + 1 }} 筆紀錄</span>
                  </div>
                </div>
                
                <!-- 卡片內容區 -->
                <div class="p-4 flex flex-col gap-4">
                  <!-- 捕獲時間 -->
                  <div v-if="record.capture_date" class="flex items-center text-base text-gray-900 font-medium">
                    <span class="text-gray-600 mr-2">📅</span>
                    捕獲時間：{{ formatDate(record.capture_date) }}
                  </div>
                  
                  <!-- 捕獲地點 -->
                  <div v-if="record.location" class="flex flex-wrap items-center text-base text-gray-900 font-medium gap-1">
                    <span class="text-gray-600 mr-1">📍</span>
                    捕獲地點：
                    <span class="bg-gray-200 text-sm px-2 py-0.5 rounded" v-if="record.tribe">{{ record.tribe }}</span>
                    <span class="break-all">{{ record.location }}</span>
                  </div>
                  
                  <!-- 捕獲方式 -->
                  <div v-if="record.capture_method" class="flex items-center text-base text-gray-900 font-medium">
                    <span class="text-gray-600 mr-2">🎣</span>
                    捕獲方式：{{ record.capture_method }}
                  </div>
                  
                  <!-- Image -->
                  <LazyImage 
                    :src="record.image_url" 
                    :alt="`${fish.name} 捕獲紀錄 ${index + 1}`"
                    class="w-full h-auto object-cover rounded-lg shadow-sm border border-gray-200"
                  />
                  
                  <!-- 捕獲說明 -->
                  <div v-if="record.notes" class="bg-amber-50 rounded-lg p-4 border border-amber-200">
                    <div class="flex items-start gap-2">
                      <span class="text-amber-600 text-lg leading-none mt-0.5">📝</span>
                      <div>
                        <span class="text-base font-medium text-amber-800 block mb-1">捕獲說明</span>
                        <p class="text-base text-gray-800 leading-relaxed whitespace-pre-line break-words">{{ record.notes }}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <div v-else class="text-center py-12 bg-gray-50 rounded-lg">
               <p class="text-gray-500 mb-4">目前還沒有捕獲紀錄照片</p>
            </div>
          </div>
        </section>
      </template>
  
      <!-- 底部：進階知識（僅 editor / admin 可見） -->
      <template #bottom>
        <section 
          v-if="isEditor && (Object.keys(groupedNotes).length || user)"
          class="bg-white rounded-xl shadow-sm border border-gray-200 p-4"
        >
          <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
            <h2 class="text-xl font-bold flex items-center gap-2 text-gray-900">
              <span>📖</span> 進階知識
            </h2>
          </div>

          <div v-if="Object.keys(groupedNotesByTypeAndLocate).length" class="space-y-8">
            <div v-for="(locates, type) in groupedNotesByTypeAndLocate" :key="type">
              <h4 class="text-lg font-bold text-gray-800 mb-4 px-1 flex items-center border-b pb-2">
                <span class="w-1.5 h-5 bg-teal-500 rounded-full mr-2"></span>
                {{ type }}
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- 針對每個部落標籤建一個區塊 -->
                <div v-for="(notes, locate) in locates" :key="locate" class="bg-gray-50 rounded-xl p-5 border border-gray-200">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 mb-3">
                    {{ locate }}
                  </span>
                  <ul class="space-y-4">
                    <li v-for="note in notes" :key="note.id">
                      <div class="text-gray-800 md:text-lg whitespace-pre-line leading-relaxed">{{ note.note }}</div>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-gray-500 text-center py-8 border border-dashed border-gray-300 rounded-lg">
            目前沒有進階地方知識的紀錄
          </div>
        </section>
      </template>
    </FishGridLayout>
  </FishAppLayout>
</template>

<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import FishGridLayout from '@/Layouts/FishGridLayout.vue'
import TribalClassificationSummary from '@/Components/TribalClassificationSummary.vue'
import LazyImage from '@/Components/LazyImage.vue'

// Removed persistent layout to support dynamic props
// defineOptions({
//   layout: FishAppLayout
// })

const props = defineProps({
  fish: Object,
  tribalClassifications: { type: Array, default: () => [] },
  captureRecords: { type: Array, default: () => [] },
  fishNotes: { type: Object, default: () => ({}) },
  tribes: { type: Array, default: () => [] },
})

const page = usePage()
const user = computed(() => page.props.auth?.user)
const isEditor = computed(() => ['editor', 'admin'].includes(user.value?.role))
const groupedNotes = computed(() => props.fishNotes || {})

// 將進階知識依據分類標籤 (note_type) 及部落標籤 (locate) 進行二次統整
const groupedNotesByTypeAndLocate = computed(() => {
  const result = {};
  for (const [type, notes] of Object.entries(props.fishNotes || {})) {
    result[type] = {};
    for (const note of notes) {
      const locate = note.locate || '未分類部落';
      if (!result[type][locate]) {
        result[type][locate] = [];
      }
      result[type][locate].push(note);
    }
  }
  return result;
});

// 動態決定手機版麵包屑中間層級文字
// 若魚名太長 (> 12 字元)，則縮減中間層級為 "..." 以爭取空間
const mobileBackText = computed(() => {
  return (props.fish?.name?.length || 0) > 12 ? '...' : 'among no tao'
})

// 格式化捕獲日期為易讀格式
const formatDate = (dateStr) => {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}/${month}/${day}`
}
</script>
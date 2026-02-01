<template>
  <Head :title="`${fish.name}的基本資料`" />
  
  <FishAppLayout
    :pageTitle="fish.name"
    mobileBackUrl="/fishs"
    :mobileBackText="mobileBackText"
    :showBottomNav="false"
  >
    <FishGridLayout>
      <!-- 左欄額外內容：部落分類摘要 -->
      <template #left-extra>
        <section v-if="tribalClassifications?.length || user">
          <TribalClassificationSummary 
            :classifications="tribalClassifications" 
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
              <Link v-if="user" :href="`/fish/${fish.id}/media-manager`" class="inline-flex items-center gap-1 text-sm text-teal-600 hover:text-teal-700 font-medium">
                <span class="text-lg leading-none">⚙️</span> 管理照片
              </Link>
            </div>
  
            <div v-if="captureRecords.length" class="space-y-8">
              <div v-for="record in captureRecords" :key="record.id" class="flex flex-col gap-3">
                <!-- Location Tag -->
                <div v-if="record.location" class="flex items-center text-sm text-gray-500">
                  <span class="bg-gray-100 text-xs px-2 py-0.5 rounded mr-2" v-if="record.tribe">{{ record.tribe }}</span>
                   {{ record.location }}
                </div>
                
                <!-- Image -->
                 <LazyImage 
                    :src="record.image_url" 
                    :alt="`${fish.name} 捕獲紀錄`"
                    class="w-full h-auto object-cover rounded-lg shadow-sm border border-gray-100"
                 />
                 
                 <!-- Photographer -->
<!-- Photographer info removed -->
              </div>
            </div>
            
            <div v-else class="text-center py-12 bg-gray-50 rounded-lg">
               <p class="text-gray-500 mb-4">目前還沒有捕獲紀錄照片</p>
               <Link v-if="user" :href="`/fish/${fish.id}/media-manager`" class="inline-flex px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 font-medium shadow-sm transition-colors">
                  管理照片
               </Link>
            </div>
          </div>
        </section>
      </template>
  
      <!-- 右欄：進階知識 -->
      <template #right>
        <section 
          v-if="Object.keys(groupedNotes).length || user"
          class="bg-white rounded-xl shadow-sm border border-gray-200 p-4"
        >
          <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
            <h2 class="text-xl font-bold flex items-center gap-2 text-gray-900">
              <span>📖</span> 進階知識
            </h2>
            <Link 
              v-if="user"
              :href="`/fish/${fish.id}/knowledge-manager`" 
              class="flex items-center gap-1 text-sm bg-teal-100 text-teal-700 px-3 py-1.5 rounded-md font-medium hover:bg-teal-200 transition"
            >
              <span class="text-lg leading-none">⚙️</span> 管理進階知識
            </Link>
          </div>

          <div v-if="Object.keys(groupedNotes).length" class="space-y-6">
            <div v-for="(items, type) in groupedNotes" :key="type">
              <h4 class="font-medium text-gray-800 mb-2 px-1 flex items-center">
                <span class="w-1 h-4 bg-teal-500 rounded-full mr-2"></span>
                {{ type }}
              </h4>
              <ul class="space-y-3">
                <li 
                  v-for="note in items" 
                  :key="note.id" 
                  class="bg-gray-50 rounded-lg p-4 border border-gray-200"
                >
                  <div class="flex justify-between items-start gap-3">
                    <div class="flex-1">
                      <span v-if="note.locate" class="inline-flex self-start items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 mb-2">
                        {{ note.locate }}
                      </span>
                      <div class="text-gray-800 md:text-lg whitespace-pre-line leading-relaxed">{{ note.note }}</div>
                    </div>
                    <!-- 編輯 Action -->
                    <Link v-if="user" :href="`/fish/${fish.id}/knowledge/${note.id}/edit`" class="text-gray-400 hover:text-blue-600 p-1 flex-shrink-0">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                    </Link>
                  </div>
                </li>
              </ul>
            </div>
          </div>
          <div v-else class="text-gray-500 text-center py-8 border border-dashed border-gray-300 rounded-lg">
            尚未建立知識筆記
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
})

const page = usePage()
const user = computed(() => page.props.auth?.user)
const groupedNotes = computed(() => props.fishNotes || {})

// 動態決定手機版麵包屑中間層級文字
// 若魚名太長 (> 12 字元)，則縮減中間層級為 "..." 以爭取空間
const mobileBackText = computed(() => {
  return (props.fish?.name?.length || 0) > 12 ? '...' : 'among no tao'
})
</script>
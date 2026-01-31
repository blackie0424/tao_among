<template>
  <Head :title="`${fish.name}的基本資料`" />
  
  <FishAppLayout
    :pageTitle="fish.name"
    mobileBackUrl="/fishs"
    :mobileBackText="mobileBackText"
  >
    <FishGridLayout>
      <!-- 左欄額外內容：部落分類摘要 -->
      <template #left-extra>
        <section v-if="tribalClassifications?.length">
          <TribalClassificationSummary 
            :classifications="tribalClassifications" 
            :fishId="fish.id" 
          />
        </section>
      </template>
  
      <!-- 中欄：捕獲紀錄 -->
      <template #middle>
        <section>
          <div class="rounded-xl bg-white shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
              <div class="flex items-center gap-3">
                <h3 class="text-2xl font-bold text-gray-900">捕獲紀錄</h3>
                <span class="text-sm font-bold bg-gray-100 text-gray-800 px-3 py-1 rounded-full">{{ captureRecords.length }}</span>
              </div>
              <Link v-if="user" :href="`/fish/${fish.id}/capture-records/create`" class="hidden lg:inline-flex items-center gap-1 text-sm text-teal-600 hover:text-teal-700 font-medium">
                <span class="text-lg leading-none">+</span> 新增照片
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
                    :src="record.url" 
                    :alt="`${fish.name} 捕獲紀錄`"
                    class="w-full h-auto object-cover rounded-lg shadow-sm border border-gray-100"
                 />
                 
                 <!-- Photographer -->
                 <div class="pt-1 text-xs text-gray-400 text-right">
                    拍攝者：{{ record.photographer || '匿名' }} · {{ record.date }}
                 </div>
              </div>
            </div>
            
            <div v-else class="text-center py-12 bg-gray-50 rounded-lg">
               <p class="text-gray-500 mb-4">目前還沒有捕獲紀錄照片</p>
               <Link v-if="user" :href="`/fish/${fish.id}/capture-records/create`" class="inline-flex px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 font-medium shadow-sm transition-colors">
                  上傳第一張照片
               </Link>
            </div>
          </div>
        </section>
      </template>
  
      <!-- 右欄：知識與發音 -->
      <template #right>
        <!-- 魚類知識卡片 -->
        <FishKnowledgeCard 
          :fishId="fish.id" 
          :notes="groupedNotes['knowledge']" 
          title="魚類知識"
          type="knowledge"
        />
  
        <!-- 傳說故事卡片 -->
        <FishKnowledgeCard 
          :fishId="fish.id" 
          :notes="groupedNotes['story']" 
          title="傳說與故事"
          type="story"
        />
        
         <!-- 食用分級卡片 -->
         <FishKnowledgeCard 
          :fishId="fish.id" 
          :notes="groupedNotes['eating']" 
          title="食用分級"
          type="eating"
        />
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
import FishKnowledgeCard from '@/Components/FishKnowledgeCard.vue'

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
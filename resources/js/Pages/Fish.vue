<template>
  <Head :title="`${fish.name}的基本資料`" />
  
  <div class="min-h-screen bg-gray-50 pb-[calc(6rem+env(safe-area-inset-bottom))]">
    <!-- 頂部導覽列 -->
    <header class="sticky top-0 z-30 bg-white/90 backdrop-blur-md shadow-sm border-b border-gray-100">
      <div class="container mx-auto max-w-2xl px-4 h-14 flex items-center gap-2">
        <Link href="/fishs" class="text-gray-600 hover:text-blue-600 p-1 rounded-full hover:bg-gray-100 transition">
           <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </Link>
        <h1 class="text-lg font-bold text-gray-900 truncate">{{ fish.name }}</h1>
      </div>
    </header>

    <!-- 響應式佈局容器 -->
    <main class="container mx-auto max-w-7xl px-4 py-6">
      
      <div class="lg:grid lg:grid-cols-2 lg:gap-8 lg:items-start">
        
        <!-- 左欄：核心識別 & 地方知識 (Desktop Sticky) -->
        <div class="space-y-6 lg:sticky lg:top-20">
          <!-- 1. 核心識別區塊 (照片、魚名、發音) -->
          <section>
            <FishDetailLeft :fish="fish" />
          </section>

          <!-- 2. 地方知識 (Tribal Classifications) -->
          <section>
            <TribalClassificationSummary 
              :classifications="tribalClassifications" 
              :fishId="fish.id" 
            />
          </section>
        </div>

        <!-- 右欄：捕獲紀錄 & 進階知識 (Desktop Scrollable) -->
        <div class="space-y-6 mt-6 lg:mt-0 lg:h-[calc(100vh-8rem)] lg:overflow-y-auto lg:pr-2 scrollbar-hide">
          
          <!-- 3. 捕獲紀錄區塊 (Photo Grid) -->
          <section>
             <div class="rounded-xl bg-white shadow-sm border border-gray-200 p-4">
              <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
                <h3 class="text-xl font-semibold text-gray-900">捕獲紀錄</h3>
                <span class="text-sm text-gray-500">{{ captureRecords.length }} 筆資料</span>
              </div>

              <div v-if="captureRecords.length" class="grid grid-cols-2 md:grid-cols-3 gap-3">
                <div 
                  v-for="record in captureRecords" 
                  :key="record.id"
                  class="relative aspect-square rounded-lg overflow-hidden bg-gray-100 border border-gray-200"
                >
                  <LazyImage
                    :src="record.image_url"
                    :alt="`捕獲紀錄 ${record.capture_date || ''}`"
                    wrapperClass="w-full h-full"
                    imgClass="w-full h-full object-cover"
                  />
                  <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent p-2">
                    <p class="text-white text-xs font-medium truncate">
                      {{ record.tribe || '未標示部落' }}
                    </p>
                    <p class="text-white/80 text-[10px]">
                      {{ record.capture_date || '日期未知' }}
                    </p>
                  </div>
                </div>
              </div>
              <div v-else class="text-center py-8 text-gray-500">
                <p>尚未新增捕獲照片</p>
              </div>
            </div>
          </section>

          <!-- 4. 進階知識 (Fish Notes) -->
          <section>
            <div class="rounded-xl bg-white shadow-sm border border-gray-200 p-4">
              <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
                <h3 class="text-xl font-semibold text-gray-900">知識筆記</h3>
              </div>

              <div v-if="Object.keys(groupedNotes).length" class="space-y-6">
                <div v-for="(items, type) in groupedNotes" :key="type">
                  <h4 class="font-medium text-gray-800 mb-2 flex items-center">
                    <span class="w-1 h-4 bg-blue-500 rounded-full mr-2"></span>
                    {{ type }} 
                    <span class="ml-2 text-sm text-gray-500">({{ items.length }})</span>
                  </h4>
                  <ul class="space-y-3">
                    <li v-for="note in items" :key="note.id" class="bg-gray-50 rounded-lg p-3">
                      <div class="flex flex-col gap-1">
                        <span class="inline-flex self-start items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 mb-1">
                          {{ note.locate }}
                        </span>
                        <p class="text-gray-700 text-base leading-relaxed whitespace-pre-line">
                          {{ note.note }}
                        </p>
                      </div>
                    </li>
                  </ul>
                </div>
              </div>
              <div v-else class="text-center py-6 text-gray-500">
                尚無知識筆記
              </div>
            </div>
          </section>

        </div>
      </div>
    </main>

    <BottomNavBar :fishId="fish.id" activeTab="basic" />
  </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import FishDetailLeft from '@/Components/FishDetailLeft.vue'
import TribalClassificationSummary from '@/Components/TribalClassificationSummary.vue'
import BottomNavBar from '@/Components/Global/BottomNavBar.vue'
import LazyImage from '@/Components/LazyImage.vue'

const props = defineProps({
  fish: Object,
  tribalClassifications: {
    type: Array,
    default: () => [],
  },
  captureRecords: {
    type: Array,
    default: () => [],
  },
  fishNotes: {
    type: Object,
    default: () => ({}),
  },
})

// 將後端已分組的資料直接暴露為 computed
const groupedNotes = computed(() => props.fishNotes || {})
</script>

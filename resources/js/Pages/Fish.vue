<template>
  <Head :title="`${fish.name}的基本資料`" />
  
  <div class="min-h-screen bg-gray-50 pb-[calc(6rem+env(safe-area-inset-bottom))] lg:pb-6">
    <!-- 頂部導覽列 (RWD) -->
    <header class="sticky top-0 z-30 bg-white/90 backdrop-blur-md shadow-sm border-b border-gray-100">
      <div class="container mx-auto max-w-7xl px-4 h-14 flex items-center justify-between">
        <!-- Mobile Nav (< 768px/1024px) -->
        <div class="flex items-center gap-3 lg:hidden w-full">
           <Link href="/fishs" class="text-gray-600 hover:text-blue-600 flex items-center gap-1">
             <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
             <span class="text-sm font-medium">圖鑑列表</span>
           </Link>
           <h1 class="text-lg font-bold text-gray-900 mx-auto pr-8">基本資料</h1>
        </div>

        <!-- Desktop Nav (>= 1024px) -->
        <div class="hidden lg:flex items-center gap-4 w-full">
           <!-- Logo / Home -->
           <Link href="/fishs" class="font-bold text-gray-900 text-lg tracking-wide hover:text-blue-600 transition">
             雅美魚類圖鑑
           </Link>
           
           <!-- Breadcrumbs -->
           <div class="flex items-center text-sm text-gray-500 gap-2">
             <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
             <span class="font-medium text-gray-900">{{ fish.name }}</span>
           </div>

           <!-- User Menu (Right aligned) -->
           <div class="ml-auto flex items-center gap-3">
              <div v-if="user" class="text-sm font-medium text-gray-700 flex items-center gap-2">
                <span class="bg-blue-100 text-blue-800 py-1 px-3 rounded-full text-xs">田調人員</span>
                {{ user.name }}
              </div>
              <Link v-if="user" href="/logout" method="post" as="button" class="text-sm text-gray-500 hover:text-red-600">
                登出
              </Link>
              <Link v-else href="/login" class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                登入
              </Link>
           </div>
        </div>
      </div>
    </header>

    <!-- 響應式佈局容器 -->
    <main class="container mx-auto max-w-7xl px-4 py-6">
  
      <div class="lg:grid lg:grid-cols-3 lg:gap-8 lg:items-start">
        
        <!-- 左欄：核心識別 & 地方知識 (Desktop Sticky) - 維持不變 -->
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

        <!-- 中欄：捕獲紀錄 (Desktop Scrollable) -->
        <div class="space-y-6 mt-6 lg:mt-0 lg:h-[calc(100vh-8rem)] lg:overflow-y-auto lg:px-2 scrollbar-hide">
          <!-- 3. 捕獲紀錄區塊 (Photo Grid) -->
          <section>
             <div class="rounded-xl bg-white shadow-sm border border-gray-200 p-4">
              <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
                <div class="flex items-center gap-2">
                   <h3 class="text-xl font-semibold text-gray-900">捕獲紀錄</h3>
                   <span class="text-xs font-medium bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ captureRecords.length }}</span>
                </div>
                
                <!-- Desktop Add Button -->
                <Link 
                  v-if="user"
                  :href="`/fish/${fish.id}/capture-records/create`" 
                  class="hidden lg:inline-flex items-center gap-1 text-sm text-blue-600 font-medium hover:text-blue-800 hover:bg-blue-50 px-3 py-1 rounded-md transition"
                >
                  <span class="text-lg leading-none">+</span> 新增照片
                </Link>
              </div>

              <div v-if="captureRecords.length" class="space-y-8">
                <div 
                  v-for="record in captureRecords" 
                  :key="record.id"
                  class="flex flex-col gap-3"
                >
                  <!-- 16:9 圖片 -->
                  <div class="relative aspect-video rounded-xl overflow-hidden bg-gray-100 border border-gray-200 shadow-sm">
                    <LazyImage
                      :src="record.image_url"
                      :alt="`捕獲紀錄`"
                      wrapperClass="w-full h-full"
                      imgClass="w-full h-full object-cover"
                    />
                  </div>
                  
                  <!-- 圖片下方資訊：部落、地點、方法 -->
                  <div class="px-1">
                    <div class="flex items-center flex-wrap gap-2 mb-1.5">
                       <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-bold bg-blue-50 text-blue-700">
                         {{ record.tribe || '未標示部落' }}
                       </span>
                       <span v-if="record.capture_location" class="text-gray-700 font-medium text-sm flex items-center gap-1">
                          <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                          {{ record.capture_location }}
                       </span>
                    </div>
                    <p class="text-sm text-gray-600 flex items-center gap-2">
                       <span class="font-medium text-gray-500">捕獲方法：</span>
                       {{ record.capture_method || '未記錄' }}
                    </p>
                  </div>
                </div>
              </div>
              <div v-else class="text-center py-8 text-gray-500">
                <p>尚未新增捕獲照片</p>
              </div>
            </div>
          </section>
        </div>

        <!-- 右欄：進階知識 (Desktop Scrollable) -->
        <div class="space-y-6 mt-6 lg:mt-0 lg:h-[calc(100vh-8rem)] lg:overflow-y-auto lg:pl-2 scrollbar-hide">
          <!-- 4. 進階知識 (Fish Notes) -->
          <section>
            <div class="rounded-xl bg-white shadow-sm border border-gray-200 p-4">
              <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
                <h3 class="text-xl font-semibold text-gray-900">知識筆記</h3>
                <!-- Desktop Action Buttons -->
                <div v-if="user" class="hidden lg:flex items-center gap-2">
                  <Link 
                    :href="`/fish/${fish.id}/create`" 
                    class="inline-flex items-center gap-1 text-sm text-teal-600 font-medium hover:text-teal-800 hover:bg-teal-50 px-3 py-1 rounded-md transition"
                  >
                    <span class="text-lg leading-none">+</span> 新增進階知識
                  </Link>
                </div>
              </div>

              <!-- 知識卡片列表 ... (省略內容，假設內容不變) -->
              <div v-if="Object.keys(groupedNotes).length" class="space-y-6">
                  <div v-for="(items, type) in groupedNotes" :key="type">
                    <h4 class="font-medium text-gray-800 mb-2 px-1">
                      <span class="w-1 h-4 bg-blue-500 rounded-full mr-2"></span>
                      {{ type }} 
                      <span class="ml-2 text-sm text-gray-500">({{ items.length }})</span>
                    </h4>
                    <ul class="space-y-3">
                      <li 
                        v-for="note in items" 
                        :key="note.id" 
                        class="bg-gray-50 rounded-lg p-3 border border-gray-200"
                      >
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
              <div v-else class="text-center py-8 text-gray-500">
                <p>尚未建立知識筆記</p>
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
import { Head, Link, usePage } from '@inertiajs/vue3'
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

// 取得當前使用者狀態 for RWD 顯示控制
const page = usePage()
const user = computed(() => page.props.auth?.user)

// 將後端已分組的資料直接暴露為 computed
const groupedNotes = computed(() => props.fishNotes || {})
</script>

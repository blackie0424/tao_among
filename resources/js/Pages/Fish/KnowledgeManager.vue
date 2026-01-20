<template>
  <Head :title="`${fish.name} - 知識筆記管理`" />
  
  <div class="min-h-screen bg-gray-50 pb-[calc(6rem+env(safe-area-inset-bottom))]">
    <main class="container mx-auto max-w-2xl px-4 py-6 space-y-8">
      
      <!-- 頂部返回 -->
      <div class="flex items-center gap-2 mb-4">
        <a :href="`/fish/${fish.id}`" class="text-blue-600 font-medium flex items-center gap-1">
           <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
           {{ fish.name }}
        </a>
        <span class="text-gray-400">/</span>
        <span class="text-gray-900 font-bold">知識筆記管理</span>
      </div>

      <!-- 區塊 S: 基本資料管理 (原 overflow menu 功能移至此) -->
      <section>
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-xl font-bold flex items-center gap-2">
            <span>⚙️</span> 基本資料管理
          </h2>
        </div>
        
        <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-200">
           <div class="flex flex-col md:flex-row items-center gap-4">
              <!-- 魚類圖片縮圖 -->
              <div class="w-24 h-24 flex-shrink-0 bg-gray-100 rounded-lg overflow-hidden border border-gray-200">
                  <LazyImage
                    :src="fish.display_image_url || fish.image_url"
                    :alt="fish.name"
                    wrapperClass="w-full h-full"
                    imgClass="w-full h-full object-cover"
                  />
              </div>

              <div class="flex-1 flex flex-col md:flex-row justify-between items-center w-full gap-4">
                <div class="text-center md:text-left">
                   <div class="text-sm text-gray-500 mb-1">魚類名稱</div>
                   <div class="text-2xl font-bold text-gray-900 flex items-center justify-center md:justify-start gap-2">
                      {{ fish.name }}
                   </div>
                </div>
                
                <div class="flex flex-wrap gap-2 w-full md:w-auto justify-center md:justify-end">
                   <a :href="`/fish/${fish.id}/edit`" class="flex-1 md:flex-none inline-flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                      修改名稱
                   </a>
                   <a :href="`/fish/${fish.id}/merge`" class="flex-1 md:flex-none inline-flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                      合併魚類
                   </a>
                   <button @click="confirmDelete" class="flex-1 md:flex-none inline-flex items-center justify-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                      刪除魚類
                   </button>
                </div>
              </div>
           </div>
        </div>
      </section>

      <!-- 區塊 A: 地方知識 (Tribal Classifications) -->
      <section>
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-xl font-bold flex items-center gap-2">
            <span>🏝️</span> 地方知識
          </h2>
        </div>
        
        <div class="space-y-3">
          <div v-if="tribalClassifications.length > 0">
             <div 
               v-for="item in tribalClassifications" 
               :key="item.id"
               class="bg-white rounded-lg p-4 shadow-sm border border-gray-200"
             >
                <div class="flex justify-between items-start mb-2">
                   <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                     {{ item.tribe }}
                   </span>
                   <!-- 編輯/刪除 -->
                   <div class="flex gap-2">
                      <a :href="`/fish/${fish.id}/tribal-classifications/${item.id}/edit`" class="text-gray-400 hover:text-blue-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                      </a>
                   </div>
                </div>
                <div class="text-sm text-gray-700 space-y-1">
                   <p><span class="font-medium text-gray-500">分類：</span> {{ item.food_category || '無' }}</p>
                   <p><span class="font-medium text-gray-500">處理：</span> {{ item.processing_method || '無' }}</p>
                </div>
             </div>
          </div>
          <div v-else class="text-gray-500 text-center py-8 bg-white rounded-xl border border-dashed border-gray-300">
             尚未建立地方知識
          </div>
        </div>
      </section>

      <!-- 區塊 B: 進階知識 (Fish Notes) -->
      <section>
        <div class="flex items-center justify-between mb-4">
           <h2 class="text-xl font-bold flex items-center gap-2">
            <span>📖</span> 進階知識
          </h2>
        </div>

        <div v-if="Object.keys(groupedNotes).length" class="space-y-6">
            <div v-for="(items, type) in groupedNotes" :key="type">
              <h4 class="font-medium text-gray-800 mb-2 px-1">{{ type }}</h4>
              <ul class="space-y-3">
                <li 
                  v-for="note in items" 
                  :key="note.id" 
                  class="bg-white rounded-lg p-4 shadow-sm border border-gray-200"
                >
                  <div class="flex justify-between items-start gap-3">
                    <div class="flex-1">
                         <span class="inline-flex self-start items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 mb-2">
                           {{ note.locate }}
                         </span>
                         <div class="text-gray-800 md:text-lg whitespace-pre-line">{{ note.note }}</div>
                    </div>
                    <!-- 編輯 Action -->
                     <a :href="`/fish/${fish.id}/knowledge/${note.id}/edit`" class="text-gray-400 hover:text-blue-600 p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                     </a>
                  </div>
                </li>
              </ul>
            </div>
        </div>
        <div v-else class="text-gray-500 text-center py-8 bg-white rounded-xl border border-dashed border-gray-300">
          尚未建立知識筆記
        </div>
      </section>

    </main>

    <!-- 底部 Sticky 操作按鈕 -->
    <div class="fixed bottom-0 left-0 right-0 p-4 bg-white border-t border-gray-200 z-50 flex gap-3 pb-[calc(1rem+env(safe-area-inset-bottom))]">
       <a :href="`/fish/${fish.id}/tribal-classifications/create`" class="flex-1 bg-indigo-600 text-white rounded-lg py-3 flex items-center justify-center gap-2 font-medium shadow-lg hover:bg-indigo-700 active:scale-[0.98] transition-transform">
         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
         新增地方知識
       </a>
       <a :href="`/fish/${fish.id}/create`" class="flex-1 bg-teal-600 text-white rounded-lg py-3 flex items-center justify-center gap-2 font-medium shadow-lg hover:bg-teal-700 active:scale-[0.98] transition-transform min-w-[120px]">
         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
         新增進階知識
       </a>
    </div>

  </div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import LazyImage from '@/Components/LazyImage.vue'

const props = defineProps({
  fish: Object,
  tribalClassifications: { type: Array, default: () => [] },
  fishNotes: { type: Object, default: () => ({}) }
})

const groupedNotes = computed(() => props.fishNotes || {})

const confirmDelete = () => {
  if (confirm('確定要刪除這隻魚類資料嗎？此動作無法復原。')) {
    router.delete(`/fish/${props.fish.id}`)
  }
}
</script>

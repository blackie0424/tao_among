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

    <!-- BottomNavBar 依舊保留 -->
    <BottomNavBar :fishId="fish.id" activeTab="knowledge" />
  </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import BottomNavBar from '@/Components/Global/BottomNavBar.vue'

const props = defineProps({
  fish: Object,
  tribalClassifications: { type: Array, default: () => [] },
  fishNotes: { type: Object, default: () => ({}) }
})

const groupedNotes = computed(() => props.fishNotes || {})
</script>

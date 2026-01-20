<template>
  <Head :title="`${fish.name} - 影音紀錄管理`" />
  
  <div class="min-h-screen bg-gray-50 pb-[calc(6rem+env(safe-area-inset-bottom))]">
    <main class="container mx-auto max-w-2xl px-4 py-6 space-y-8">
      
      <!-- 頂部返回 -->
      <div class="flex items-center gap-2 mb-4">
        <a :href="`/fish/${fish.id}`" class="text-blue-600 font-medium flex items-center gap-1">
           <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
           {{ fish.name }}
        </a>
        <span class="text-gray-400">/</span>
        <span class="text-gray-900 font-bold">影音紀錄管理</span>
      </div>

      <!-- 區塊 A: 捕獲照片 (Grid) -->
      <section>
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-xl font-bold flex items-center gap-2">
            <span>📸</span> 捕獲照片
          </h2>
        </div>
        
        <div v-if="captureRecords.length" class="grid grid-cols-2 gap-4">
          <div 
             v-for="record in captureRecords" 
             :key="record.id" 
             class="bg-white rounded-lg shadow-sm overflow-hidden border border-gray-200"
          >
             <div class="aspect-square relative bg-gray-100">
               <LazyImage
                :src="record.image_url"
                :alt="`捕獲紀錄`"
                wrapperClass="w-full h-full"
                imgClass="w-full h-full object-cover"
              />
              <!-- 編輯按鈕 -->
               <a 
                 :href="`/fish/${fish.id}/capture-records/${record.id}/edit`"
                 class="absolute top-2 right-2 bg-white/90 p-1.5 rounded-full shadow-sm text-gray-600 hover:text-blue-600"
               >
                 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
               </a>
             </div>
             <div class="p-3">
               <div class="text-sm font-medium text-gray-900">{{ record.tribe}}</div>
               <div class="text-xs text-gray-500 mt-1">{{ formatDate(record.capture_date) }}</div>
             </div>
          </div>
        </div>
        <div v-else class="text-gray-500 text-center py-8 bg-white rounded-xl border border-dashed border-gray-300">
          尚未新增捕獲照片
        </div>
      </section>

      <!-- 區塊 B: 發音錄音 (List) -->
      <section>
        <div class="flex items-center justify-between mb-4">
           <h2 class="text-xl font-bold flex items-center gap-2">
            <span>🔊</span> 發音錄音
          </h2>
        </div>

        <div v-if="fish.audios && fish.audios.length" class="space-y-3">
           <div 
             v-for="audio in fish.audios" 
             :key="audio.id"
             class="bg-white rounded-lg p-4 shadow-sm border border-gray-200 flex items-center justify-between"
           >
             <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-500">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 14.142M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path></svg>
                </div>
                <div>
                   <div class="font-medium text-gray-900">{{ getAudioLabel(audio) }}</div>
                   <div class="text-xs text-gray-500 mt-0.5">{{ new Date(audio.created_at).toLocaleDateString() }}</div>
                </div>
             </div>
             
             <div class="flex items-center gap-2">
                <audio :src="audio.url" controls class="h-8 w-24 md:w-40"></audio>
                <!-- 編輯/刪除連結，可使用下拉選單或直接連結，這裡為簡化使用編輯鈕 -->
                <a :href="`/fish/${fish.id}/audio/${audio.id}/edit`" class="text-gray-400 hover:text-blue-600 p-2">
                   <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                </a>
             </div>
           </div>
        </div>
        <div v-else class="text-gray-500 text-center py-8 bg-white rounded-xl border border-dashed border-gray-300">
          尚未新增發音錄音
        </div>
      </section>

    </main>

    <!-- 底部 Sticky 操作按鈕 -->
    <div class="fixed bottom-0 left-0 right-0 p-4 bg-white border-t border-gray-200 z-50 flex gap-3 pb-[calc(1rem+env(safe-area-inset-bottom))]">
       <a :href="`/fish/${fish.id}/capture-records/create`" class="flex-1 bg-blue-600 text-white rounded-lg py-3 flex items-center justify-center gap-2 font-medium shadow-lg hover:bg-blue-700 active:scale-[0.98] transition-transform">
         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
         新增照片
       </a>
       <a :href="`/fish/${fish.id}/createAudio`" class="flex-1 bg-rose-600 text-white rounded-lg py-3 flex items-center justify-center gap-2 font-medium shadow-lg hover:bg-rose-700 active:scale-[0.98] transition-transform">
         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18l9-4-9-4-9 4 9 4zm0 0v-8"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 10a7 7 0 11-14 0"></path></svg>
         新增錄音
       </a>
    </div>

  </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import LazyImage from '@/Components/LazyImage.vue'

const props = defineProps({
  fish: Object,
  captureRecords: { type: Array, default: () => [] }
})

const formatDate = (dateString) => {
    if(!dateString) return '未記錄日期';
    return dateString;
}

const getAudioLabel = (audio) => {
    // 若有自訂名稱顯示名稱，否則顯示預設
    // 這裡假設後端沒有特別欄位，就顯示 ID 或 類型
    return `錄音 #${audio.id}`
}
</script>

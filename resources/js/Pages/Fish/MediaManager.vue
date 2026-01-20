<template>
  <Head :title="`${fish.name} - 影音紀錄管理`" />
  
  <div class="min-h-screen bg-gray-50 pb-[calc(6rem+env(safe-area-inset-bottom))]">
    <main class="container mx-auto max-w-2xl px-4 py-6 space-y-8">
      
      <!-- 頂部返回 -->
      <div class="flex items-center gap-2 mb-4">
        <Link :href="`/fish/${fish.id}`" class="text-blue-600 font-medium flex items-center gap-1">
           <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
           {{ fish.name }}
        </Link>
        <span class="text-gray-400">/</span>
        <span class="text-gray-900 font-bold">影音紀錄管理</span>
      </div>

      <!-- 區塊 A: 捕獲照片 (Grid) -->
      <section>
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-xl font-bold flex items-center gap-2">
            <span>📸</span> 捕獲照片
          </h2>
          <Link 
            :href="`/fish/${fish.id}/capture-records/create`" 
            class="flex items-center gap-1 text-sm bg-blue-100 text-blue-700 px-3 py-1.5 rounded-full font-medium active:scale-95 transition-transform"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            新增照片
          </Link>
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
          <Link 
            :href="`/fish/${fish.id}/createAudio`" 
            class="flex items-center gap-1 text-sm bg-rose-100 text-rose-700 px-3 py-1.5 rounded-full font-medium active:scale-95 transition-transform"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            新增錄音
          </Link>
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
                
                <!-- 操作按鈕區 -->
                <div class="flex items-center gap-1">
                   <!-- 狀態/設為主發音 -->
                   <span v-if="audio.name === fish.audio_filename" class="text-xs font-bold text-green-600 bg-green-100 px-2 py-1 rounded">
                      主發音
                   </span>
                   <button 
                      v-else
                      @click="setMainAudio(audio)"
                      class="text-xs bg-blue-100 text-blue-600 hover:bg-blue-200 px-2 py-1 rounded transition-colors"
                      title="設為主要發音"
                   >
                      設為主發音
                   </button>

                   <!-- 刪除 -->
                   <button 
                      v-if="audio.name !== fish.audio_filename"
                      @click="deleteAudio(audio)"
                      class="text-gray-400 hover:text-red-600 p-2 rounded-full hover:bg-red-50 transition-colors"
                      title="刪除"
                   >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                   </button>
                </div>
             </div>
           </div>
        </div>
        <div v-else class="text-gray-500 text-center py-8 bg-white rounded-xl border border-dashed border-gray-300">
          尚未新增發音錄音
        </div>
      </section>

    </main>

    <BottomNavBar :fishId="fish.id" activeTab="media" />
  </div>
</template>

<script setup>
import { Head, router, Link } from '@inertiajs/vue3'
import LazyImage from '@/Components/LazyImage.vue'
import BottomNavBar from '@/Components/Global/BottomNavBar.vue'

const props = defineProps({
  fish: Object,
  captureRecords: { type: Array, default: () => [] }
})

const formatDate = (dateString) => {
    if(!dateString) return '未記錄日期';
    return dateString;
}

const getAudioLabel = (audio) => {
    return `錄音 #${audio.id}`
}

const setMainAudio = (audio) => {
    if (confirm('確定要將此檔案設為主要發音嗎？')) {
        router.put(`/fish/${props.fish.id}/audio/${audio.id}/set-base`)
    }
}

const deleteAudio = (audio) => {
    if (confirm('確定要刪除此發音檔案嗎？此動作無法復原。')) {
        router.delete(`/fish/${props.fish.id}/audio/${audio.id}`)
    }
}
</script>

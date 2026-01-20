<template>
  <Head :title="`${fish.name} - 影音紀錄管理`" />
  
  <div class="min-h-screen bg-gray-50 pb-[calc(6rem+env(safe-area-inset-bottom))] lg:pb-6">
    <!-- 頂部導覽列 (RWD) -->
    <header class="sticky top-0 z-30 bg-white/90 backdrop-blur-md shadow-sm border-b border-gray-100">
      <div class="container mx-auto max-w-7xl px-4 h-14 flex items-center justify-between">
        <!-- Mobile Nav (< 1024px) -->
        <div class="flex items-center gap-3 lg:hidden w-full">
           <Link :href="`/fish/${fish.id}`" class="text-gray-600 hover:text-blue-600 flex items-center gap-1">
             <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
             <span class="text-sm font-medium">返回</span>
           </Link>
           <h1 class="text-lg font-bold text-gray-900 mx-auto pr-8">影音紀錄管理</h1>
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
             <Link :href="`/fish/${fish.id}`" class="hover:text-blue-600 transition">{{ fish.name }}</Link>
             <span class="text-gray-300">/</span>
             <span class="font-medium text-gray-900">影音紀錄</span>
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
           </div>
        </div>
      </div>
    </header>

    <!-- 響應式佈局容器 -->
    <main class="container mx-auto max-w-7xl px-4 py-6">
      <div class="lg:grid lg:grid-cols-2 lg:gap-8 lg:items-start">
        
        <!-- 左欄：核心識別 (Desktop Sticky) -->
        <div class="space-y-6 lg:sticky lg:top-20 hidden lg:block">
          <section>
            <FishDetailLeft :fish="fish" />
          </section>
        </div>

        <!-- 右欄：影音管理內容 -->
        <div class="space-y-6 lg:h-[calc(100vh-8rem)] lg:overflow-y-auto lg:pr-2 scrollbar-hide">
          
          <!-- 區塊 A: 捕獲照片 -->
          <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
              <h2 class="text-xl font-bold flex items-center gap-2 text-gray-900">
                <span>📸</span> 捕獲照片
              </h2>
              <Link 
                :href="`/fish/${fish.id}/capture-records/create`" 
                class="flex items-center gap-1 text-sm bg-blue-100 text-blue-700 px-3 py-1.5 rounded-md font-medium hover:bg-blue-200 transition"
              >
                <span class="text-lg leading-none">+</span> 新增照片
              </Link>
            </div>
            
            <div v-if="captureRecords.length" class="grid grid-cols-2 gap-4">
              <div 
                 v-for="record in captureRecords" 
                 :key="record.id" 
                 class="bg-white rounded-lg overflow-hidden border border-gray-200 relative group"
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
                     class="absolute top-2 right-2 bg-white/90 p-1.5 rounded-full shadow-sm text-gray-600 hover:text-blue-600 opacity-100 lg:opacity-0 lg:group-hover:opacity-100 transition-opacity"
                   >
                     <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                   </a>
                 </div>
                 <div class="p-2 text-center">
                   <div class="text-sm font-medium text-gray-900">{{ record.tribe || '未標示' }}</div>
                   <div class="text-xs text-gray-500">{{ formatDate(record.capture_date) }}</div>
                 </div>
              </div>
            </div>
            <div v-else class="text-gray-500 text-center py-8 border border-dashed border-gray-300 rounded-lg">
              尚未新增捕獲照片
            </div>
          </section>

          <!-- 區塊 B: 發音錄音 -->
          <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
               <h2 class="text-xl font-bold flex items-center gap-2 text-gray-900">
                <span>🔊</span> 發音錄音
              </h2>
              <Link 
                :href="`/fish/${fish.id}/createAudio`" 
                class="flex items-center gap-1 text-sm bg-rose-100 text-rose-700 px-3 py-1.5 rounded-md font-medium hover:bg-rose-200 transition"
              >
                <span class="text-lg leading-none">+</span> 新增錄音
              </Link>
            </div>

            <div v-if="fish.audios && fish.audios.length" class="space-y-3">
               <div 
                 v-for="audio in fish.audios" 
                 :key="audio.id"
                 class="bg-gray-50 rounded-lg p-3 border border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-3"
               >
                 <div class="flex items-center gap-3 w-full sm:w-auto">
                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 flex-shrink-0">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 14.142M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path></svg>
                    </div>
                    <div>
                       <div class="font-medium text-sm text-gray-900">{{ getAudioLabel(audio) }}</div>
                       <div class="text-xs text-gray-500">{{ new Date(audio.created_at).toLocaleDateString() }}</div>
                    </div>
                 </div>
                 
                 <div class="flex items-center gap-3 w-full sm:w-auto justify-between sm:justify-end">
                    <audio :src="audio.url" controls class="h-8 w-32 md:w-48"></audio>
                    
                    <div class="flex items-center gap-1">
                       <span v-if="audio.name === fish.audio_filename" class="text-xs font-bold text-green-600 bg-green-100 px-2 py-1 rounded whitespace-nowrap">
                          主發音
                       </span>
                       <button 
                          v-else
                          @click="setMainAudio(audio)"
                          class="text-xs bg-blue-50 text-blue-600 hover:bg-blue-100 px-2 py-1 rounded transition-colors whitespace-nowrap"
                          title="設為主要發音"
                       >
                          設為主
                       </button>

                       <button 
                          v-if="audio.name !== fish.audio_filename"
                          @click="deleteAudio(audio)"
                          class="text-gray-400 hover:text-red-600 p-1.5 rounded-full hover:bg-red-50 transition-colors"
                          title="刪除"
                       >
                          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                       </button>
                    </div>
                 </div>
               </div>
            </div>
            <div v-else class="text-gray-500 text-center py-8 border border-dashed border-gray-300 rounded-lg">
              尚未新增發音錄音
            </div>
          </section>

        </div>
      </div>
    </main>

    <BottomNavBar :fishId="fish.id" activeTab="media" />
  </div>
</template>

<script setup>
import { Head, router, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import LazyImage from '@/Components/LazyImage.vue'
import BottomNavBar from '@/Components/Global/BottomNavBar.vue'
import FishDetailLeft from '@/Components/FishDetailLeft.vue'

const props = defineProps({
  fish: Object,
  captureRecords: { type: Array, default: () => [] }
})

const page = usePage()
const user = computed(() => page.props.auth?.user)

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

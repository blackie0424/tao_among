<!-- filepath: resources/js/Components/FishDetailLeft.vue -->
<template>
  <div class="w-full flex flex-col items-center">
    <!-- 手機使用較小底距，桌面維持較大空間以保持排版 -->
    <div class="w-full relative bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <!-- 圖片區域：移除個別圓角，由外層容器控制 -->
      <div class="relative aspect-[4/3] bg-gray-100">
        <LazyImage
          :src="fish.display_image_url || fish.image_url"
          :alt="fish.name"
          wrapperClass="w-full h-full"
          imgClass="w-full h-full object-cover"
        />
      </div>

      <!-- 魚名與發音區塊 -->
      <div class="flex items-center justify-between p-4 bg-white border-t border-gray-100">
         
         <!-- 左側：閱讀區 (魚名 + 發音) -->
         <div class="flex items-center gap-3">
           <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ fish.name }}</h1>
           
           <!-- Audio Player -->
           <div v-if="fish.audio_url" class="flex-shrink-0">
               <Volume :audioUrl="fish.audio_url" />
           </div>
         </div>

         <!-- 右側：管理區 (編輯 + 新增錄音) -->
         <div v-if="user" class="hidden lg:flex items-center gap-1">
            <!-- Edit Fish Button -->
            <Link :href="`/fish/${fish.id}/edit`" class="text-gray-400 hover:text-blue-600 p-2 rounded-full hover:bg-gray-50 transition" title="修改基本資料">
               <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
            </Link>

            <!-- Add Audio Button -->
            <Link :href="`/fish/${fish.id}/createAudio`" class="text-gray-400 hover:text-rose-600 p-2 rounded-full hover:bg-gray-50 transition" title="新增錄音">
               <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path></svg>
            </Link>
         </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import Volume from '@/Components/Volume.vue'
import LazyImage from '@/Components/LazyImage.vue'
import { usePage, Link } from '@inertiajs/vue3'
import { computed } from 'vue'

defineProps({
  fish: Object,
})

const page = usePage()
const user = computed(() => page.props.auth?.user)
</script>

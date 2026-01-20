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

      <!-- 魚名與發音區塊：移除頂部圓角與邊框 -->
      <div class="flex items-center justify-between p-4 bg-white border-t border-gray-100">
         <div class="flex items-center gap-4">
           <h1 class="text-3xl font-bold text-gray-900 tracking-tight">{{ fish.name }}</h1>
           
           <!-- Desktop Actions -->
           <div v-if="user" class="hidden lg:flex items-center gap-2">
              <Link :href="`/fish/${fish.id}/edit`" class="text-gray-400 hover:text-blue-600 p-1" title="修改名稱/基本資料">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
              </Link>
           </div>
         </div>

         <div class="flex items-center gap-2">
            <!-- Desktop Audio Add Action -->
            <Link v-if="user" :href="`/fish/${fish.id}/createAudio`" class="hidden lg:block text-gray-400 hover:text-rose-600 p-1" title="新增/管理錄音">
               <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            </Link>
            
            <div v-if="fish.audio_url" class="flex-shrink-0">
                <Volume :audioUrl="fish.audio_url" />
            </div>
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

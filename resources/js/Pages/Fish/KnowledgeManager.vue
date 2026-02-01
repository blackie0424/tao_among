<template>
  <Head :title="`${fish.name} - 基本資料與地方知識`" />
  
  <FishGridLayout :hideLeftOnMobile="true">
    <!-- 中欄：基本管理 & 地方知識 -->
    <template #middle>
      <!-- 區塊 S: 基本資料管理 (Mobile: Full Card; Desktop: Actions Only) -->
      <section>
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-xl font-bold flex items-center gap-2 text-gray-900">
            <span>⚙️</span> 基本資料管理
          </h2>
        </div>
        
        <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-200">
          <!-- Mobile View layout (保留原樣) -->
          <div class="lg:hidden flex flex-col md:flex-row items-center gap-4">
            <div class="w-24 h-24 flex-shrink-0 bg-gray-100 rounded-lg overflow-hidden border border-gray-200">
              <LazyImage
                :src="fish.display_image_url || fish.image_url"
                :alt="fish.name"
                wrapperClass="w-full h-full"
                imgClass="w-full h-full object-cover"
              />
            </div>
            <div class="flex-1 w-full text-center md:text-left">
              <div class="text-2xl font-bold text-gray-900 mb-4">{{ fish.name }}</div>
              <div class="flex flex-col gap-2">
                <a :href="`/fish/${fish.id}/edit`" class="block w-full text-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">修改名稱</a>
                <a :href="`/fish/${fish.id}/merge`" class="block w-full text-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">合併魚類</a>
                <button @click="confirmDelete" class="block w-full text-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700">刪除魚類</button>
              </div>
            </div>
          </div>

          <!-- Desktop View layout (純按鈕列，隱藏 redundant info) -->
          <div class="hidden lg:flex gap-4">
            <a :href="`/fish/${fish.id}/edit`" class="flex-1 inline-flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
              修改名稱
            </a>
            <a :href="`/fish/${fish.id}/merge`" class="flex-1 inline-flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
              合併魚類
            </a>
            <button @click="confirmDelete" class="flex-1 inline-flex items-center justify-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 transition">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
              刪除魚類
            </button>
          </div>
        </div>
      </section>

      <!-- 區塊 A: 地方知識 -->
      <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
          <h2 class="text-xl font-bold flex items-center gap-2 text-gray-900">
            <span>🏝️</span> 地方知識
          </h2>
          <Link 
            :href="`/fish/${fish.id}/tribal-classifications/create`" 
            class="flex items-center gap-1 text-sm bg-indigo-100 text-indigo-700 px-3 py-1.5 rounded-md font-medium hover:bg-indigo-200 transition"
          >
            <span class="text-lg leading-none">+</span> 新增地方知識
          </Link>
        </div>
        
        <div class="space-y-3">
          <div v-if="tribalClassifications.length > 0">
            <div 
              v-for="item in tribalClassifications" 
              :key="item.id"
              class="bg-gray-50 rounded-lg p-4 border border-gray-200 mb-3 last:mb-0"
            >
              <div class="flex justify-between items-start mb-2 block">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                  {{ item.tribe }}
                </span>
                <a :href="`/fish/${fish.id}/tribal-classifications/${item.id}/edit`" class="text-gray-400 hover:text-blue-600 p-1">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                </a>
              </div>
              <div class="text-sm text-gray-700 space-y-1">
                <p><span class="font-medium text-gray-500">分類：</span> {{ item.food_category || '無' }}</p>
                <p><span class="font-medium text-gray-500">處理：</span> {{ item.processing_method || '無' }}</p>
              </div>
            </div>
          </div>
          <div v-else class="text-gray-500 text-center py-8 border border-dashed border-gray-300 rounded-lg">
            尚未建立地方知識
          </div>
        </div>
      </section>
    </template>

    <!-- 右欄：進階知識 -->
    <template #right>
      <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
          <h2 class="text-xl font-bold flex items-center gap-2 text-gray-900">
            <span>📖</span> 進階知識
          </h2>
          <Link 
            :href="`/fish/${fish.id}/knowledge/create`" 
            class="flex items-center gap-1 text-sm bg-teal-100 text-teal-700 px-3 py-1.5 rounded-md font-medium hover:bg-teal-200 transition"
          >
            <span class="text-lg leading-none">+</span> 新增進階知識
          </Link>
        </div>

        <div v-if="Object.keys(groupedNotes).length" class="space-y-6">
          <div v-for="(items, type) in groupedNotes" :key="type">
            <h4 class="font-medium text-gray-800 mb-2 px-1 flex items-center">
              <span class="w-1 h-4 bg-teal-500 rounded-full mr-2"></span>
              {{ type }}
            </h4>
            <ul class="space-y-3">
              <li 
                v-for="note in items" 
                :key="note.id" 
                class="bg-gray-50 rounded-lg p-4 border border-gray-200"
              >
                <div class="flex justify-between items-start gap-3">
                  <div class="flex-1">
                    <span class="inline-flex self-start items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 mb-2">
                      {{ note.locate }}
                    </span>
                    <div class="text-gray-800 md:text-lg whitespace-pre-line leading-relaxed">{{ note.note }}</div>
                  </div>
                  <!-- 操作區 -->
                  <div class="flex items-center gap-1 flex-shrink-0">
                    <!-- 編輯 Action -->
                    <a :href="`/fish/${fish.id}/knowledge/${note.id}/edit`" class="text-gray-400 hover:text-blue-600 p-1" title="編輯">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                    </a>
                    <!-- 刪除 Action -->
                    <button @click="confirmDeleteNote(note)" class="text-gray-400 hover:text-red-600 p-1" title="刪除">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </div>
        <div v-else class="text-gray-500 text-center py-8 border border-dashed border-gray-300 rounded-lg">
          尚未建立知識筆記
        </div>
      </section>
    </template>
  </FishGridLayout>
</template>

<script setup>
import { Head, router, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import FishGridLayout from '@/Layouts/FishGridLayout.vue'
import LazyImage from '@/Components/LazyImage.vue'

// 設定巢狀佈局，並傳遞 props
defineOptions({
  layout: (h, page) => h(FishAppLayout, {
    pageTitle: '基本資料與知識管理',
    activeTab: 'knowledge',
    breadcrumbPage: '基本資料與知識',
    mobileBackUrl: `/fish/${page.props.fish?.id}`,
    mobileBackText: page.props.fish?.name || '返回',
    showBottomNav: false
  }, () => page)
})

const props = defineProps({
  fish: Object,
  tribalClassifications: { type: Array, default: () => [] },
  fishNotes: { type: Object, default: () => ({}) }
})

const page = usePage()
const user = computed(() => page.props.auth?.user)
const fish = computed(() => props.fish)

const groupedNotes = computed(() => props.fishNotes || {})

const confirmDelete = () => {
  if (confirm('確定要刪除這隻魚類資料嗎？此動作無法復原。')) {
    router.delete(`/fish/${props.fish.id}`)
  }
}

const confirmDeleteNote = (note) => {
  if (confirm(`確定要刪除這則進階知識嗎？\n\n「${note.note.substring(0, 50)}${note.note.length > 50 ? '...' : ''}」\n\n此動作無法復原。`)) {
    router.delete(`/fish/${props.fish.id}/knowledge/${note.id}`)
  }
}
</script>

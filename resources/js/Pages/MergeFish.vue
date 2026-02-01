<template>
  <Head :title="`合併魚類 - ${fish.name || '未命名'}`" />
  
  <FishAppLayout
    pageTitle="合併魚類作業"
    :mobileBackUrl="`/fish/${fish.id}`"
    :mobileBackText="fish.name || '返回'"
    :showBottomNav="false"
  >
    <div class="space-y-6">
      <!-- 頁面標題 (改為副標題形式，因為 Header 已有主標題) -->
      <div class="mb-6">
        <h1 class="text-xl md:text-2xl font-bold text-gray-900">
          將其他魚類資料合併到 <span class="text-blue-700">{{ fish.name }}</span>
        </h1>
        <p class="text-gray-500 mt-1 text-sm">此操作將把選定魚類的資料（筆記、音檔、捕獲紀錄）遷移至此魚類，並刪除來源魚類。</p>
      </div>

      <!-- 主要魚類資訊卡片 -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center gap-4">
          <!-- 魚類圖片 -->
          <div class="w-full md:w-1/3">
            <LazyImage
              :src="fish.image_url"
              :alt="fish.name"
              wrapperClass="w-full h-48 bg-gray-100 rounded-lg"
              imgClass="w-full h-full object-contain"
            />
          </div>
        </div>
      </div>

      <!-- 搜尋與選擇要合併的魚類 -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-xl font-bold text-gray-900 mb-4">第一步：選擇來源魚類</h3>

        <!-- 搜尋欄位 -->
        <div class="mb-6">
          <div class="relative">
            <input
              v-model="searchQuery"
              @input="handleSearch"
              type="text"
              placeholder="搜尋魚類名稱..."
              class="w-full px-4 py-3 pl-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
            <svg
              class="w-5 h-5 text-gray-400 absolute left-4 top-1/2 transform -translate-y-1/2"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
              />
            </svg>
          </div>
        </div>

        <!-- 搜尋結果 -->
        <div v-if="searchResults.length > 0" class="space-y-3 mb-6">
          <div
            v-for="result in searchResults"
            :key="result.id"
            @click="toggleSelectFish(result.id)"
            class="flex items-center gap-4 p-4 border rounded-lg cursor-pointer transition-colors"
            :class="
              selectedFishIds.includes(result.id)
                ? 'border-blue-500 bg-blue-50'
                : 'border-gray-200 hover:border-gray-300'
            "
          >
            <input
              type="checkbox"
              :checked="selectedFishIds.includes(result.id)"
              class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500"
              @click.stop="toggleSelectFish(result.id)"
            />
            <!-- 魚類圖片 -->
            <div class="w-24 h-24 flex-shrink-0">
              <LazyImage
                :src="result.image_url"
                :alt="result.name"
                wrapperClass="w-full h-full bg-gray-100 rounded-lg"
                imgClass="w-full h-full object-contain"
              />
            </div>
            <div class="flex-1">
              <div class="font-bold text-lg text-gray-900">
                {{ result.name }}
              </div>
              <div class="text-sm text-gray-500">
                ID: {{ result.id }}
              </div>
            </div>
          </div>
        </div>

        <!-- 無搜尋結果 -->
        <div v-else-if="searchQuery && !isSearching" class="text-center py-8 text-gray-500">
          找不到符合的魚類
        </div>

        <!-- 搜尋載入中 -->
        <div v-if="isSearching" class="text-center py-8">
          <div
            class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"
          ></div>
          <div class="mt-2 text-gray-600">搜尋中...</div>
        </div>

        <!-- 已選擇的魚類摘要 -->
        <div
          v-if="selectedFishIds.length > 0"
          class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-4"
        >
          <div class="flex items-center justify-between">
            <div class="text-blue-900 font-medium">
              已選擇 <span class="font-bold text-lg">{{ selectedFishIds.length }}</span> 筆來源魚類
            </div>
            <button
              @click="clearSelection"
              class="text-blue-600 hover:text-blue-800 text-sm font-medium hover:underline"
            >
              清除選擇
            </button>
          </div>
        </div>
      </div>

      <!-- 預覽合併結果 -->
      <div v-if="previewData" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-xl font-bold text-gray-900 mb-4">第二步：確認合併結果</h3>

        <!-- 統計摘要 -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <div class="bg-blue-50 rounded-lg p-4 border border-blue-100">
            <div class="text-sm text-blue-600 mb-1 font-medium">即將轉移筆記</div>
            <div class="text-3xl font-bold text-blue-900">{{ previewData.notes_count }}</div>
          </div>
          <div class="bg-green-50 rounded-lg p-4 border border-green-100">
            <div class="text-sm text-green-600 mb-1 font-medium">即將轉移音檔</div>
            <div class="text-3xl font-bold text-green-900">{{ previewData.audios_count }}</div>
          </div>
          <div class="bg-purple-50 rounded-lg p-4 border border-purple-100">
            <div class="text-sm text-purple-600 mb-1 font-medium">即將轉移捕獲紀錄</div>
            <div class="text-3xl font-bold text-purple-900">
              {{ previewData.capture_records_count }}
            </div>
          </div>
        </div>

        <!-- 衝突提示 -->
        <div v-if="previewData.conflicts.length > 0" class="mb-6">
          <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
            <div class="flex items-start gap-3">
              <svg
                class="w-6 h-6 text-amber-600 flex-shrink-0 mt-0.5"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path
                  fill-rule="evenodd"
                  d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                  clip-rule="evenodd"
                />
              </svg>
              <div class="flex-1">
                <div class="font-bold text-amber-900 mb-2">
                  發現 {{ previewData.conflicts.length }} 個資料衝突
                </div>
                <ul class="space-y-1 text-sm text-amber-800 list-disc pl-5">
                  <li
                    v-for="(conflict, index) in previewData.conflicts"
                    :key="index"
                  >
                    <span class="font-medium">{{ conflict.type }}:</span> {{ conflict.message }}
                  </li>
                </ul>
                <div class="mt-3 text-sm text-amber-700 bg-amber-100/50 p-2 rounded">
                  <strong>處理方式：</strong> 將保留目前目標魚類（{{ fish.name }}）的資料，忽略來源魚類的衝突欄位。
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 合併策略說明 -->
        <div class="bg-gray-50 rounded-lg p-4 mb-6 text-sm text-gray-600">
          <div class="font-bold mb-2 text-gray-800">合併說明：</div>
          <ul class="space-y-1 pl-5 list-disc">
            <li>所有關聯資料（筆記、音檔、捕獲紀錄）將合併到目標魚類。</li>
            <li>被合併的來源魚類將被<span class="text-red-600 font-bold">永久刪除</span>。</li>
          </ul>
        </div>
      </div>

      <!-- 操作按鈕 -->
      <div class="flex flex-col sm:flex-row justify-end gap-4 pt-4 border-t border-gray-200">
        <button
          v-if="!previewData"
          @click="loadPreview"
          :disabled="selectedFishIds.length === 0 || isLoadingPreview"
          class="px-6 py-3 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed transition-all shadow-sm"
        >
          <span v-if="isLoadingPreview" class="flex items-center gap-2">
            <svg class="animate-spin h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            載入預覽...
          </span>
          <span v-else>預覽合併結果</span>
        </button>

        <button
          v-if="previewData"
          @click="executeMerge"
          :disabled="isMerging"
          class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-bold rounded-lg hover:from-blue-700 hover:to-blue-800 disabled:from-gray-400 disabled:to-gray-400 disabled:cursor-not-allowed transition-all shadow-md flex items-center justify-center gap-2"
        >
          <span v-if="isMerging" class="flex items-center gap-2">
            <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            合併處理中...
          </span>
          <span v-else>確認合併</span>
        </button>
      </div>

      <!-- 錯誤訊息 -->
      <div v-if="errorMessage" class="bg-red-50 border border-red-200 rounded-lg p-4 animate-fade-in-up">
        <div class="flex items-start gap-3">
          <svg class="w-6 h-6 text-red-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path
              fill-rule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
              clip-rule="evenodd"
            />
          </svg>
          <div class="flex-1">
            <div class="font-bold text-red-900">操作失敗</div>
            <div class="text-red-800 text-sm mt-1">{{ errorMessage }}</div>
          </div>
        </div>
      </div>
    </div>
  </FishAppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import LazyImage from '@/Components/LazyImage.vue'
import { markFishStale, markFishesDeleted } from '@/utils/fishListCache'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'

// 設定 Layout (已改為 template wrapper 方式)
// defineOptions({ ... }) 移除

const props = defineProps({
  fish: {
    type: Object,
    required: true,
  },
})
const searchQuery = ref('')
const searchResults = ref([])
const selectedFishIds = ref([])
const isSearching = ref(false)
const previewData = ref(null)
const isLoadingPreview = ref(false)
const isMerging = ref(false)
const errorMessage = ref('')

let searchTimeout = null

// 搜尋處理（防抖）
function handleSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(async () => {
    if (!searchQuery.value.trim()) {
      searchResults.value = []
      return
    }

    isSearching.value = true
    try {
      const response = await axios.get('/prefix/api/fishs/search', {
        params: {
          q: searchQuery.value,
          exclude: props.fish.id, // 排除自己
        },
      })
      searchResults.value = response.data.data || []
    } catch (error) {
      console.error('搜尋失敗：', error)
      errorMessage.value = '搜尋失敗，請稍後再試'
    } finally {
      isSearching.value = false
    }
  }, 300)
}

// 切換選擇魚類
function toggleSelectFish(fishId) {
  const index = selectedFishIds.value.indexOf(fishId)
  if (index > -1) {
    selectedFishIds.value.splice(index, 1)
  } else {
    selectedFishIds.value.push(fishId)
  }
  // 清除預覽資料
  previewData.value = null
}

// 清除選擇
function clearSelection() {
  selectedFishIds.value = []
  previewData.value = null
}

// 載入預覽
async function loadPreview() {
  if (selectedFishIds.value.length === 0) return

  isLoadingPreview.value = true
  errorMessage.value = ''

  try {
    const response = await axios.post('/prefix/api/fish/merge/preview', {
      target_fish_id: props.fish.id,
      source_fish_ids: selectedFishIds.value,
    })
    previewData.value = response.data.data
  } catch (error) {
    console.error('預覽失敗：', error)
    errorMessage.value = error.response?.data?.message || '預覽失敗，請稍後再試'
  } finally {
    isLoadingPreview.value = false
  }
}

// 執行合併
async function executeMerge() {
  if (!previewData.value) return

  if (
    !confirm(
      `確定要將 ${selectedFishIds.value.length} 筆魚類資料合併到「${props.fish.name}」嗎？此操作無法復原。`
    )
  ) {
    return
  }

  isMerging.value = true
  errorMessage.value = ''

  try {
    await axios.post('/prefix/api/fish/merge', {
      target_fish_id: props.fish.id,
      source_fish_ids: selectedFishIds.value,
    })

    // 標記被合併的魚類為已刪除（從 Fishs 頁面快取中移除）
    markFishesDeleted(selectedFishIds.value)
    // 標記目標魚類需要更新（因為合併後資料有變動）
    markFishStale(props.fish.id)

    // 合併成功，跳轉回魚類詳細頁面
    router.visit(`/fish/${props.fish.id}`, {
      onSuccess: () => {
        alert('合併成功！')
      },
    })
  } catch (error) {
    console.error('合併失敗：', error)
    errorMessage.value = error.response?.data?.message || '合併失敗，請稍後再試'
    isMerging.value = false
  }
}
</script>

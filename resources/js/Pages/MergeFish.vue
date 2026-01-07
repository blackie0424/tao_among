<template>
  <Head :title="`合併魚類 - ${fish.name || '未命名'}`" />
  <div class="max-w-7xl mx-auto p-4 md:p-6">
    <!-- 返回按鈕 -->
    <div class="mb-6">
      <Link
        :href="`/fish/${fish.id}`"
        class="text-blue-600 hover:text-blue-800 flex items-center gap-2"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M10 19l-7-7m0 0l7-7m-7 7h18"
          />
        </svg>
        返回魚類詳情
      </Link>
    </div>

    <!-- 頁面標題 -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900 mb-2">將其他魚類資料合併到{{ fish.name }}</h1>
    </div>

    <!-- 主要魚類資訊卡片 -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
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
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
      <h3 class="text-xl font-bold text-gray-900 mb-4">選擇要合併的魚類</h3>

      <!-- 搜尋欄位 -->
      <div class="mb-6">
        <div class="relative">
          <input
            v-model="searchQuery"
            @input="handleSearch"
            type="text"
            placeholder="魚類名稱"
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
          <div class="w-full md:w-1/3">
            <LazyImage
              :src="result.image_url"
              :alt="result.name"
              wrapperClass="w-full h-48 bg-gray-100 rounded-lg"
              imgClass="w-full h-full object-contain"
            />
          </div>
          <div class="flex-1">
            <div class="font-semibold text-gray-900">
              {{ result.name }}
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
        class="bg-blue-50 border border-blue-200 rounded-lg p-4"
      >
        <div class="flex items-center justify-between">
          <div class="text-blue-900">
            已選擇 <span class="font-bold">{{ selectedFishIds.length }}</span> 筆魚類資料
          </div>
          <button
            @click="clearSelection"
            class="text-blue-600 hover:text-blue-800 text-sm underline"
          >
            清除選擇
          </button>
        </div>
      </div>
    </div>

    <!-- 預覽合併結果 -->
    <div v-if="previewData" class="bg-white rounded-lg shadow-md p-6 mb-8">
      <h3 class="text-xl font-bold text-gray-900 mb-4">合併預覽</h3>

      <!-- 統計摘要 -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-blue-50 rounded-lg p-4">
          <div class="text-sm text-blue-600 mb-1">筆記數量</div>
          <div class="text-2xl font-bold text-blue-900">{{ previewData.notes_count }}</div>
        </div>
        <div class="bg-green-50 rounded-lg p-4">
          <div class="text-sm text-green-600 mb-1">音檔數量</div>
          <div class="text-2xl font-bold text-green-900">{{ previewData.audios_count }}</div>
        </div>
        <div class="bg-purple-50 rounded-lg p-4">
          <div class="text-sm text-purple-600 mb-1">捕獲紀錄</div>
          <div class="text-2xl font-bold text-purple-900">
            {{ previewData.capture_records_count }}
          </div>
        </div>
      </div>

      <!-- 衝突提示 -->
      <div v-if="previewData.conflicts.length > 0" class="mb-6">
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
          <div class="flex items-start gap-3">
            <svg
              class="w-6 h-6 text-yellow-600 flex-shrink-0 mt-0.5"
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
              <div class="font-semibold text-yellow-900 mb-2">
                發現 {{ previewData.conflicts.length }} 個衝突
              </div>
              <ul class="space-y-2 text-sm text-yellow-800">
                <li
                  v-for="(conflict, index) in previewData.conflicts"
                  :key="index"
                  class="flex items-start gap-2"
                >
                  <span class="font-medium">{{ conflict.type }}:</span>
                  <span>{{ conflict.message }}</span>
                </li>
              </ul>
              <div class="mt-3 text-sm text-yellow-700">
                ✓ 系統將自動處理衝突，保留主要魚類的地方知識資料
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 合併策略說明 -->
      <div class="bg-gray-50 rounded-lg p-4 mb-6">
        <div class="text-sm text-gray-700">
          <div class="font-semibold mb-2">合併策略：</div>
          <ul class="space-y-1 pl-5 list-disc">
            <li>所有筆記、音檔、捕獲紀錄將合併到主要魚類</li>
            <li>地方知識（部落分類）以主要魚類為準</li>
            <li>體型資料將選擇資料較完整的記錄</li>
            <li>被合併的魚類將被刪除</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- 操作按鈕 -->
    <div class="flex justify-end gap-4">
      <button
        @click="loadPreview"
        :disabled="selectedFishIds.length === 0 || isLoadingPreview"
        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors"
      >
        <span v-if="isLoadingPreview">載入預覽中...</span>
        <span v-else>預覽合併結果</span>
      </button>

      <button
        v-if="previewData"
        @click="executeMerge"
        :disabled="isMerging"
        class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors"
      >
        <span v-if="isMerging">合併中...</span>
        <span v-else>確認合併</span>
      </button>
    </div>

    <!-- 錯誤訊息 -->
    <div v-if="errorMessage" class="mt-6 bg-red-50 border border-red-200 rounded-lg p-4">
      <div class="flex items-start gap-3">
        <svg class="w-6 h-6 text-red-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path
            fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
            clip-rule="evenodd"
          />
        </svg>
        <div class="flex-1">
          <div class="font-semibold text-red-900 mb-1">操作失敗</div>
          <div class="text-red-800">{{ errorMessage }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import LazyImage from '../Components/LazyImage.vue'

const props = defineProps({
  fish: {
    type: Object,
    required: true,
  },
})

// 狀態管理
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

    // 合併成功，跳轉回魚類詳情頁
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

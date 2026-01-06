<template>
  <div
    :class="['relative flex items-center justify-center bg-gray-100 overflow-hidden', wrapperClass]"
    :style="wrapperStyle"
  >
    <LoadingBar :loading="loading" :error="error" type="image" loading-text="資料載入中..." />
    <img
      v-show="!loading"
      :src="currentSrc"
      :alt="alt"
      :loading="imgLoading"
      :class="['object-contain', imgClass]"
      :style="imgStyle"
      @load="onLoad"
      @error="onError"
    />
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import LoadingBar from '@/Components/LoadingBar.vue'

const props = defineProps({
  src: String,
  alt: String,
  wrapperClass: { type: String, default: '' },
  wrapperStyle: { type: [String, Object], default: '' },
  imgClass: { type: String, default: '' },
  imgStyle: { type: [String, Object], default: '' },
  imgIndex: { type: Number, default: 0 }, // 新增：圖片在列表中的索引
  defaultSrc: {
    type: String,
    default: '/images/default.png', // 預設圖片路徑
  },
})

// 從 Inertia 共享資料取得資料夾配置
const page = usePage()
const storageFolders = computed(
  () => page.props.storageFolders || { image: 'images', webp: 'webp' }
)

const loading = ref(true)
const error = ref(false)
const useWebp = ref(true) // 是否嘗試使用 webp 格式

/**
 * 將圖片 URL 轉換為 webp 格式
 * 根據後端配置的資料夾名稱進行替換
 * 例如: .../dev-images/fish.jpg -> .../dev-webp/fish.webp
 */
function toWebpUrl(url) {
  if (!url) return url
  // 若已經是 webp 格式則直接返回
  if (url.toLowerCase().endsWith('.webp')) return url
  // 若是本地預設圖片則不轉換
  if (url.startsWith('/images/')) return url

  const imageFolder = storageFolders.value.image
  const webpFolder = storageFolders.value.webp

  // 替換資料夾路徑：從 image 資料夾改為 webp 資料夾
  // 並將副檔名改為 .webp
  let webpUrl = url

  // 替換資料夾名稱（處理 URL 中的路徑）
  if (imageFolder && webpFolder) {
    // 匹配資料夾名稱（確保是完整的資料夾名稱，避免部分匹配）
    const folderPattern = new RegExp(`/${escapeRegExp(imageFolder)}/`, 'g')
    webpUrl = webpUrl.replace(folderPattern, `/${webpFolder}/`)
  }

  // 替換副檔名為 .webp
  webpUrl = webpUrl.replace(/\.(jpg|jpeg|png|gif|bmp)$/i, '.webp')

  return webpUrl
}

/**
 * 轉義正則表達式特殊字元
 */
function escapeRegExp(string) {
  return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

/**
 * 判斷 URL 是否可以轉換為 webp
 */
function canConvertToWebp(url) {
  if (!url) return false
  // 若已經是 webp 或是本地預設圖片則不需轉換
  if (url.toLowerCase().endsWith('.webp')) return false
  if (url.startsWith('/images/')) return false
  // 檢查是否為支援轉換的圖片格式
  return /\.(jpg|jpeg|png|gif|bmp)$/i.test(url)
}

// 計算當前應該載入的圖片 URL
const currentSrc = computed(() => {
  // 若完全載入失敗，顯示預設圖片
  if (error.value) {
    return props.defaultSrc
  }
  // 若應該嘗試 webp 且 URL 可轉換
  if (useWebp.value && canConvertToWebp(props.src)) {
    return toWebpUrl(props.src)
  }
  // 否則使用原始 URL
  return props.src
})

function onLoad() {
  loading.value = false
}

function onError() {
  // 若正在嘗試 webp 且 URL 可轉換，則 fallback 到原始格式
  if (useWebp.value && canConvertToWebp(props.src)) {
    useWebp.value = false
    // 保持 loading 狀態，等待原始格式載入
    return
  }
  // 若原始格式也失敗，則顯示預設圖片
  loading.value = false
  error.value = true
}

// 當 src 改變時重設狀態
watch(
  () => props.src,
  () => {
    loading.value = true
    error.value = false
    useWebp.value = true // 重新嘗試 webp
  }
)

// 根據 imgIndex 決定 loading 屬性
const imgLoading = computed(() => (props.imgIndex < 8 ? 'eager' : 'lazy'))
</script>

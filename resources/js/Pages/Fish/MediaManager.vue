<template>
  <Head :title="`${fish.name} - 捕獲紀錄與唸法`" />

  <FishGridLayout :hideTopOnMobile="true" :hideTop="true">
    <!-- 中欄：捕獲照片 -->
    <template #middle>
      <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
          <h2 class="text-xl font-bold flex items-center gap-2 text-gray-900">
            <span>📸</span> {{ fish.name }} 的捕獲紀錄
          </h2>
          <div class="flex items-center gap-2">
            <Link
              :href="`/fish/${fish.id}/capture-records/create`"
              class="flex items-center gap-1 text-sm bg-blue-100 text-blue-700 px-3 py-1.5 rounded-md font-medium hover:bg-blue-200 transition"
            >
              <span class="text-lg leading-none">+</span> 新增捕獲紀錄
            </Link>
            <Link
              v-if="canBatchCreateCaptureRecords"
              :href="`/fish/${fish.id}/capture-records/batch-create`"
              class="flex items-center gap-1 text-sm bg-green-100 text-green-700 px-3 py-1.5 rounded-md font-medium hover:bg-green-200 transition"
            >
              <span class="text-lg leading-none">⚡</span> 批次新增
            </Link>
          </div>
        </div>

        <div
          v-if="captureRecords.length"
          class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"
        >
          <div v-for="record in captureRecords" :key="record.id" class="flex flex-col gap-3 group">
            <!-- 16:9 圖片 -->
            <div
              class="relative aspect-video rounded-xl overflow-hidden bg-gray-100 border border-gray-200 shadow-sm group cursor-pointer"
              @click="selectedImage = record.image_url"
            >
              <LazyImage
                :src="record.image_url"
                :alt="`捕獲紀錄`"
                wrapperClass="w-full h-full"
                imgClass="w-full h-full object-cover"
              />

              <!-- 首圖標示與設定按鈕 -->
              <div class="absolute top-2 left-2 z-10">
                <span
                  v-if="record.id === fish.display_capture_record_id"
                  class="px-2 py-1 bg-teal-500 text-white text-xs font-bold rounded shadow-sm flex items-center gap-1"
                >
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M5 13l4 4L19 7"
                    ></path>
                  </svg>
                  圖鑑首圖
                </span>
                <button
                  v-else
                  @click.stop="setMainImage(record)"
                  class="px-2 py-1 bg-white/90 hover:bg-white text-gray-700 hover:text-blue-600 text-xs font-medium rounded shadow-sm backdrop-blur-sm lg:opacity-0 lg:group-hover:opacity-100 transition-opacity flex items-center gap-1"
                >
                  設為首圖
                </button>
              </div>

              <!-- 編輯按鈕 -->
              <a
                :href="`/fish/${fish.id}/capture-records/${record.id}/edit`"
                class="absolute top-2 right-2 bg-white/90 p-2 rounded-full shadow-sm text-gray-600 hover:text-blue-600 lg:opacity-0 lg:group-hover:opacity-100 transition-opacity z-10"
                @click.stop
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                  ></path>
                </svg>
              </a>
            </div>

            <!-- 圖片下方資訊 -->
            <div class="px-1">
              <div class="flex items-center flex-wrap gap-2 mb-1.5">
                <span class="font-medium text-gray-500 text-sm">捕獲地點：</span>
                <span
                  class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-bold bg-blue-50 text-blue-700"
                >
                  {{ record.tribe || '未標示' }}
                </span>
                <span v-if="record.location" class="text-gray-700 text-sm font-medium">
                  {{ record.location }}
                </span>
              </div>
              <div class="text-sm text-gray-600 space-y-1">
                <p class="flex items-center gap-2">
                  <span class="font-medium text-gray-500">捕獲方法：</span>
                  {{ record.capture_method || '未記錄' }}
                </p>
                <p class="flex items-center gap-2 text-xs text-gray-400">
                  <span class="font-medium">捕獲時間：</span>
                  {{ formatDate(record.capture_date) }}
                </p>
              </div>
            </div>
          </div>
        </div>
        <div
          v-else
          class="text-gray-500 text-center py-8 border border-dashed border-gray-300 rounded-lg"
        >
          尚未新增捕獲照片
        </div>
      </section>
    </template>

    <!-- 底部：發音錄音 -->
    <template #bottom>
      <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
          <h2 class="text-xl font-bold flex items-center gap-2 text-gray-900">
            <span>🔊</span> 發音列表
          </h2>
          <Link
            :href="`/fish/${fish.id}/audio/create`"
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
              <div>
                <div class="font-medium text-sm text-gray-900">{{ getAudioLabel(audio) }}</div>
                <div class="text-xs text-gray-500">
                  {{ new Date(audio.created_at).toLocaleDateString() }}
                </div>
              </div>
            </div>

            <div class="flex items-center gap-3 w-full sm:w-auto justify-between sm:justify-end">
              <audio :src="audio.url" controls class="h-8 w-32 md:w-48"></audio>

              <div class="flex items-center gap-1">
                <span
                  v-if="audio.name === fish.audio_filename"
                  class="text-xs font-bold text-green-600 bg-green-100 px-2 py-1 rounded whitespace-nowrap"
                >
                  主發音
                </span>
                <button
                  v-else
                  @click="setMainAudio(audio)"
                  class="text-xs bg-blue-50 text-blue-600 hover:bg-blue-100 px-2 py-1 rounded transition-colors whitespace-nowrap"
                  title="設為主要發音"
                >
                  設為主要發音
                </button>

                <button
                  v-if="audio.name !== fish.audio_filename"
                  @click="deleteAudio(audio)"
                  class="text-gray-400 hover:text-red-600 p-1.5 rounded-full hover:bg-red-50 transition-colors"
                  title="刪除"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                    ></path>
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>
        <div
          v-else
          class="text-gray-500 text-center py-8 border border-dashed border-gray-300 rounded-lg"
        >
          尚未新增發音錄音
        </div>
      </section>
    </template>
  </FishGridLayout>

  <!-- 圖片放大檢視 (Lightbox) -->
  <Teleport to="body">
    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="selectedImage"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-4"
        @click="selectedImage = null"
      >
        <button
          @click="selectedImage = null"
          class="absolute top-4 right-4 text-white hover:text-gray-300 p-2 z-[110]"
        >
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M6 18L18 6M6 6l12 12"
            ></path>
          </svg>
        </button>
        <img
          :src="selectedImage"
          class="max-h-[90vh] max-w-full object-contain rounded-lg shadow-2xl"
          @click.stop
        />
      </div>
    </transition>
  </Teleport>
</template>

<script setup>
import { Head, router, Link, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { markFishStale } from '@/utils/fishListCache'
import { hasEditorAccess } from '@/utils/userPermissions'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import FishGridLayout from '@/Layouts/FishGridLayout.vue'
import LazyImage from '@/Components/UI/LazyImage.vue'

// 設定巢狀佈局，並傳遞 props
defineOptions({
  layout: (h, page) =>
    h(
      FishAppLayout,
      {
        pageTitle: '捕獲與發音管理',
        activeTab: 'media',
        breadcrumbPage: '捕獲與發音',
        mobileBackUrl: `/fish/${page.props.fish?.id}`,
        mobileBackText: page.props.fish?.name || '返回',
        showBottomNav: false,
      },
      () => page
    ),
})

const props = defineProps({
  fish: Object,
  captureRecords: { type: Array, default: () => [] },
})

const page = usePage()
const user = computed(() => page.props.auth?.user)
const canBatchCreateCaptureRecords = computed(() => hasEditorAccess(user.value))
const fish = computed(() => props.fish)
const selectedImage = ref(null)

const formatDate = (dateString) => {
  if (!dateString) return '未記錄'
  // 只取年月日 (YYYY-MM-DD)
  const date = new Date(dateString)
  if (isNaN(date.getTime())) return dateString
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

const getAudioLabel = (audio) => {
  return `錄音 #${audio.id}`
}

const setMainAudio = (audio) => {
  if (confirm('確定要將此檔案設為主要發音嗎？')) {
    router.put(
      `/fish/${props.fish.id}/audio/${audio.id}/set-base`,
      {},
      {
        onSuccess: () => {
          // 標記魚類資料需要更新（清除快取）
          markFishStale(props.fish.id)
        },
      }
    )
  }
}

const deleteAudio = (audio) => {
  if (confirm('確定要刪除此發音檔案嗎？此動作無法復原。')) {
    router.delete(`/fish/${props.fish.id}/audio/${audio.id}`, {
      onSuccess: () => {
        // 標記魚類資料需要更新（清除快取）
        markFishStale(props.fish.id)
      },
    })
  }
}

const setMainImage = (record) => {
  if (confirm('確定要將這張捕獲紀錄設為圖鑑首圖嗎？')) {
    router.put(
      `/fish/${props.fish.id}/display-image`,
      {
        capture_record_id: record.id,
      },
      {
        onSuccess: () => {
          markFishStale(props.fish.id)
        },
      }
    )
  }
}
</script>

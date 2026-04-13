<template>
  <Head :title="`${selectedTribe} 的統計資料 | 管理後台`" />

  <FishAppLayout
    :page-title="`${selectedTribe} 的統計資料`"
    mobile-back-url="/fishs"
    mobile-back-text="among no tao"
  >
    <div class="dashboard-root">
      <!-- 頁面標題 -->
      <div class="dashboard-header">
        <div class="dashboard-header__icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
            />
          </svg>
        </div>
        <div>
          <h1 class="dashboard-header__title">{{ selectedTribe }} 的統計資料</h1>
          <p class="dashboard-header__subtitle">掌握 {{ selectedTribe }} 部落的資料統計</p>
        </div>
      </div>

      <!-- 部落切換器 -->
      <div class="tribe-switcher">
        <div class="tribe-switcher__inner">
          <button
            v-for="tribe in tribes"
            :id="`tribe-btn-${tribe}`"
            :key="tribe"
            class="tribe-btn"
            :class="{ 'tribe-btn--active': selectedTribe === tribe }"
            :disabled="isLoading && selectedTribe === tribe"
            @click="selectTribe(tribe)"
          >
            <span class="tribe-btn__dot"></span>
            {{ tribe }}
          </button>
        </div>
        <!-- 載入指示 -->
        <div v-if="isLoading" class="tribe-switcher__loading">
          <svg
            class="loading-spinner"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 100 16 8 8 0 01-8-8z"
            />
          </svg>
          載入中…
        </div>
      </div>

      <!-- 詳細 Breakdown 區域 -->
      <div class="detail-grid">
        <!-- 全部模式：捕獲紀錄 by 部落 -->
        <div class="detail-card" v-if="!selectedTribe && captureStats.by_tribe?.length">
          <div class="detail-card__header">
            <span class="detail-card__icon">📸</span>
            <h2 class="detail-card__title">捕獲紀錄分佈</h2>
            <span class="detail-card__badge">{{ captureStats.total }} 筆</span>
          </div>
          <div class="bar-list">
            <div
              v-for="item in captureStats.by_tribe.map((i) => ({ label: i.tribe, count: i.count }))"
              :key="item.label"
              class="bar-item"
            >
              <div class="bar-item__label" :title="item.label">{{ item.label }}</div>
              <div class="bar-item__track">
                <div
                  class="bar-item__fill bar-item__fill--capture"
                  :style="{ width: barWidth(item.count, captureStats.total) }"
                ></div>
              </div>
              <div class="bar-item__count" :class="{ 'bar-item__count--zero': item.count === 0 }">
                {{ item.count > 0 ? item.count + ' 筆' : '–' }}
              </div>
            </div>
          </div>
        </div>

        <!-- 部落模式：捕獲紀錄 by 地點 -->
        <div class="detail-card" v-if="selectedTribe && captureStats.by_location?.length">
          <div class="detail-card__header">
            <span class="detail-card__icon">📸</span>
            <h2 class="detail-card__title">捕獲地點分佈</h2>
            <span class="detail-card__badge">{{ captureStats.total }} 筆</span>
          </div>
          <div class="bar-list">
            <div v-for="item in captureStats.by_location" :key="item.label" class="bar-item">
              <div class="bar-item__label" :title="item.label">{{ item.label }}</div>
              <div class="bar-item__track">
                <div
                  class="bar-item__fill bar-item__fill--capture"
                  :style="{ width: barWidth(item.count, captureStats.total) }"
                ></div>
              </div>
              <div class="bar-item__count" :class="{ 'bar-item__count--zero': item.count === 0 }">
                {{ item.count > 0 ? item.count + ' 筆' : '–' }}
              </div>
            </div>
          </div>
        </div>

        <!-- 全部模式：部落分類 by 部落 -->
        <div class="detail-card" v-if="!selectedTribe && tribalStats.by_tribe?.length">
          <div class="detail-card__header">
            <span class="detail-card__icon">🏘️</span>
            <h2 class="detail-card__title">部落分類分佈</h2>
            <span class="detail-card__badge">{{ tribalStats.total }} 筆</span>
          </div>
          <div class="bar-list">
            <div
              v-for="item in tribalStats.by_tribe.map((i) => ({ label: i.tribe, count: i.count }))"
              :key="item.label"
              class="bar-item"
            >
              <div class="bar-item__label" :title="item.label">{{ item.label }}</div>
              <div class="bar-item__track">
                <div
                  class="bar-item__fill bar-item__fill--tribal"
                  :style="{ width: barWidth(item.count, tribalStats.total) }"
                ></div>
              </div>
              <div class="bar-item__count" :class="{ 'bar-item__count--zero': item.count === 0 }">
                {{ item.count > 0 ? item.count + ' 筆' : '–' }}
              </div>
            </div>
          </div>
        </div>

        <!-- 部落模式：部落分類 by 食物分類 -->
        <div class="detail-card" v-if="selectedTribe">
          <div class="detail-card__header">
            <span class="detail-card__icon">🏘️</span>
            <h2 class="detail-card__title">食用分類分佈</h2>
            <span class="detail-card__badge">{{ tribalStats.total }} 種</span>
          </div>
          <div class="bar-list">
            <div v-for="item in tribalStats.by_food_category" :key="item.label" class="bar-item">
              <div class="bar-item__label" :title="item.label">{{ item.label }}</div>
              <div class="bar-item__track">
                <div
                  class="bar-item__fill bar-item__fill--tribal"
                  :style="{ width: barWidth(item.count, tribalStats.total) }"
                ></div>
              </div>
              <div class="bar-item__count" :class="{ 'bar-item__count--zero': item.count === 0 }">
                {{ item.count > 0 ? item.count + ' 筆' : '–' }}
              </div>
            </div>
          </div>
        </div>

        <!-- 部落模式：部落分類 by 處理方法 -->
        <div class="detail-card" v-if="selectedTribe">
          <div class="detail-card__header">
            <span class="detail-card__icon">🔪</span>
            <h2 class="detail-card__title">處理方式分佈</h2>
            <span class="detail-card__badge">{{ tribalStats.total }} 種</span>
          </div>
          <div class="bar-list">
            <div
              v-for="item in tribalStats.by_processing_method"
              :key="item.label"
              class="bar-item"
            >
              <div class="bar-item__label" :title="item.label">{{ item.label }}</div>
              <div class="bar-item__track">
                <div
                  class="bar-item__fill bar-item__fill--processing"
                  :style="{ width: barWidth(item.count, tribalStats.total) }"
                ></div>
              </div>
              <div class="bar-item__count" :class="{ 'bar-item__count--zero': item.count === 0 }">
                {{ item.count > 0 ? item.count + ' 筆' : '–' }}
              </div>
            </div>
          </div>
        </div>

        <!-- 音檔 by 地區（全部模式） -->
        <div class="detail-card" v-if="!selectedTribe && audioStats.by_locate?.length">
          <div class="detail-card__header">
            <span class="detail-card__icon">🔊</span>
            <h2 class="detail-card__title">音檔地區分佈</h2>
            <span class="detail-card__badge">{{ audioStats.total }} 筆</span>
          </div>
          <div class="bar-list">
            <div
              v-for="item in audioStats.by_locate.map((i) => ({ label: i.locate, count: i.count }))"
              :key="item.label"
              class="bar-item"
            >
              <div class="bar-item__label" :title="item.label">{{ item.label }}</div>
              <div class="bar-item__track">
                <div
                  class="bar-item__fill bar-item__fill--audio"
                  :style="{ width: barWidth(item.count, audioStats.total) }"
                ></div>
              </div>
              <div class="bar-item__count" :class="{ 'bar-item__count--zero': item.count === 0 }">
                {{ item.count > 0 ? item.count + ' 筆' : '–' }}
              </div>
            </div>
          </div>
        </div>

        <!-- 地方知識 by 類型 -->
        <div class="detail-card" v-if="noteStats.by_type?.length">
          <div class="detail-card__header">
            <span class="detail-card__icon">📝</span>
            <h2 class="detail-card__title">地方知識類型</h2>
            <span class="detail-card__badge">{{ noteStats.total }} 筆</span>
          </div>
          <div class="bar-list">
            <div
              v-for="item in noteStats.by_type.map((i) => ({ label: i.type, count: i.count }))"
              :key="item.label"
              class="bar-item"
            >
              <div class="bar-item__label" :title="item.label">{{ item.label }}</div>
              <div class="bar-item__track">
                <div
                  class="bar-item__fill bar-item__fill--note"
                  :style="{ width: barWidth(item.count, noteStats.total) }"
                ></div>
              </div>
              <div class="bar-item__count" :class="{ 'bar-item__count--zero': item.count === 0 }">
                {{ item.count > 0 ? item.count + ' 筆' : '–' }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </FishAppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'

// ---- Props ----
const props = defineProps({
  tribes: { type: Array, required: true },
  selectedTribe: { type: String, default: null },
  fishStats: { type: Object, required: true },
  captureStats: { type: Object, required: true },
  tribalStats: { type: Object, required: true },
  audioStats: { type: Object, required: true },
  noteStats: { type: Object, required: true },
})

// ---- 部落切換 ----
const isLoading = ref(false)

function selectTribe(tribe) {
  if (isLoading.value) return
  isLoading.value = true

  const params = tribe ? { tribe } : {}
  router.get('/dashboard', params, {
    preserveState: false,
    preserveScroll: false,
    onFinish: () => {
      isLoading.value = false
    },
  })
}

// ---- 工具函式 ----

function barWidth(count, total) {
  if (!total) return '0%'
  return Math.round((count / total) * 100) + '%'
}
</script>

<style scoped>
/* =========================================
   Root
   ========================================= */
.dashboard-root {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

/* =========================================
   Header
   ========================================= */
.dashboard-header {
  display: flex;
  align-items: center;
  gap: 1rem;
}
.dashboard-header__icon {
  width: 3rem;
  height: 3rem;
  border-radius: 0.875rem;
  background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
  flex-shrink: 0;
}
.dashboard-header__icon svg {
  width: 1.5rem;
  height: 1.5rem;
  color: #fff;
}
.dashboard-header__title {
  font-size: 1.375rem;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.dashboard-header__subtitle {
  font-size: 0.875rem;
  color: #6b7280;
  margin: 0.125rem 0 0;
}

/* =========================================
   Tribe Switcher
   ========================================= */
.tribe-switcher {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}
.tribe-switcher__inner {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  background: #f3f4f6;
  padding: 0.375rem;
  border-radius: 0.875rem;
}
.tribe-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.375rem 0.875rem;
  border-radius: 0.625rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #6b7280;
  background: transparent;
  border: none;
  cursor: pointer;
  transition: all 0.18s ease;
  white-space: nowrap;
}
.tribe-btn:hover:not(:disabled) {
  background: #fff;
  color: #111827;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}
.tribe-btn--active {
  background: #fff;
  color: #1d4ed8;
  font-weight: 600;
  box-shadow: 0 2px 8px rgba(59, 130, 246, 0.15);
}
.tribe-btn:disabled {
  opacity: 0.6;
  cursor: default;
}
.tribe-btn__dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
  background: #d1d5db;
  display: inline-block;
}
.tribe-btn--active .tribe-btn__dot {
  background: #3b82f6;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
}
.tribe-btn__dot--all {
  background: linear-gradient(135deg, #3b82f6, #8b5cf6, #10b981);
}

.tribe-switcher__loading {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.8125rem;
  color: #6b7280;
}
.loading-spinner {
  width: 1rem;
  height: 1rem;
  animation: spin 0.8s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* =========================================
   Filter Banner
   ========================================= */
.filter-banner {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: linear-gradient(135deg, #eff6ff, #eef2ff);
  border: 1px solid #bfdbfe;
  border-radius: 0.75rem;
  padding: 0.625rem 1rem;
  font-size: 0.875rem;
  color: #1e40af;
}
.filter-banner__icon {
  width: 1rem;
  height: 1rem;
  flex-shrink: 0;
}
.filter-banner strong {
  font-weight: 700;
}
.filter-banner__clear {
  margin-left: auto;
  background: none;
  border: 1px solid #93c5fd;
  border-radius: 0.375rem;
  padding: 0.2rem 0.625rem;
  font-size: 0.75rem;
  color: #1d4ed8;
  cursor: pointer;
  transition: all 0.15s ease;
  white-space: nowrap;
}
.filter-banner__clear:hover {
  background: #dbeafe;
}

/* =========================================
   Detail Grid
   ========================================= */
.detail-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 1rem;
}

.detail-card {
  background: #fff;
  border-radius: 1rem;
  padding: 1.25rem;
  box-shadow:
    0 1px 3px rgba(0, 0, 0, 0.07),
    0 4px 12px rgba(0, 0, 0, 0.04);
  border: 1px solid #f3f4f6;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.detail-card__header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.detail-card__icon {
  font-size: 1.25rem;
  line-height: 1;
}
.detail-card__title {
  font-size: 0.9375rem;
  font-weight: 600;
  color: #111827;
  flex: 1;
  margin: 0;
}
.detail-card__badge {
  background: #f3f4f6;
  color: #6b7280;
  font-size: 0.75rem;
  font-weight: 500;
  padding: 0.2rem 0.6rem;
  border-radius: 999px;
  white-space: nowrap;
}

/* =========================================
   Bar List
   ========================================= */
.bar-list {
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
}
.bar-item {
  display: grid;
  grid-template-columns: 7rem 1fr 2.5rem;
  align-items: center;
  gap: 0.625rem;
}
.bar-item__label {
  font-size: 0.8125rem;
  color: #374151;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.bar-item__track {
  background: #f3f4f6;
  border-radius: 999px;
  height: 0.5rem;
  overflow: hidden;
}
.bar-item__fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}
.bar-item__fill--capture {
  background: linear-gradient(90deg, #f59e0b, #fbbf24);
}
.bar-item__fill--tribal {
  background: linear-gradient(90deg, #10b981, #34d399);
}
.bar-item__fill--audio {
  background: linear-gradient(90deg, #8b5cf6, #a78bfa);
}
.bar-item__fill--note {
  background: linear-gradient(90deg, #ec4899, #f472b6);
}
.bar-item__fill--user {
  background: linear-gradient(90deg, #06b6d4, #22d3ee);
}
.bar-item__fill--processing {
  background: linear-gradient(90deg, #f97316, #fb923c);
}
.bar-item__count {
  font-size: 0.8125rem;
  font-weight: 600;
  color: #374151;
  text-align: right;
}
.bar-item__count--zero {
  color: #9ca3af;
  font-weight: 400;
}
</style>

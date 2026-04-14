<template>
  <Head :title="`${selectedTribe} 的統計資料 | 管理後台`" />

  <FishAppLayout
    :page-title="`${selectedTribe} 的統計資料`"
    mobile-back-url="/fishs"
    mobile-back-text="among no tao"
    breadcrumb-page="統計面板"
  >
    <div class="dashboard-root">
      <!-- 頁面標題 + 部落切換器（同排，空間不足時換行） -->
      <div class="dashboard-top">
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
      </div>

      <!-- 部落模式：資料完整度概況 -->
      <div class="tribe-overview" v-if="dataCompleteness">
        <div class="tribe-overview__title">
          <span>📊</span>
          <span>資料完整度概況</span>
          <span class="tribe-overview__total">系統總魚類共 {{ dataCompleteness.total }} 筆</span>
        </div>
        <div class="tribe-overview__metrics">
          <!-- 食用分類 -->
          <div class="completeness-item">
            <div class="completeness-item__header">
              <span class="completeness-item__label">食用分類</span>
              <span
                class="completeness-dot"
                :class="`completeness-dot--${dataCompleteness.food.level}`"
              ></span>
              <span
                class="completeness-item__pct"
                :class="`completeness-item__pct--${dataCompleteness.food.level}`"
              >
                {{ dataCompleteness.food.pct }}%
              </span>
            </div>
            <div class="completeness-item__track">
              <div
                class="completeness-item__fill"
                :class="`completeness-item__fill--${dataCompleteness.food.level}`"
                :style="{ width: dataCompleteness.food.pct + '%' }"
              ></div>
            </div>
            <div class="completeness-item__detail">
              <span>已確認 {{ dataCompleteness.food.recorded }} 筆</span>
              <a
                v-if="dataCompleteness.food.q > 0"
                :href="`/fishs?tribe=${selectedTribe}&food_category=?`"
                class="completeness-item__link"
                >待確認(?) {{ dataCompleteness.food.q }} 筆</a
              >
              <a
                v-if="dataCompleteness.food.unrecorded > 0"
                :href="`/fishs?tribe=${selectedTribe}&food_category=尚未紀錄`"
                class="completeness-item__link completeness-item__link--missing"
                >尚未紀錄 {{ dataCompleteness.food.unrecorded }} 筆</a
              >
            </div>
          </div>
          <!-- 處理方式 -->
          <div class="completeness-item">
            <div class="completeness-item__header">
              <span class="completeness-item__label">處理方式</span>
              <span
                class="completeness-dot"
                :class="`completeness-dot--${dataCompleteness.processing.level}`"
              ></span>
              <span
                class="completeness-item__pct"
                :class="`completeness-item__pct--${dataCompleteness.processing.level}`"
              >
                {{ dataCompleteness.processing.pct }}%
              </span>
            </div>
            <div class="completeness-item__track">
              <div
                class="completeness-item__fill"
                :class="`completeness-item__fill--${dataCompleteness.processing.level}`"
                :style="{ width: dataCompleteness.processing.pct + '%' }"
              ></div>
            </div>
            <div class="completeness-item__detail">
              <span>已確認 {{ dataCompleteness.processing.recorded }} 筆</span>
              <a
                v-if="dataCompleteness.processing.q > 0"
                :href="`/fishs?tribe=${selectedTribe}&processing_method=?`"
                class="completeness-item__link"
                >待確認(?) {{ dataCompleteness.processing.q }} 筆</a
              >
              <a
                v-if="dataCompleteness.processing.unrecorded > 0"
                :href="`/fishs?tribe=${selectedTribe}&processing_method=尚未紀錄`"
                class="completeness-item__link completeness-item__link--missing"
                >尚未紀錄 {{ dataCompleteness.processing.unrecorded }} 筆</a
              >
            </div>
          </div>
          <!-- 魚類發音（音檔） -->
          <div class="completeness-item">
            <div class="completeness-item__header">
              <span class="completeness-item__label">魚類發音</span>
              <span
                class="completeness-dot"
                :class="`completeness-dot--${dataCompleteness.audio.level}`"
              ></span>
              <span
                class="completeness-item__pct"
                :class="`completeness-item__pct--${dataCompleteness.audio.level}`"
              >
                {{ dataCompleteness.audio.pct }}%
              </span>
            </div>
            <div class="completeness-item__track">
              <div
                class="completeness-item__fill"
                :class="`completeness-item__fill--${dataCompleteness.audio.level}`"
                :style="{ width: dataCompleteness.audio.pct + '%' }"
              ></div>
            </div>
            <div class="completeness-item__detail">
              <span>有音檔 {{ dataCompleteness.audio.with }} 筆</span>
              <a
                v-if="dataCompleteness.audio.without > 0"
                href="/fishs?without_audio=1"
                class="completeness-item__link completeness-item__link--missing"
                >尚無音檔 {{ dataCompleteness.audio.without }} 筆</a
              >
            </div>
          </div>
        </div>
      </div>
      <div class="detail-grid">
        <!-- 部落分類 by 食物分類 -->
        <div class="detail-card">
          <div class="detail-card__header">
            <span class="detail-card__icon">🏘️</span>
            <h2 class="detail-card__title">食用分類分佈</h2>
            <span class="detail-card__badge">{{ fishStats.total }} 筆</span>
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
            <template v-if="dataCompleteness?.food.unrecorded > 0">
              <div class="bar-separator"></div>
              <a
                class="bar-item bar-item--missing bar-item--link"
                :href="`/fishs?tribe=${selectedTribe}&food_category=尚未紀錄`"
              >
                <div class="bar-item__label">尚未紀錄</div>
                <div class="bar-item__track">
                  <div
                    class="bar-item__fill bar-item__fill--missing"
                    :style="{
                      width: barWidth(dataCompleteness.food.unrecorded, dataCompleteness.total),
                    }"
                  ></div>
                </div>
                <div class="bar-item__count bar-item__count--missing">
                  {{ dataCompleteness.food.unrecorded }} 筆
                </div>
              </a>
            </template>
          </div>
        </div>

        <!-- 處理方式分佈 -->
        <div class="detail-card">
          <div class="detail-card__header">
            <span class="detail-card__icon">🔪</span>
            <h2 class="detail-card__title">處理方式分佈</h2>
            <span class="detail-card__badge">{{ fishStats.total }} 筆</span>
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
            <template v-if="dataCompleteness?.processing.unrecorded > 0">
              <div class="bar-separator"></div>
              <a
                class="bar-item bar-item--missing bar-item--link"
                :href="`/fishs?tribe=${selectedTribe}&processing_method=尚未紀錄`"
              >
                <div class="bar-item__label">尚未紀錄</div>
                <div class="bar-item__track">
                  <div
                    class="bar-item__fill bar-item__fill--missing"
                    :style="{
                      width: barWidth(
                        dataCompleteness.processing.unrecorded,
                        dataCompleteness.total
                      ),
                    }"
                  ></div>
                </div>
                <div class="bar-item__count bar-item__count--missing">
                  {{ dataCompleteness.processing.unrecorded }} 筆
                </div>
              </a>
            </template>
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
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'

// ---- Props ----
const props = defineProps({
  tribes: { type: Array, required: true },
  selectedTribe: { type: String, default: null },
  fishStats: { type: Object, required: true },
  tribalStats: { type: Object, required: true },
  audioStats: { type: Object, required: true },
  noteStats: { type: Object, required: true },
})

// ---- 部落切換 ----
const isLoading = ref(false)

function selectTribe(tribe) {
  if (isLoading.value) return
  isLoading.value = true

  router.get(
    '/dashboard',
    { tribe },
    {
      preserveState: false,
      preserveScroll: false,
      onFinish: () => {
        isLoading.value = false
      },
    }
  )
}

// ---- 資料完整度計算 ----

const dataCompleteness = computed(() => {
  const total = props.fishStats.total
  if (!total) return null

  const unrecorded = total - props.tribalStats.total

  const foodQ = (props.tribalStats.by_food_category ?? []).find((i) => i.label === '?')?.count ?? 0
  const foodMissing = unrecorded + foodQ
  const foodRate = (total - foodMissing) / total

  const procQ =
    (props.tribalStats.by_processing_method ?? []).find((i) => i.label === '?')?.count ?? 0
  const procMissing = unrecorded + procQ
  const procRate = (total - procMissing) / total

  const withoutAudio = props.fishStats.without_audio ?? 0
  const audioRate = (total - withoutAudio) / total

  function level(rate) {
    if (rate >= 0.8) return 'green'
    if (rate >= 0.6) return 'yellow'
    return 'red'
  }

  return {
    total,
    food: {
      recorded: total - foodMissing,
      q: foodQ,
      unrecorded,
      missing: foodMissing,
      pct: Math.round(foodRate * 100),
      level: level(foodRate),
    },
    processing: {
      recorded: total - procMissing,
      q: procQ,
      unrecorded,
      missing: procMissing,
      pct: Math.round(procRate * 100),
      level: level(procRate),
    },
    audio: {
      with: total - withoutAudio,
      without: withoutAudio,
      pct: Math.round(audioRate * 100),
      level: level(audioRate),
    },
  }
})

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
   Top Row（標題 + 部落切換器）
   ========================================= */
.dashboard-top {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}

/* =========================================
   Header
   ========================================= */
.dashboard-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-shrink: 0;
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
  margin-left: auto;
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
   Tribe Overview / Completeness
   ========================================= */
.tribe-overview {
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
.tribe-overview__title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9375rem;
  font-weight: 600;
  color: #111827;
}
.tribe-overview__total {
  margin-left: auto;
  font-size: 0.8125rem;
  font-weight: 400;
  color: #6b7280;
}
.tribe-overview__metrics {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
}
@media (max-width: 480px) {
  .tribe-overview__metrics {
    grid-template-columns: 1fr;
  }
}
.completeness-item {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}
.completeness-item__header {
  display: flex;
  align-items: center;
  gap: 0.375rem;
}
.completeness-item__label {
  font-size: 0.875rem;
  font-weight: 500;
  color: #374151;
}
.completeness-dot {
  width: 0.625rem;
  height: 0.625rem;
  border-radius: 50%;
  flex-shrink: 0;
}
.completeness-dot--green {
  background: #10b981;
}
.completeness-dot--yellow {
  background: #f59e0b;
}
.completeness-dot--red {
  background: #ef4444;
}
.completeness-item__pct {
  margin-left: auto;
  font-size: 0.875rem;
  font-weight: 700;
}
.completeness-item__pct--green {
  color: #059669;
}
.completeness-item__pct--yellow {
  color: #d97706;
}
.completeness-item__pct--red {
  color: #dc2626;
}
.completeness-item__track {
  background: #f3f4f6;
  border-radius: 999px;
  height: 0.5rem;
  overflow: hidden;
}
.completeness-item__fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}
.completeness-item__fill--green {
  background: linear-gradient(90deg, #059669, #10b981);
}
.completeness-item__fill--yellow {
  background: linear-gradient(90deg, #d97706, #f59e0b);
}
.completeness-item__fill--red {
  background: linear-gradient(90deg, #dc2626, #ef4444);
}
.completeness-item__detail {
  font-size: 0.75rem;
  color: #9ca3af;
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.25rem;
}
.completeness-item__link {
  color: #d97706;
  font-weight: 500;
  text-decoration: underline;
  cursor: pointer;
}
.completeness-item__link--missing {
  color: #f97316;
}
.completeness-item__link:hover,
.completeness-item__link--missing:hover {
  opacity: 0.75;
}
.completeness-item__missing {
  color: #f97316;
  font-weight: 500;
}
.completeness-item__q {
  color: #d97706;
  font-weight: 500;
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
.bar-item__fill--missing {
  background: repeating-linear-gradient(45deg, #d1d5db, #d1d5db 4px, #e5e7eb 4px, #e5e7eb 8px);
}
.bar-separator {
  height: 1px;
  background: #f3f4f6;
  margin: 0.25rem 0;
}
.bar-item--missing .bar-item__label {
  color: #9ca3af;
  font-style: italic;
}
.bar-item__count--missing {
  color: #f97316;
  font-weight: 600;
}
.bar-item--link {
  display: flex;
  text-decoration: none;
  cursor: pointer;
}
.bar-item--link:hover .bar-item__label {
  text-decoration: underline;
}
.bar-item--link:hover .bar-item__count--missing {
  opacity: 0.75;
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

<template>
  <Head title="量化報告 | 管理後台" />

  <FishAppLayout page-title="量化報告" mobile-back-url="/dashboard" mobile-back-text="統計面板">
    <div class="report-root">
      <!-- 頁面標題 -->
      <div class="report-header">
        <div class="report-header__icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M9 17v-2m3 2v-4m3 4v-6M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"
            />
          </svg>
        </div>
        <div>
          <h1 class="report-header__title">魚類資料量化報告</h1>
          <p class="report-header__subtitle">依部落彙整食用分類、處理方式及捕獲方式的數量統計</p>
        </div>
      </div>

      <!-- 總覽卡片 -->
      <div class="overview-card">
        <div class="overview-card__item">
          <span class="overview-card__num">{{ statistics.total_fish }}</span>
          <span class="overview-card__label">魚種總數</span>
        </div>
        <div class="overview-card__item">
          <span class="overview-card__num">{{ tribalTotalCount }}</span>
          <span class="overview-card__label">部落分類筆數</span>
        </div>
        <div class="overview-card__item">
          <span class="overview-card__num">{{ captureTotalCount }}</span>
          <span class="overview-card__label">捕獲紀錄筆數</span>
        </div>
        <div class="overview-card__item">
          <span class="overview-card__num">{{ tribes.length }}</span>
          <span class="overview-card__label">部落數量</span>
        </div>
      </div>

      <!-- 食用分類總覽橫條圖 -->
      <section class="matrix-section summary-section" v-if="allFoodCategories.length > 0">
        <div class="matrix-section__header">
          <span class="matrix-section__icon">🍽️</span>
          <h2 class="matrix-section__title">食用分類總覽（跨部落合計）</h2>
          <span class="matrix-section__hint">單位：筆</span>
        </div>
        <div class="summary-bars">
          <div v-for="category in allFoodCategories" :key="category" class="summary-bar-item">
            <div class="summary-bar-item__label">{{ category || '未分類' }}</div>
            <div class="summary-bar-item__track">
              <div
                class="summary-bar-item__fill summary-bar-item__fill--food"
                :style="{ width: barWidth(getCategoryRowTotal(category), tribalTotalCount) }"
              ></div>
            </div>
            <div class="summary-bar-item__count">{{ getCategoryRowTotal(category) }}</div>
          </div>
        </div>
      </section>

      <!-- 處理方式總覽橫條圖 -->
      <section class="matrix-section summary-section" v-if="allProcessingMethods.length > 0">
        <div class="matrix-section__header">
          <span class="matrix-section__icon">🔪</span>
          <h2 class="matrix-section__title">處理方式總覽（跨部落合計）</h2>
          <span class="matrix-section__hint">單位：筆</span>
        </div>
        <div class="summary-bars">
          <div v-for="method in allProcessingMethods" :key="method" class="summary-bar-item">
            <div class="summary-bar-item__label">{{ method || '未記錄' }}</div>
            <div class="summary-bar-item__track">
              <div
                class="summary-bar-item__fill summary-bar-item__fill--processing"
                :style="{ width: barWidth(getProcessingRowTotal(method), processingTotalCount) }"
              ></div>
            </div>
            <div class="summary-bar-item__count">{{ getProcessingRowTotal(method) }}</div>
          </div>
        </div>
      </section>

      <!-- 食用分類矩陣表（各部落細節）-->
      <section class="matrix-section">
        <div class="matrix-section__header">
          <span class="matrix-section__icon">🍽️</span>
          <h2 class="matrix-section__title">各部落食用分類統計</h2>
          <span class="matrix-section__hint">單位：魚種數（筆）</span>
        </div>
        <div class="matrix-wrapper">
          <table class="matrix-table">
            <thead>
              <tr>
                <th class="matrix-table__corner">食用分類 ╲ 部落</th>
                <th v-for="tribe in tribes" :key="tribe" class="matrix-table__tribe-head">
                  {{ tribe }}
                </th>
                <th class="matrix-table__total-head">小計</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="category in allFoodCategories" :key="category">
                <td class="matrix-table__row-label">{{ category || '未分類' }}</td>
                <td
                  v-for="tribe in tribes"
                  :key="tribe"
                  class="matrix-table__cell"
                  :class="{
                    'matrix-table__cell--has-value': getCategoryCount(tribe, category) > 0,
                  }"
                >
                  {{ getCategoryCount(tribe, category) || '–' }}
                </td>
                <td class="matrix-table__row-total">
                  {{ getCategoryRowTotal(category) }}
                </td>
              </tr>
              <!-- 無分類紀錄提示 -->
              <tr v-if="allFoodCategories.length === 0">
                <td :colspan="tribes.length + 2" class="matrix-table__empty">尚無食用分類資料</td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td class="matrix-table__footer-label">部落小計</td>
                <td v-for="tribe in tribes" :key="tribe" class="matrix-table__footer-total">
                  {{ getTribeTribalTotal(tribe) }}
                </td>
                <td class="matrix-table__footer-grand">{{ tribalTotalCount }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>

      <!-- 處理方式矩陣表 -->
      <section class="matrix-section">
        <div class="matrix-section__header">
          <span class="matrix-section__icon">🔪</span>
          <h2 class="matrix-section__title">各部落處理方式統計</h2>
          <span class="matrix-section__hint">單位：魚種數（筆）</span>
        </div>
        <div class="matrix-wrapper">
          <table class="matrix-table">
            <thead>
              <tr>
                <th class="matrix-table__corner">處理方式 ╲ 部落</th>
                <th v-for="tribe in tribes" :key="tribe" class="matrix-table__tribe-head">
                  {{ tribe }}
                </th>
                <th class="matrix-table__total-head">小計</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="method in allProcessingMethods" :key="method">
                <td class="matrix-table__row-label">{{ method || '未記錄' }}</td>
                <td
                  v-for="tribe in tribes"
                  :key="tribe"
                  class="matrix-table__cell"
                  :class="{
                    'matrix-table__cell--has-value': getProcessingCount(tribe, method) > 0,
                  }"
                >
                  {{ getProcessingCount(tribe, method) || '–' }}
                </td>
                <td class="matrix-table__row-total">
                  {{ getProcessingRowTotal(method) }}
                </td>
              </tr>
              <tr v-if="allProcessingMethods.length === 0">
                <td :colspan="tribes.length + 2" class="matrix-table__empty">尚無處理方式資料</td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td class="matrix-table__footer-label">部落小計</td>
                <td v-for="tribe in tribes" :key="tribe" class="matrix-table__footer-total">
                  {{ getTribeProcessingTotal(tribe) }}
                </td>
                <td class="matrix-table__footer-grand">{{ processingTotalCount }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>

      <!-- 捕獲方式矩陣表 -->
      <section class="matrix-section">
        <div class="matrix-section__header">
          <span class="matrix-section__icon">🎣</span>
          <h2 class="matrix-section__title">各部落捕獲方式統計</h2>
          <span class="matrix-section__hint">單位：捕獲紀錄（筆）</span>
        </div>
        <div class="matrix-wrapper">
          <table class="matrix-table">
            <thead>
              <tr>
                <th class="matrix-table__corner">捕獲方式 ╲ 部落</th>
                <th v-for="tribe in tribes" :key="tribe" class="matrix-table__tribe-head">
                  {{ tribe }}
                </th>
                <th class="matrix-table__total-head">小計</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="method in allCaptureMethods" :key="method">
                <td class="matrix-table__row-label">{{ method || '未記錄' }}</td>
                <td
                  v-for="tribe in tribes"
                  :key="tribe"
                  class="matrix-table__cell"
                  :class="{ 'matrix-table__cell--has-value': getCaptureCount(tribe, method) > 0 }"
                >
                  {{ getCaptureCount(tribe, method) || '–' }}
                </td>
                <td class="matrix-table__row-total">
                  {{ getCaptureRowTotal(method) }}
                </td>
              </tr>
              <tr v-if="allCaptureMethods.length === 0">
                <td :colspan="tribes.length + 2" class="matrix-table__empty">尚無捕獲方式資料</td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td class="matrix-table__footer-label">部落小計</td>
                <td v-for="tribe in tribes" :key="tribe" class="matrix-table__footer-total">
                  {{ getTribeCaptureTotal(tribe) }}
                </td>
                <td class="matrix-table__footer-grand">{{ captureTotalCount }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>
    </div>
  </FishAppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'

// ---- Props ----
const props = defineProps({
  tribes: { type: Array, required: true },
  foodCategories: { type: Array, required: true },
  processingMethods: { type: Array, required: true },
  statistics: { type: Object, required: true },
})

// ---- 衍生資料：從實際統計中收集所有出現過的分類（含未在 config 中的例外值）----

const allFoodCategories = computed(() => {
  const fromStats = Object.values(props.statistics.food_categories_by_tribe).flatMap(Object.keys)
  const merged = [...new Set([...props.foodCategories, ...fromStats])]
  return merged.filter((c) => c !== '') // 空字串顯示於「未分類」邏輯已處理
})

const allProcessingMethods = computed(() => {
  const fromStats = Object.keys(props.statistics.processing_methods)
  const merged = [...new Set([...props.processingMethods, ...fromStats])]
  return merged.filter((m) => m !== '')
})

const allCaptureMethods = computed(() => {
  return [...new Set(Object.values(props.statistics.capture_methods_by_tribe).flatMap(Object.keys))]
})

// ---- 總計 ----

const tribalTotalCount = computed(() =>
  Object.values(props.statistics.food_categories_by_tribe)
    .flatMap(Object.values)
    .reduce((sum, n) => sum + n, 0)
)

const captureTotalCount = computed(() =>
  Object.values(props.statistics.capture_methods_by_tribe)
    .flatMap(Object.values)
    .reduce((sum, n) => sum + n, 0)
)

const processingTotalCount = computed(() =>
  Object.values(props.statistics.processing_methods_by_tribe)
    .flatMap(Object.values)
    .reduce((sum, n) => sum + n, 0)
)

// ---- 食用分類矩陣查詢函式 ----

function getCategoryCount(tribe, category) {
  return props.statistics.food_categories_by_tribe?.[tribe]?.[category] ?? 0
}

function getCategoryRowTotal(category) {
  return props.tribes.reduce((sum, tribe) => sum + getCategoryCount(tribe, category), 0)
}

function getTribeTribalTotal(tribe) {
  return Object.values(props.statistics.food_categories_by_tribe?.[tribe] ?? {}).reduce(
    (sum, n) => sum + n,
    0
  )
}

// ---- 處理方式矩陣查詢函式 ----

function getProcessingCount(tribe, method) {
  return props.statistics.processing_methods_by_tribe?.[tribe]?.[method] ?? 0
}

function getProcessingRowTotal(method) {
  return props.tribes.reduce((sum, tribe) => sum + getProcessingCount(tribe, method), 0)
}

function getTribeProcessingTotal(tribe) {
  return Object.values(props.statistics.processing_methods_by_tribe?.[tribe] ?? {}).reduce(
    (sum, n) => sum + n,
    0
  )
}

// ---- 捕獲方式矩陣查詢函式 ----

function getCaptureCount(tribe, method) {
  return props.statistics.capture_methods_by_tribe?.[tribe]?.[method] ?? 0
}

function getCaptureRowTotal(method) {
  return props.tribes.reduce((sum, tribe) => sum + getCaptureCount(tribe, method), 0)
}

function getTribeCaptureTotal(tribe) {
  return Object.values(props.statistics.capture_methods_by_tribe?.[tribe] ?? {}).reduce(
    (sum, n) => sum + n,
    0
  )
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
.report-root {
  display: flex;
  flex-direction: column;
  gap: 2rem;
  padding-bottom: 2rem;
}

/* =========================================
   Header
   ========================================= */
.report-header {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.report-header__icon {
  width: 3rem;
  height: 3rem;
  border-radius: 0.75rem;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.report-header__icon svg {
  width: 1.5rem;
  height: 1.5rem;
  color: white;
}

.report-header__title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--color-text-primary, #111827);
  margin: 0;
}

.report-header__subtitle {
  font-size: 0.875rem;
  color: var(--color-text-secondary, #6b7280);
  margin: 0.25rem 0 0;
}

/* =========================================
   Overview Cards
   ========================================= */
.overview-card {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 1rem;
  padding: 1.25rem;
}

@media (min-width: 640px) {
  .overview-card {
    grid-template-columns: repeat(4, 1fr);
  }
}

.overview-card__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
}

.overview-card__num {
  font-size: 2rem;
  font-weight: 700;
  color: #10b981;
  line-height: 1;
}

.overview-card__label {
  font-size: 0.75rem;
  color: #6b7280;
  text-align: center;
}

/* =========================================
   Matrix Section
   ========================================= */
.matrix-section {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 1rem;
  overflow: hidden;
}

.matrix-section__header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #f3f4f6;
  background: #fafafa;
}

.matrix-section__icon {
  font-size: 1.25rem;
}

.matrix-section__title {
  font-size: 1rem;
  font-weight: 600;
  color: #111827;
  margin: 0;
  flex: 1;
}

.matrix-section__hint {
  font-size: 0.75rem;
  color: #9ca3af;
}

/* =========================================
   Matrix Table
   ========================================= */
.matrix-wrapper {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.matrix-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.matrix-table__corner {
  background: #f9fafb;
  color: #6b7280;
  font-weight: 600;
  text-align: left;
  padding: 0.625rem 0.875rem;
  border-right: 2px solid #e5e7eb;
  border-bottom: 2px solid #e5e7eb;
  white-space: nowrap;
  min-width: 120px;
}

.matrix-table__tribe-head {
  background: #f9fafb;
  color: #374151;
  font-weight: 600;
  text-align: center;
  padding: 0.625rem 0.75rem;
  border-bottom: 2px solid #e5e7eb;
  border-right: 1px solid #f3f4f6;
  white-space: nowrap;
}

.matrix-table__total-head {
  background: #f0fdf4;
  color: #065f46;
  font-weight: 700;
  text-align: center;
  padding: 0.625rem 0.75rem;
  border-bottom: 2px solid #e5e7eb;
  border-left: 2px solid #d1fae5;
  white-space: nowrap;
}

.matrix-table__row-label {
  background: #f9fafb;
  color: #374151;
  font-weight: 500;
  padding: 0.5rem 0.875rem;
  border-right: 2px solid #e5e7eb;
  border-bottom: 1px solid #f3f4f6;
  white-space: nowrap;
}

.matrix-table__cell {
  text-align: center;
  padding: 0.5rem 0.75rem;
  color: #9ca3af;
  border-right: 1px solid #f9fafb;
  border-bottom: 1px solid #f3f4f6;
}

.matrix-table__cell--has-value {
  color: #1f2937;
  font-weight: 600;
  background: #f0fdf4;
}

.matrix-table__row-total {
  text-align: center;
  padding: 0.5rem 0.75rem;
  font-weight: 700;
  color: #065f46;
  background: #f0fdf4;
  border-left: 2px solid #d1fae5;
  border-bottom: 1px solid #f3f4f6;
}

.matrix-table__footer-label {
  background: #e5e7eb;
  color: #374151;
  font-weight: 600;
  padding: 0.625rem 0.875rem;
  border-right: 2px solid #d1d5db;
  border-top: 2px solid #d1d5db;
}

.matrix-table__footer-total {
  background: #e5e7eb;
  text-align: center;
  font-weight: 700;
  color: #1f2937;
  padding: 0.625rem 0.75rem;
  border-right: 1px solid #d1d5db;
  border-top: 2px solid #d1d5db;
}

.matrix-table__footer-grand {
  background: #d1fae5;
  text-align: center;
  font-weight: 700;
  color: #065f46;
  padding: 0.625rem 0.75rem;
  border-left: 2px solid #a7f3d0;
  border-top: 2px solid #d1d5db;
}

.matrix-table__empty {
  text-align: center;
  color: #9ca3af;
  padding: 2rem;
  font-style: italic;
}

/* =========================================
   Bar List (處理方式橫條圖)
   ========================================= */
/* =========================================
   Summary Bars（總覽橫條圖）
   ========================================= */
.summary-section {
  box-shadow: 0 0 0 2px #e7f7f0;
}

.summary-bars {
  padding: 1rem 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
}

.summary-bar-item {
  display: grid;
  grid-template-columns: 100px 1fr 52px;
  align-items: center;
  gap: 0.75rem;
}

.summary-bar-item__label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #1f2937;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.summary-bar-item__track {
  height: 1.25rem;
  background: #f3f4f6;
  border-radius: 0.375rem;
  overflow: hidden;
}

.summary-bar-item__fill {
  height: 100%;
  border-radius: 0.375rem;
  transition: width 0.45s cubic-bezier(0.4, 0, 0.2, 1);
}

.summary-bar-item__fill--food {
  background: linear-gradient(90deg, #34d399 0%, #10b981 100%);
}

.summary-bar-item__fill--processing {
  background: linear-gradient(90deg, #fb923c 0%, #f97316 100%);
}

.summary-bar-item__count {
  font-size: 1rem;
  font-weight: 700;
  color: #111827;
  text-align: right;
  min-width: 2rem;
}
</style>

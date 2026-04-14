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

      <!-- 部落選擇器 -->
      <div class="tribe-selector">
        <label class="tribe-selector__label">目前檢視部落：</label>
        <select v-model="selectedTribe" class="tribe-selector__dropdown">
          <option v-for="tribe in tribes" :key="tribe" :value="tribe">
            {{ tribe }}
          </option>
        </select>
      </div>

      <!-- 總覽卡片 -->
      <div class="overview-card">
        <div class="overview-card__item overview-card__item--highlight">
          <span class="overview-card__num">{{ tribeFishCount }}</span>
          <span class="overview-card__label">已蒐集魚種數</span>
        </div>
        <div class="overview-card__item">
          <span class="overview-card__num">{{ tribeCategoryTotal }}</span>
          <span class="overview-card__label">食用分類紀錄</span>
        </div>
        <div class="overview-card__item">
          <span class="overview-card__num">{{ tribeProcessingTotal }}</span>
          <span class="overview-card__label">處理方式紀錄</span>
        </div>
        <div class="overview-card__item">
          <span class="overview-card__num">{{ tribeCaptureTotal }}</span>
          <span class="overview-card__label">捕獲紀錄筆數</span>
        </div>
      </div>

      <!-- 食用分類詳細清單 -->
      <section class="matrix-section summary-section">
        <div class="matrix-section__header">
          <span class="matrix-section__icon">🍽️</span>
          <h2 class="matrix-section__title">食用分類詳細</h2>
          <span class="tribe-badge tribe-badge--food">{{ selectedTribe }}</span>
          <span class="matrix-section__hint">單位：筆</span>
        </div>
        <div class="summary-bars">
          <div v-for="category in tribeFoodCategories" :key="category" class="summary-bar-item">
            <div class="summary-bar-item__label">{{ category || '未分類' }}</div>
            <div class="summary-bar-item__count">
              <template v-if="getTribeCategoryCount(category) > 0">
                {{ getTribeCategoryCount(category) }} 筆
              </template>
              <span v-else class="summary-bar-item__unrecorded">尚未記錄</span>
            </div>
            <div class="summary-bar-item__track">
              <div
                class="summary-bar-item__fill summary-bar-item__fill--food"
                :style="{ width: tribeCategoryBarWidth(getTribeCategoryCount(category)) }"
              ></div>
            </div>
          </div>
          <p v-if="tribeFoodCategories.length === 0" class="summary-bars__empty">
            尚無食用分類資料
          </p>
        </div>
      </section>

      <!-- 處理方式詳細清單 -->
      <section class="matrix-section summary-section">
        <div class="matrix-section__header">
          <span class="matrix-section__icon">🔪</span>
          <h2 class="matrix-section__title">處理方式詳細</h2>
          <span class="tribe-badge tribe-badge--processing">{{ selectedTribe }}</span>
          <span class="matrix-section__hint">單位：筆</span>
        </div>
        <div class="summary-bars">
          <div v-for="method in tribeProcessingMethods" :key="method" class="summary-bar-item">
            <div class="summary-bar-item__label">{{ method || '未記錄' }}</div>
            <div class="summary-bar-item__count">
              <template v-if="getTribeProcessingCount(method) > 0">
                {{ getTribeProcessingCount(method) }} 筆
              </template>
              <span v-else class="summary-bar-item__unrecorded">尚未記錄</span>
            </div>
            <div class="summary-bar-item__track">
              <div
                class="summary-bar-item__fill summary-bar-item__fill--processing"
                :style="{ width: tribeProcessingBarWidth(getTribeProcessingCount(method)) }"
              ></div>
            </div>
          </div>
          <p v-if="tribeProcessingMethods.length === 0" class="summary-bars__empty">
            尚無處理方式資料
          </p>
        </div>
      </section>

      <!-- 捕獲方式詳細清單 -->
      <section class="matrix-section summary-section">
        <div class="matrix-section__header">
          <span class="matrix-section__icon">🎣</span>
          <h2 class="matrix-section__title">捕獲方式詳細</h2>
          <span class="tribe-badge tribe-badge--capture">{{ selectedTribe }}</span>
          <span class="matrix-section__hint">單位：筆</span>
        </div>
        <div class="summary-bars">
          <div v-for="method in tribeCaptureMethodList" :key="method" class="summary-bar-item">
            <div class="summary-bar-item__label">{{ method || '未記錄' }}</div>
            <div class="summary-bar-item__count">
              <template v-if="getTribeCaptureCount(method) > 0">
                {{ getTribeCaptureCount(method) }} 筆
              </template>
              <span v-else class="summary-bar-item__unrecorded">尚未記錄</span>
            </div>
            <div class="summary-bar-item__track">
              <div
                class="summary-bar-item__fill summary-bar-item__fill--capture"
                :style="{ width: tribeCaptureBarWidth(getTribeCaptureCount(method)) }"
              ></div>
            </div>
          </div>
          <p v-if="tribeCaptureMethodList.length === 0" class="summary-bars__empty">
            尚無捕獲方式資料
          </p>
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
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'

// ---- Props ----
const props = defineProps({
  tribes: { type: Array, required: true },
  foodCategories: { type: Array, required: true },
  processingMethods: { type: Array, required: true },
  captureMethods: { type: Array, required: true },
  statistics: { type: Object, required: true },
})

// ---- State ----
const selectedTribe = ref('iraraley')

// ---- 衍生資料：選定部落的分類與方式（包含 config 未記錄的選項 + 實際存在的選項） ----

/**
 * 該部落的所有食用分類（包括 config 中定義但尚未有紀錄的選項）
 */
const tribeFoodCategories = computed(() => {
  // 取得該部落實際出現過的食用分類
  const tribeCategories = Object.keys(
    props.statistics.food_categories_by_tribe?.[selectedTribe.value] ?? {}
  )
  // 與 config 中的食用分類並集，優先 config 順序
  const merged = [...new Set([...props.foodCategories, ...tribeCategories])]
  return merged.filter((c) => c !== '')
})

/**
 * 該部落的所有處理方式（包括 config 中定義但尚未有紀錄的選項）
 */
const tribeProcessingMethods = computed(() => {
  const tribeMethods = Object.keys(
    props.statistics.processing_methods_by_tribe?.[selectedTribe.value] ?? {}
  )
  const merged = [...new Set([...props.processingMethods, ...tribeMethods])]
  return merged.filter((m) => m !== '')
})

/**
 * 該部落的捕獲方式清單（包括 config 中定義但尚未有紀錄的選項）
 */
const tribeCaptureMethodList = computed(() => {
  const fromStats = Object.keys(
    props.statistics.capture_methods_by_tribe?.[selectedTribe.value] ?? {}
  )
  return [...new Set([...props.captureMethods, ...fromStats])]
})

// ---- 衍生資料：跨部落總覽（供矩陣表使用） ----

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
  const fromStats = Object.values(props.statistics.capture_methods_by_tribe).flatMap(Object.keys)
  return [...new Set([...props.captureMethods, ...fromStats])]
})

// ---- 選定部落的總計 ----

const tribeFishCount = computed(
  () => props.statistics.fish_count_by_tribe?.[selectedTribe.value] ?? 0
)

const tribeCategoryTotal = computed(() =>
  Object.values(props.statistics.food_categories_by_tribe?.[selectedTribe.value] ?? {}).reduce(
    (sum, n) => sum + n,
    0
  )
)

const tribeProcessingTotal = computed(() =>
  Object.values(props.statistics.processing_methods_by_tribe?.[selectedTribe.value] ?? {}).reduce(
    (sum, n) => sum + n,
    0
  )
)

const tribeCaptureTotal = computed(() =>
  Object.values(props.statistics.capture_methods_by_tribe?.[selectedTribe.value] ?? {}).reduce(
    (sum, n) => sum + n,
    0
  )
)

// ---- 跨部落的總計 ----

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

// ---- 選定部落的查詢函式 ----

/**
 * 查詢選定部落的特定食用分類紀錄數
 */
function getTribeCategoryCount(category) {
  return props.statistics.food_categories_by_tribe?.[selectedTribe.value]?.[category] ?? 0
}

/**
 * 查詢選定部落的特定處理方式紀錄數
 */
function getTribeProcessingCount(method) {
  return props.statistics.processing_methods_by_tribe?.[selectedTribe.value]?.[method] ?? 0
}

/**
 * 查詢選定部落的特定捕獲方式紀錄數
 */
function getTribeCaptureCount(method) {
  return props.statistics.capture_methods_by_tribe?.[selectedTribe.value]?.[method] ?? 0
}

/**
 * 計算視覺化橫條寬度（針對選定部落）
 */
function tribeCategoryBarWidth(count) {
  if (!tribeCategoryTotal.value) return '0%'
  return Math.round((count / tribeCategoryTotal.value) * 100) + '%'
}

function tribeProcessingBarWidth(count) {
  if (!tribeProcessingTotal.value) return '0%'
  return Math.round((count / tribeProcessingTotal.value) * 100) + '%'
}

function tribeCaptureBarWidth(count) {
  if (!tribeCaptureTotal.value) return '0%'
  return Math.round((count / tribeCaptureTotal.value) * 100) + '%'
}

// ---- 跨部落的查詢函式 ----

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
   Tribe Selector
   ========================================= */
.tribe-selector {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 0.75rem 1rem;
}

.tribe-selector__label {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-text-primary, #111827);
  white-space: nowrap;
}

.tribe-selector__dropdown {
  padding: 0.5rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  background: white;
  font-size: 0.875rem;
  cursor: pointer;
  color: var(--color-text-primary, #111827);
  transition:
    border-color 0.2s,
    box-shadow 0.2s;
}

.tribe-selector__dropdown:hover {
  border-color: #9ca3af;
}

.tribe-selector__dropdown:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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

.overview-card__item--highlight .overview-card__num {
  color: #0284c7;
}

.overview-card__item--highlight .overview-card__label {
  font-weight: 600;
  color: #0369a1;
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
  grid-template-columns: 110px 80px 1fr;
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

.summary-bar-item__count {
  font-size: 0.875rem;
  font-weight: 700;
  color: #111827;
  white-space: nowrap;
}

.summary-bar-item__unrecorded {
  font-size: 0.75rem;
  font-weight: 400;
  color: #9ca3af;
  font-style: italic;
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

.summary-bar-item__fill--capture {
  background: linear-gradient(90deg, #60a5fa 0%, #3b82f6 100%);
}

.summary-bars__empty {
  color: #9ca3af;
  text-align: center;
  padding: 1.5rem;
  font-style: italic;
  font-size: 0.875rem;
  margin: 0;
}

/* =========================================
   Tribe Badge
   ========================================= */
.tribe-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.625rem;
  border-radius: 9999px;
  font-size: 0.8125rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.tribe-badge--food {
  background: #d1fae5;
  color: #065f46;
}

.tribe-badge--processing {
  background: #ffedd5;
  color: #9a3412;
}

.tribe-badge--capture {
  background: #dbeafe;
  color: #1e40af;
}
</style>

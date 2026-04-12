<template>
  <Head title="統計面板 | 管理後台" />

  <FishAppLayout page-title="統計面板" mobile-back-url="/fishs" mobile-back-text="among no tao">
    <div class="dashboard-root">

      <!-- 頁面標題 -->
      <div class="dashboard-header">
        <div class="dashboard-header__icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
        </div>
        <div>
          <h1 class="dashboard-header__title">資料統計面板</h1>
          <p class="dashboard-header__subtitle">即時掌握系統各類資料總覽</p>
        </div>
      </div>

      <!-- 部落切換器 -->
      <div class="tribe-switcher">
        <div class="tribe-switcher__inner">
          <button
            id="tribe-btn-all"
            class="tribe-btn"
            :class="{ 'tribe-btn--active': !selectedTribe }"
            :disabled="isLoading && !selectedTribe"
            @click="selectTribe(null)"
          >
            <span class="tribe-btn__dot tribe-btn__dot--all"></span>
            全部
          </button>
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
          <svg class="loading-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 100 16 8 8 0 01-8-8z"/>
          </svg>
          載入中…
        </div>
      </div>

      <!-- 篩選標籤 -->
      <div v-if="selectedTribe" class="filter-banner">
        <svg class="filter-banner__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
        </svg>
        目前篩選：<strong>{{ selectedTribe }}</strong> 部落的資料
        <button class="filter-banner__clear" @click="selectTribe(null)">清除篩選 ✕</button>
      </div>

      <!-- 頂部 Summary Cards -->
      <div class="summary-grid">
        <div class="summary-card summary-card--fish">
          <div class="summary-card__icon">🐟</div>
          <div class="summary-card__body">
            <div class="summary-card__number">{{ fishStats.total }}</div>
            <div class="summary-card__label">{{ selectedTribe ? `${selectedTribe} 魚種` : '魚類' }}</div>
          </div>
          <div class="summary-card__sub-group">
            <span class="summary-card__sub">有捕獲紀錄 <strong>{{ fishStats.with_capture_record }}</strong></span>
            <span class="summary-card__sub">有音檔 <strong>{{ fishStats.with_audio }}</strong></span>
            <span class="summary-card__sub">有部落分類 <strong>{{ fishStats.with_tribal_classification }}</strong></span>
          </div>
        </div>

        <div class="summary-card summary-card--capture">
          <div class="summary-card__icon">📸</div>
          <div class="summary-card__body">
            <div class="summary-card__number">{{ captureStats.total }}</div>
            <div class="summary-card__label">捕獲紀錄</div>
          </div>
        </div>

        <div class="summary-card summary-card--tribal">
          <div class="summary-card__icon">🏘️</div>
          <div class="summary-card__body">
            <div class="summary-card__number">{{ tribalStats.total }}</div>
            <div class="summary-card__label">部落分類</div>
          </div>
        </div>

        <div class="summary-card summary-card--audio">
          <div class="summary-card__icon">🔊</div>
          <div class="summary-card__body">
            <div class="summary-card__number">{{ audioStats.total }}</div>
            <div class="summary-card__label">音檔</div>
          </div>
        </div>

        <div class="summary-card summary-card--note">
          <div class="summary-card__icon">📝</div>
          <div class="summary-card__body">
            <div class="summary-card__number">{{ noteStats.total }}</div>
            <div class="summary-card__label">地方知識</div>
          </div>
        </div>

        <div class="summary-card summary-card--user">
          <div class="summary-card__icon">👥</div>
          <div class="summary-card__body">
            <div class="summary-card__number">{{ userStats.total }}</div>
            <div class="summary-card__label">LINE 使用者</div>
          </div>
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
          <BarList :items="captureStats.by_tribe.map(i => ({ label: i.tribe, count: i.count }))" :total="captureStats.total" color="capture" />
        </div>

        <!-- 部落模式：捕獲紀錄 by 捕獲方式 -->
        <div class="detail-card" v-if="selectedTribe && captureStats.by_method?.length">
          <div class="detail-card__header">
            <span class="detail-card__icon">📸</span>
            <h2 class="detail-card__title">捕獲方式分佈</h2>
            <span class="detail-card__badge">{{ captureStats.total }} 筆</span>
          </div>
          <BarList :items="captureStats.by_method" :total="captureStats.total" color="capture" />
        </div>

        <!-- 全部模式：部落分類 by 部落 -->
        <div class="detail-card" v-if="!selectedTribe && tribalStats.by_tribe?.length">
          <div class="detail-card__header">
            <span class="detail-card__icon">🏘️</span>
            <h2 class="detail-card__title">部落分類分佈</h2>
            <span class="detail-card__badge">{{ tribalStats.total }} 筆</span>
          </div>
          <BarList :items="tribalStats.by_tribe.map(i => ({ label: i.tribe, count: i.count }))" :total="tribalStats.total" color="tribal" />
        </div>

        <!-- 部落模式：部落分類 by 食物分類 -->
        <div class="detail-card" v-if="selectedTribe && tribalStats.by_food_category?.length">
          <div class="detail-card__header">
            <span class="detail-card__icon">🏘️</span>
            <h2 class="detail-card__title">食用分類分佈</h2>
            <span class="detail-card__badge">{{ tribalStats.total }} 種</span>
          </div>
          <BarList :items="tribalStats.by_food_category" :total="tribalStats.total" color="tribal" />
        </div>

        <!-- 部落模式：部落分類 by 處理方法 -->
        <div class="detail-card" v-if="selectedTribe && tribalStats.by_processing_method?.length">
          <div class="detail-card__header">
            <span class="detail-card__icon">🔪</span>
            <h2 class="detail-card__title">處理方式分佈</h2>
            <span class="detail-card__badge">{{ tribalStats.total }} 種</span>
          </div>
          <BarList :items="tribalStats.by_processing_method" :total="tribalStats.total" color="processing" />
        </div>

        <!-- 音檔 by 地區（全部模式） -->
        <div class="detail-card" v-if="!selectedTribe && audioStats.by_locate?.length">
          <div class="detail-card__header">
            <span class="detail-card__icon">🔊</span>
            <h2 class="detail-card__title">音檔地區分佈</h2>
            <span class="detail-card__badge">{{ audioStats.total }} 筆</span>
          </div>
          <BarList :items="audioStats.by_locate.map(i => ({ label: i.locate, count: i.count }))" :total="audioStats.total" color="audio" />
        </div>

        <!-- 地方知識 by 類型 -->
        <div class="detail-card" v-if="noteStats.by_type?.length">
          <div class="detail-card__header">
            <span class="detail-card__icon">📝</span>
            <h2 class="detail-card__title">地方知識類型</h2>
            <span class="detail-card__badge">{{ noteStats.total }} 筆</span>
          </div>
          <BarList :items="noteStats.by_type.map(i => ({ label: i.type, count: i.count }))" :total="noteStats.total" color="note" />
        </div>

        <!-- LINE 使用者 by role（不受部落篩選） -->
        <div class="detail-card" v-if="userStats.by_role?.length">
          <div class="detail-card__header">
            <span class="detail-card__icon">👥</span>
            <h2 class="detail-card__title">LINE 使用者角色</h2>
            <span class="detail-card__badge">{{ userStats.total }} 人</span>
          </div>
          <BarList :items="userStats.by_role.map(i => ({ label: roleLabel(i.role), count: i.count }))" :total="userStats.total" color="user" />
        </div>

      </div>
    </div>
  </FishAppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'

// ---- 子元件：橫條圖列表 ----
// 抽成 inline component 以便複用
const BarList = {
  props: {
    items: { type: Array, required: true },
    total: { type: Number, required: true },
    color: { type: String, default: 'capture' },
  },
  template: `
    <div class="bar-list">
      <div v-for="item in items" :key="item.label" class="bar-item">
        <div class="bar-item__label" :title="item.label">{{ item.label }}</div>
        <div class="bar-item__track">
          <div
            class="bar-item__fill"
            :class="'bar-item__fill--' + color"
            :style="{ width: barWidth(item.count, total) }"
          ></div>
        </div>
        <div class="bar-item__count">{{ item.count }}</div>
      </div>
    </div>
  `,
  setup(props) {
    function barWidth(count, total) {
      if (!total) return '0%'
      return Math.round((count / total) * 100) + '%'
    }
    return { barWidth }
  },
}

// ---- Props ----
const props = defineProps({
  tribes:        { type: Array,  required: true },
  selectedTribe: { type: String, default: null },
  fishStats:     { type: Object, required: true },
  captureStats:  { type: Object, required: true },
  tribalStats:   { type: Object, required: true },
  audioStats:    { type: Object, required: true },
  noteStats:     { type: Object, required: true },
  userStats:     { type: Object, required: true },
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
    onFinish: () => { isLoading.value = false },
  })
}

// ---- 工具函式 ----
function roleLabel(role) {
  const map = { admin: '管理員', editor: '編輯人員', viewer: '瀏覽者' }
  return map[role] ?? role
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
  box-shadow: 0 1px 4px rgba(0,0,0,0.08);
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
@keyframes spin { to { transform: rotate(360deg); } }

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
   Summary Grid
   ========================================= */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
}

.summary-card {
  background: #fff;
  border-radius: 1rem;
  padding: 1.25rem 1.25rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.07), 0 4px 12px rgba(0,0,0,0.04);
  border: 1px solid #f3f4f6;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.summary-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(0,0,0,0.1);
}
.summary-card__icon { font-size: 1.75rem; line-height: 1; }
.summary-card__body { display: flex; flex-direction: column; gap: 0.125rem; }
.summary-card__number {
  font-size: 2rem;
  font-weight: 800;
  line-height: 1;
  color: #111827;
}
.summary-card__label { font-size: 0.8125rem; font-weight: 500; color: #6b7280; }
.summary-card__sub-group {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  border-top: 1px solid #f3f4f6;
  padding-top: 0.625rem;
}
.summary-card__sub { font-size: 0.75rem; color: #9ca3af; }
.summary-card__sub strong { color: #374151; font-weight: 600; }

.summary-card--fish    { border-top: 3px solid #3b82f6; }
.summary-card--capture { border-top: 3px solid #f59e0b; }
.summary-card--tribal  { border-top: 3px solid #10b981; }
.summary-card--audio   { border-top: 3px solid #8b5cf6; }
.summary-card--note    { border-top: 3px solid #ec4899; }
.summary-card--user    { border-top: 3px solid #06b6d4; }

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
  box-shadow: 0 1px 3px rgba(0,0,0,0.07), 0 4px 12px rgba(0,0,0,0.04);
  border: 1px solid #f3f4f6;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.detail-card__header { display: flex; align-items: center; gap: 0.5rem; }
.detail-card__icon { font-size: 1.25rem; line-height: 1; }
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
   Bar List（全局，非 scoped 無法直接作用於 inline component）
   ========================================= */
:deep(.bar-list) {
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
}
:deep(.bar-item) {
  display: grid;
  grid-template-columns: 7rem 1fr 2.5rem;
  align-items: center;
  gap: 0.625rem;
}
:deep(.bar-item__label) {
  font-size: 0.8125rem;
  color: #374151;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
:deep(.bar-item__track) {
  background: #f3f4f6;
  border-radius: 999px;
  height: 0.5rem;
  overflow: hidden;
}
:deep(.bar-item__fill) {
  height: 100%;
  border-radius: 999px;
  transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}
:deep(.bar-item__fill--capture) { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
:deep(.bar-item__fill--tribal)  { background: linear-gradient(90deg, #10b981, #34d399); }
:deep(.bar-item__fill--audio)   { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }
:deep(.bar-item__fill--note)       { background: linear-gradient(90deg, #ec4899, #f472b6); }
:deep(.bar-item__fill--user)       { background: linear-gradient(90deg, #06b6d4, #22d3ee); }
:deep(.bar-item__fill--processing) { background: linear-gradient(90deg, #f97316, #fb923c); }
:deep(.bar-item__count) {
  font-size: 0.8125rem;
  font-weight: 600;
  color: #374151;
  text-align: right;
}
</style>

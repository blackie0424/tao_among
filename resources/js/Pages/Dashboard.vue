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

      <!-- 頂部 Summary Cards -->
      <div class="summary-grid">
        <div class="summary-card summary-card--fish">
          <div class="summary-card__icon">🐟</div>
          <div class="summary-card__body">
            <div class="summary-card__number">{{ fishStats.total }}</div>
            <div class="summary-card__label">魚類</div>
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

      <!-- 详细分析區域 -->
      <div class="detail-grid">

        <!-- 捕獲紀錄 by 部落 -->
        <div class="detail-card" v-if="captureStats.by_tribe.length">
          <div class="detail-card__header">
            <span class="detail-card__icon">📸</span>
            <h2 class="detail-card__title">捕獲紀錄分佈</h2>
            <span class="detail-card__badge">{{ captureStats.total }} 筆</span>
          </div>
          <div class="bar-list">
            <div
              v-for="item in captureStats.by_tribe"
              :key="item.tribe"
              class="bar-item"
            >
              <div class="bar-item__label">{{ item.tribe }}</div>
              <div class="bar-item__track">
                <div
                  class="bar-item__fill bar-item__fill--capture"
                  :style="{ width: barWidth(item.count, captureStats.total) }"
                ></div>
              </div>
              <div class="bar-item__count">{{ item.count }}</div>
            </div>
          </div>
        </div>

        <!-- 部落分類 by 部落 -->
        <div class="detail-card" v-if="tribalStats.by_tribe.length">
          <div class="detail-card__header">
            <span class="detail-card__icon">🏘️</span>
            <h2 class="detail-card__title">部落分類分佈</h2>
            <span class="detail-card__badge">{{ tribalStats.total }} 筆</span>
          </div>
          <div class="bar-list">
            <div
              v-for="item in tribalStats.by_tribe"
              :key="item.tribe"
              class="bar-item"
            >
              <div class="bar-item__label">{{ item.tribe }}</div>
              <div class="bar-item__track">
                <div
                  class="bar-item__fill bar-item__fill--tribal"
                  :style="{ width: barWidth(item.count, tribalStats.total) }"
                ></div>
              </div>
              <div class="bar-item__count">{{ item.count }}</div>
            </div>
          </div>
        </div>

        <!-- 音檔 by 地區 -->
        <div class="detail-card" v-if="audioStats.by_locate.length">
          <div class="detail-card__header">
            <span class="detail-card__icon">🔊</span>
            <h2 class="detail-card__title">音檔地區分佈</h2>
            <span class="detail-card__badge">{{ audioStats.total }} 筆</span>
          </div>
          <div class="bar-list">
            <div
              v-for="item in audioStats.by_locate"
              :key="item.locate"
              class="bar-item"
            >
              <div class="bar-item__label">{{ item.locate }}</div>
              <div class="bar-item__track">
                <div
                  class="bar-item__fill bar-item__fill--audio"
                  :style="{ width: barWidth(item.count, audioStats.total) }"
                ></div>
              </div>
              <div class="bar-item__count">{{ item.count }}</div>
            </div>
          </div>
        </div>

        <!-- 地方知識 by 類型 -->
        <div class="detail-card" v-if="noteStats.by_type.length">
          <div class="detail-card__header">
            <span class="detail-card__icon">📝</span>
            <h2 class="detail-card__title">地方知識類型</h2>
            <span class="detail-card__badge">{{ noteStats.total }} 筆</span>
          </div>
          <div class="bar-list">
            <div
              v-for="item in noteStats.by_type"
              :key="item.type"
              class="bar-item"
            >
              <div class="bar-item__label">{{ item.type }}</div>
              <div class="bar-item__track">
                <div
                  class="bar-item__fill bar-item__fill--note"
                  :style="{ width: barWidth(item.count, noteStats.total) }"
                ></div>
              </div>
              <div class="bar-item__count">{{ item.count }}</div>
            </div>
          </div>
        </div>

        <!-- LINE 使用者 by role -->
        <div class="detail-card" v-if="userStats.by_role.length">
          <div class="detail-card__header">
            <span class="detail-card__icon">👥</span>
            <h2 class="detail-card__title">LINE 使用者角色</h2>
            <span class="detail-card__badge">{{ userStats.total }} 人</span>
          </div>
          <div class="bar-list">
            <div
              v-for="item in userStats.by_role"
              :key="item.role"
              class="bar-item"
            >
              <div class="bar-item__label">{{ roleLabel(item.role) }}</div>
              <div class="bar-item__track">
                <div
                  class="bar-item__fill bar-item__fill--user"
                  :style="{ width: barWidth(item.count, userStats.total) }"
                ></div>
              </div>
              <div class="bar-item__count">{{ item.count }}</div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </FishAppLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'

const props = defineProps({
  fishStats:    { type: Object, required: true },
  captureStats: { type: Object, required: true },
  tribalStats:  { type: Object, required: true },
  audioStats:   { type: Object, required: true },
  noteStats:    { type: Object, required: true },
  userStats:    { type: Object, required: true },
})

/** 計算橫條圖寬度百分比 */
function barWidth(count, total) {
  if (!total) return '0%'
  return Math.round((count / total) * 100) + '%'
}

/** 將角色 key 轉為可讀中文 */
function roleLabel(role) {
  const map = {
    admin:  '管理員',
    editor: '編輯人員',
    viewer: '瀏覽者',
  }
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
  gap: 2rem;
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

.summary-card__icon {
  font-size: 1.75rem;
  line-height: 1;
}
.summary-card__body {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}
.summary-card__number {
  font-size: 2rem;
  font-weight: 800;
  line-height: 1;
  color: #111827;
}
.summary-card__label {
  font-size: 0.8125rem;
  font-weight: 500;
  color: #6b7280;
}
.summary-card__sub-group {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  border-top: 1px solid #f3f4f6;
  padding-top: 0.625rem;
}
.summary-card__sub {
  font-size: 0.75rem;
  color: #9ca3af;
}
.summary-card__sub strong {
  color: #374151;
  font-weight: 600;
}

/* Card accent colours */
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
.bar-item__fill--capture { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
.bar-item__fill--tribal  { background: linear-gradient(90deg, #10b981, #34d399); }
.bar-item__fill--audio   { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }
.bar-item__fill--note    { background: linear-gradient(90deg, #ec4899, #f472b6); }
.bar-item__fill--user    { background: linear-gradient(90deg, #06b6d4, #22d3ee); }
.bar-item__count {
  font-size: 0.8125rem;
  font-weight: 600;
  color: #374151;
  text-align: right;
}
</style>

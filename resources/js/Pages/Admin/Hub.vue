<template>
  <Head title="後台控制台 | among" />
  <AdminLayout title="後台控制台">
    <div class="space-y-6">
      <p class="text-elder-body text-gray-500">歡迎回來，請從下方選擇管理功能。</p>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- 統計面板 -->
        <Link href="/dashboard" class="hub-card">
          <div class="hub-card__icon hub-card__icon--blue">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="hub-card__title">統計面板</div>
            <div class="hub-card__desc">部落資料完整度與分類統計</div>
          </div>
          <div class="hub-card__stat">
            <span class="hub-card__stat-num">{{ stats.fishCount }}</span>
            <span class="hub-card__stat-label">筆魚種</span>
          </div>
        </Link>

        <!-- 使用者管理 -->
        <Link href="/line-users" class="hub-card">
          <div class="hub-card__icon hub-card__icon--green">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="hub-card__title">
              使用者管理
              <span v-if="pendingUsers > 0" class="hub-card__badge">{{ pendingUsers }}</span>
            </div>
            <div class="hub-card__desc">指派田調人員與管理者角色</div>
          </div>
        </Link>

        <!-- 文獻管理 -->
        <Link href="/admin/references" class="hub-card">
          <div class="hub-card__icon hub-card__icon--indigo">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="hub-card__title">文獻管理</div>
            <div class="hub-card__desc">新增與編輯參考文獻資料</div>
          </div>
        </Link>

        <!-- 魚類報表 -->
        <Link href="/fish-report" class="hub-card">
          <div class="hub-card__icon hub-card__icon--orange">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 17v-2m3 2v-4m3 4v-6M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="hub-card__title">魚類報表</div>
            <div class="hub-card__desc">量化報告與發音覆蓋率</div>
          </div>
          <div class="hub-card__stat">
            <span class="hub-card__stat-num">{{ stats.audioCoverage }}%</span>
            <span class="hub-card__stat-label">發音覆蓋</span>
          </div>
        </Link>
      </div>

      <!-- 快速數字 -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="quick-stat">
          <div class="quick-stat__num">{{ stats.fishCount }}</div>
          <div class="quick-stat__label">總魚種數</div>
        </div>
        <div class="quick-stat">
          <div class="quick-stat__num">{{ stats.audioCoverage }}%</div>
          <div class="quick-stat__label">發音覆蓋率</div>
        </div>
        <div class="quick-stat quick-stat--warn">
          <div class="quick-stat__num">{{ stats.pendingAudio }}</div>
          <div class="quick-stat__label">待補發音</div>
        </div>
        <div class="quick-stat">
          <div class="quick-stat__num">{{ stats.monthlyNew }}</div>
          <div class="quick-stat__label">本月新增</div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({
  stats: {
    type: Object,
    required: true,
  },
  pendingUsers: {
    type: Number,
    default: 0,
  },
})
</script>

<style scoped>
.hub-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.25rem;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 0.875rem;
  text-decoration: none;
  transition: box-shadow 0.15s, border-color 0.15s;
  min-height: 5rem;
}
.hub-card:hover {
  box-shadow: 0 4px 16px rgba(0,0,0,0.08);
  border-color: #bfdbfe;
}
.hub-card__icon {
  width: 3rem;
  height: 3rem;
  border-radius: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: #fff;
}
.hub-card__icon--blue   { background: linear-gradient(135deg,#3b82f6,#6366f1); }
.hub-card__icon--green  { background: linear-gradient(135deg,#10b981,#059669); }
.hub-card__icon--indigo { background: linear-gradient(135deg,#6366f1,#8b5cf6); }
.hub-card__icon--orange { background: linear-gradient(135deg,#f97316,#f59e0b); }

.hub-card__title {
  font-size: 1.125rem; /* elder-body */
  font-weight: 600;
  color: #16181d;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.hub-card__desc {
  font-size: 0.9375rem; /* elder-aux */
  color: #6b7280;
  margin-top: 0.125rem;
}
.hub-card__badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.25rem;
  height: 1.25rem;
  background: #ef4444;
  color: #fff;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0 0.25rem;
}
.hub-card__stat {
  text-align: right;
  flex-shrink: 0;
}
.hub-card__stat-num {
  display: block;
  font-size: 1.375rem; /* elder-name */
  font-weight: 700;
  color: #16181d;
}
.hub-card__stat-label {
  font-size: 0.9375rem;
  color: #6b7280;
}

/* Quick stats */
.quick-stat {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 0.75rem;
  padding: 1rem;
  text-align: center;
}
.quick-stat--warn .quick-stat__num { color: #f97316; }
.quick-stat__num {
  font-size: 1.75rem; /* elder-title */
  font-weight: 700;
  color: #16181d;
}
.quick-stat__label {
  font-size: 0.9375rem;
  color: #6b7280;
  margin-top: 0.25rem;
}
</style>

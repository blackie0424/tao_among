<template>
  <Head :title="`${fish.name}的基本資料`" />

  <FishAppLayout
    :pageTitle="fish.name"
    mobileBackUrl="/fishs"
    :mobileBackText="mobileBackText"
    :showEditMenu="false"
  >
    <!-- 桌面雙欄：主內容 + 編輯列 -->
    <div class="flex gap-6 items-start">
      <!-- 主內容 -->
      <div class="flex-1 min-w-0">

        <!-- 分區 Tab 導覽 -->
        <div class="sticky top-20 z-20 bg-gray-50 -mx-4 px-4 pb-2">
          <div class="flex overflow-x-auto gap-1 no-scrollbar">
            <button
              v-for="tab in visibleTabs"
              :key="tab.key"
              class="tab-btn shrink-0"
              :class="{ 'tab-btn--active': activeTab === tab.key }"
              @click="switchTab(tab.key)"
            >
              {{ tab.label }}
            </button>
          </div>
        </div>

        <!-- Tab 內容 -->
        <div class="mt-4">
          <!-- 基本 -->
          <section v-show="activeTab === 'basic'">
            <FishGridLayout>
              <template #top-extra>
                <TribalClassificationSummary
                  :classifications="tribalClassifications"
                  :tribes="tribes"
                  :fishId="fish.id"
                />
              </template>
            </FishGridLayout>
          </section>

          <!-- 地方知識 -->
          <section v-show="activeTab === 'local'">
            <TribalClassificationSummary
              :classifications="tribalClassifications"
              :tribes="tribes"
              :fishId="fish.id"
            />
          </section>

          <!-- 進階知識 -->
          <section v-show="activeTab === 'advanced'">
            <FishAdvancedKnowledgeSection :fishNotes="fishNotes" :isEditor="isEditor" :user="user" />
          </section>

          <!-- 文獻知識（editor/admin 限定） -->
          <section v-show="activeTab === 'reference'">
            <ReferenceKnowledgeSection
              :referenceKnowledge="referenceKnowledge"
              :isEditor="isEditor"
              :user="user"
            />
          </section>

          <!-- 捕獲 -->
          <section v-show="activeTab === 'capture'">
            <CaptureRecordSection
              :captureRecords="captureRecords"
              :fishName="fish.name"
              :user="user"
            />
          </section>

          <!-- 發音 -->
          <section v-show="activeTab === 'audio'">
            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
              <h2 class="text-elder-name font-bold text-elder-text">發音</h2>
              <div v-if="fish.audio_url" class="flex items-center gap-4 p-4 bg-gray-50 rounded-lg">
                <Volume :audioUrl="fish.audio_url" />
                <span class="text-elder-body text-gray-700">目前發音檔</span>
              </div>
              <p v-else class="text-elder-body text-gray-400">尚無發音資料</p>
              <Link
                v-if="isEditor"
                :href="`/fish/${fish.id}/audio-list`"
                class="inline-flex items-center gap-2 text-elder-body text-blue-600 hover:text-blue-700 underline"
              >
                管理所有發音
              </Link>
            </div>
          </section>
        </div>
      </div>

      <!-- 桌面編輯列 -->
      <FishEditBar :fishId="fish.id" :canEdit="isEditor" variant="desktop" />
    </div>
  </FishAppLayout>

  <!-- 行動編輯列 -->
  <FishEditBar :fishId="fish.id" :canEdit="isEditor" variant="mobile" />
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import FishGridLayout from '@/Layouts/FishGridLayout.vue'
import TribalClassificationSummary from '@/Components/TribalClassification/TribalClassificationSummary.vue'
import CaptureRecordSection from '@/Components/CaptureRecord/CaptureRecordSection.vue'
import FishAdvancedKnowledgeSection from '@/Components/FishKnowledge/FishAdvancedKnowledgeSection.vue'
import ReferenceKnowledgeSection from '@/Components/ReferenceKnowledge/ReferenceKnowledgeSection.vue'
import FishEditBar from '@/Components/Global/FishEditBar.vue'
import Volume from '@/Components/UI/Volume.vue'

const props = defineProps({
  fish: Object,
  tribalClassifications: { type: Array, default: () => [] },
  captureRecords: { type: Array, default: () => [] },
  fishNotes: { type: Object, default: () => ({}) },
  referenceKnowledge: { type: Array, default: () => [] },
  tribes: { type: Array, default: () => [] },
})

const page = usePage()
const user = computed(() => page.props.auth?.user)
const isEditor = computed(() => ['editor', 'admin'].includes(user.value?.role))

const mobileBackText = computed(() => {
  return (props.fish?.name?.length || 0) > 12 ? '...' : 'among no tao'
})

// ─── 分區 Tab ─────────────────────────────────────────────
const ALL_TABS = [
  { key: 'basic',     label: '基本' },
  { key: 'local',     label: '地方知識' },
  { key: 'advanced',  label: '進階知識' },
  { key: 'reference', label: '文獻知識', editorOnly: true },
  { key: 'capture',   label: '捕獲' },
  { key: 'audio',     label: '發音' },
]

const visibleTabs = computed(() =>
  ALL_TABS.filter((t) => !t.editorOnly || isEditor.value)
)

const activeTab = ref('basic')

onMounted(() => {
  const param = new URLSearchParams(window.location.search).get('tab')
  if (param && visibleTabs.value.some((t) => t.key === param)) {
    activeTab.value = param
  }
})

function switchTab(key) {
  activeTab.value = key
  const url = new URL(window.location.href)
  url.searchParams.set('tab', key)
  window.history.replaceState({}, '', url.toString())
}
</script>

<style scoped>
.tab-btn {
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  font-size: 1rem; /* elder-body */
  font-weight: 500;
  color: #3f454c;
  background: transparent;
  border: none;
  cursor: pointer;
  transition: background-color 0.15s, color 0.15s;
  white-space: nowrap;
  min-height: 2.5rem;
}
.tab-btn:hover {
  background-color: #e5e7eb;
  color: #16181d;
}
.tab-btn--active {
  background-color: #dbeafe;
  color: #1d4ed8;
  font-weight: 600;
}
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

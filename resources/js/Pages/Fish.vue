<template>
  <Head :title="`${fish.name}的基本資料`" />

  <FishAppLayout
    :pageTitle="fish.name"
    mobileBackUrl="/fishs"
    mobileBackText="魚類列表"
    :stickyMobile="false"
    :showMobileTitle="false"
  >
    <!-- 魚頁內容 -->
    <div class="flex gap-6 items-start">
      <!-- 主內容 -->
      <div class="flex-1 min-w-0">
        <!-- 分區 Tab 導覽 -->
        <div class="sticky top-0 lg:top-20 z-20 bg-gray-50 -mx-4 px-4 pb-2">
          <FishTabBar :tabs="visibleTabs" :activeTab="activeTab" @select="switchTab" />
        </div>

        <!-- Tab 內容 -->
        <div class="mt-4">
          <!-- 基本 -->
          <section v-show="activeTab === 'basic'" data-testid="basic-tab">
            <div
              v-if="isEditor"
              data-testid="basic-management-actions"
              class="flex flex-wrap items-start gap-6 mb-4"
            >
              <TabEditActions
                :items="[
                  { label: '編輯名稱', href: `/fish/${fish.id}/edit` },
                ]"
              />
              <div aria-label="魚類刪除與合併" class="flex flex-wrap gap-3">
                <Link :href="`/fish/${fish.id}/merge`" class="rounded border border-red-600 px-4 py-2 text-red-700 hover:bg-red-50">合併魚類</Link>
                <button @click="confirmDelete" class="rounded bg-red-600 px-4 py-2 text-white hover:bg-red-700">刪除魚類</button>
              </div>
            </div>
            <FishGridLayout />
          </section>

          <!-- 地方知識 -->
          <section v-show="activeTab === 'local'" data-testid="local-tab">
            <TabEditActions
              v-if="isEditor"
              :items="[
                { label: '編輯地方知識', href: `/fish/${fish.id}/tribal-classifications/create` },
              ]"
            />
            <TribalClassificationSummary
              :classifications="tribalClassifications"
              :tribes="tribes"
              :fishId="fish.id"
            />
          </section>

          <!-- 進階知識 -->
          <section v-show="activeTab === 'advanced'">
            <TabEditActions
              v-if="isEditor"
              :items="[{ label: '管理進階知識', href: `/fish/${fish.id}/knowledge-list` }]"
            />
            <FishAdvancedKnowledgeSection
              :fishNotes="fishNotes"
              :isEditor="isEditor"
              :user="user"
            />
          </section>

          <!-- 文獻知識（editor/admin 限定） -->
          <section v-show="activeTab === 'reference'">
            <TabEditActions
              v-if="isEditor"
              :items="[{ label: '管理文獻知識', href: `/fish/${fish.id}/reference-knowledge` }]"
            />
            <ReferenceKnowledgeSection
              :referenceKnowledge="referenceKnowledge"
              :isEditor="isEditor"
              :user="user"
            />
          </section>

          <!-- 捕獲 -->
          <section v-show="activeTab === 'capture'">
            <TabEditActions
              v-if="isEditor"
              :items="[
                { label: '新增捕獲紀錄', href: `/fish/${fish.id}/capture-records/batch-create` },
                { label: '管理捕獲紀錄', href: `/fish/${fish.id}/capture-records` },
              ]"
            />
            <CaptureRecordSection
              :captureRecords="captureRecords"
              :fishId="fish.id"
              :canEdit="isEditor"
              :fishName="fish.name"
              :user="user"
            />
          </section>

          <!-- 發音 -->
          <section v-if="hasAudioAccess" v-show="activeTab === 'audio'">
            <TabEditActions
              v-if="isEditor"
              :items="[{ label: '新增發音', href: `/fish/${fish.id}/audio/create` }]"
            />
            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
              <h2 class="text-elder-name font-bold text-elder-text">發音</h2>
              <div v-if="fish.audio_url" class="flex items-center gap-4 p-4 bg-gray-50 rounded-lg">
                <Volume :audioUrl="fish.audio_url" />
                <span class="text-elder-body text-gray-700">目前發音檔</span>
              </div>
              <p v-else class="text-elder-body text-elder-subtext">尚無發音資料</p>
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
    </div>
  </FishAppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, Link, usePage, router } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import FishGridLayout from '@/Layouts/FishGridLayout.vue'
import FishTabBar from '@/Components/FishDetail/FishTabBar.vue'
import TribalClassificationSummary from '@/Components/TribalClassification/TribalClassificationSummary.vue'
import CaptureRecordSection from '@/Components/CaptureRecord/CaptureRecordSection.vue'
import FishAdvancedKnowledgeSection from '@/Components/FishKnowledge/FishAdvancedKnowledgeSection.vue'
import ReferenceKnowledgeSection from '@/Components/ReferenceKnowledge/ReferenceKnowledgeSection.vue'
import TabEditActions from '@/Components/Global/TabEditActions.vue'
import Volume from '@/Components/UI/Volume.vue'
import { canAccessAudio } from '@/constants/roles'

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
const hasAudioAccess = computed(() => canAccessAudio(user.value?.role))

// ─── 分區 Tab ─────────────────────────────────────────────
const ALL_TABS = [
  { key: 'basic', label: '基本' },
  { key: 'local', label: '地方知識' },
  { key: 'advanced', label: '進階知識', editorOnly: true },
  { key: 'reference', label: '文獻知識', editorOnly: true },
  { key: 'capture', label: '捕獲紀錄' },
  { key: 'audio', label: '發音', audioOnly: true },
]

const visibleTabs = computed(() =>
  ALL_TABS.filter(
    (t) => (!t.editorOnly || isEditor.value) && (!t.audioOnly || hasAudioAccess.value)
  )
)

const activeTab = ref('basic')

function confirmDelete() {
  if (confirm('確定要刪除這隻魚類資料嗎？此動作無法復原。')) {
    router.delete(`/fish/${props.fish.id}`)
  }
}

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

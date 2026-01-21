<template>
  <Head :title="`${fish.name}的基本資料`" />
  
  <FishGridLayout>
    <!-- 左欄額外內容：部落分類摘要 -->
    <template #left-extra>
      <section v-if="tribalClassifications?.length">
        <TribalClassificationSummary 
          :classifications="tribalClassifications" 
          :fishId="fish.id" 
        />
      </section>
    </template>

    <!-- 中欄：捕獲紀錄 -->
    <template #middle>
      <section>
        <div class="rounded-xl bg-white shadow-sm border border-gray-200 p-4">
          <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
            <div class="flex items-center gap-2">
              <h3 class="text-xl font-semibold text-gray-900">捕獲紀錄</h3>
              <span class="text-xs font-medium bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ captureRecords.length }}</span>
            </div>
            <Link v-if="user" :href="`/fish/${fish.id}/capture-records/create`" class="hidden lg:inline-flex items-center gap-1 text-sm text-teal-600 hover:text-teal-700 font-medium">
              <span class="text-lg leading-none">+</span> 新增照片
            </Link>
          </div>

          <div v-if="captureRecords.length" class="space-y-8">
            <div v-for="record in captureRecords" :key="record.id" class="flex flex-col gap-3">
              <div class="relative aspect-video rounded-xl overflow-hidden bg-gray-100 border border-gray-200 shadow-sm">
                <LazyImage :src="record.image_url" :alt="`捕獲紀錄`" wrapperClass="w-full h-full" imgClass="w-full h-full object-cover"/>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-8 text-gray-500"><p>尚未新增捕獲照片</p></div>
        </div>
      </section>
    </template>

    <!-- 右欄：知識筆記 -->
    <template #right>
      <section v-if="user || Object.keys(groupedNotes).length">
        <div class="rounded-xl bg-white shadow-sm border-gray-200 p-4 border">
          <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
            <h3 class="text-xl font-semibold text-gray-900">知識筆記</h3>
            <div v-if="user" class="hidden lg:flex items-center gap-2">
              <Link :href="`/fish/${fish.id}/create`" class="inline-flex items-center gap-1 text-sm text-teal-600 hover:text-teal-700 font-medium">
                <span class="text-lg leading-none">+</span> 新增進階知識
              </Link>
            </div>
          </div>
          <div v-if="Object.keys(groupedNotes).length" class="space-y-6">
            <div v-for="(items, type) in groupedNotes" :key="type">
            <h4 class="font-medium text-gray-800 mb-2 px-1 flex items-center">
              <span class="w-1 h-4 bg-teal-500 rounded-full mr-2"></span>
              {{ type }}
            </h4>
            <ul class="space-y-3">
              <li 
                v-for="note in items" 
                :key="note.id" 
                class="bg-gray-50 rounded-lg p-4 border border-gray-200"
              >
                <div class="flex justify-between items-start gap-3">
                  <div class="flex-1">
                    <span class="inline-flex self-start items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 mb-2">
                      {{ note.locate }}
                    </span>
                    <div class="text-gray-800 md:text-lg whitespace-pre-line leading-relaxed">{{ note.note }}</div>
                  </div>
                  
                </div>
              </li>
            </ul>
          </div>
        </div>
          </div>
          
      </section>
    </template>
  </FishGridLayout>
</template>

<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import FishGridLayout from '@/Layouts/FishGridLayout.vue'
import TribalClassificationSummary from '@/Components/TribalClassificationSummary.vue'
import LazyImage from '@/Components/LazyImage.vue'
import FishKnowledgeCard from '@/Components/FishKnowledgeCard.vue'

// 設定巢狀佈局
defineOptions({
  layout: FishAppLayout
})

const props = defineProps({
  fish: Object,
  tribalClassifications: { type: Array, default: () => [] },
  captureRecords: { type: Array, default: () => [] },
  fishNotes: { type: Object, default: () => ({}) },
})

const page = usePage()
const user = computed(() => page.props.auth?.user)
const groupedNotes = computed(() => props.fishNotes || {})
</script>
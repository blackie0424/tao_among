<template>
  <Head :title="`${fish.name}的基本資料`" />
  
  <AppLayout>
    
    <template #header>
      <AppHeader>
        <template #mobile-title>基本資料</template>
        <template #desktop-breadcrumb>{{ fish.name }}</template>
      </AppHeader>
    </template>

    <div class="lg:grid lg:grid-cols-3 lg:gap-8 lg:items-start">
      
      <div class="space-y-6 lg:sticky lg:top-20">
        <section>
          <FishDetailLeft :fish="fish" />
        </section>
        <section>
          <TribalClassificationSummary 
            :classifications="tribalClassifications" 
            :fishId="fish.id" 
          />
        </section>
      </div>

      <div class="space-y-6 mt-6 lg:mt-0 lg:h-[calc(100vh-8rem)] lg:overflow-y-auto lg:px-2 scrollbar-hide">
        <section>
           <div class="rounded-xl bg-white shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
              <div class="flex items-center gap-2">
                 <h3 class="text-xl font-semibold text-gray-900">捕獲紀錄</h3>
                 <span class="text-xs font-medium bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ captureRecords.length }}</span>
              </div>
              <Link v-if="user" :href="`/fish/${fish.id}/capture-records/create`" class="hidden lg:inline-flex ...">
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
      </div>

      <div class="space-y-6 mt-6 lg:mt-0 lg:h-[calc(100vh-8rem)] lg:overflow-y-auto lg:pl-2 scrollbar-hide">
        <section>
          <div class="rounded-xl bg-white shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
              <h3 class="text-xl font-semibold text-gray-900">知識筆記</h3>
              <div v-if="user" class="hidden lg:flex items-center gap-2">
                <Link :href="`/fish/${fish.id}/create`" class="inline-flex items-center gap-1 text-sm text-teal-600 ...">
                  <span class="text-lg leading-none">+</span> 新增進階知識
                </Link>
              </div>
            </div>
            <div v-if="Object.keys(groupedNotes).length" class="space-y-6">
                </div>
            <div v-else class="text-center py-8 text-gray-500"><p>尚未建立知識筆記</p></div>
          </div>
        </section>
      </div>
    </div>

    <template #bottom-nav>
      <BottomNavBar :fishId="fish.id" activeTab="basic" />
    </template>

  </AppLayout>
</template>

<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue' // 引入 Layout
import AppHeader from '@/Components/Global/AppHeader.vue'
import FishDetailLeft from '@/Components/FishDetailLeft.vue'
import TribalClassificationSummary from '@/Components/TribalClassificationSummary.vue'
import BottomNavBar from '@/Components/Global/BottomNavBar.vue'
import LazyImage from '@/Components/LazyImage.vue'

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
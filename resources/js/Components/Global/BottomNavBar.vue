<template>
  <nav
    v-if="user"
    class="fixed bottom-0 left-0 right-0 z-50 border-t border-[#e7eff3] bg-slate-50 px-4 pt-2 flex gap-2 lg:hidden"
    style="padding-bottom: calc(env(safe-area-inset-bottom) + 0.75rem)"
    role="navigation"
    aria-label="底部工具列"
  >
    <!-- 基本資料 (回頂端/圖鑑) -->
    <button
      class="flex flex-1 flex-col items-center justify-end gap-1 rounded-full text-[#0e171b]"
      @click="handleBasicInfoClick"
    >
      <div
        :class="[
          'flex h-8 items-center justify-center',
          activeTab === 'basic' ? 'text-[#0e171b]' : 'text-[#4d7f99]',
        ]"
      >
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24">
          <circle
            :style="{
              fill: activeTab === 'basic' ? 'rgb(30, 85, 246)' : 'none',
              stroke: 'currentColor',
              'stroke-linecap': 'round',
              'stroke-linejoin': 'round',
              'stroke-width': '2',
            }"
            cx="12"
            cy="12"
            r="10"
          ></circle>
          <path
            :style="{
              fill: 'none',
              stroke: activeTab === 'basic' ? 'white' : 'currentColor',
              'stroke-linecap': 'round',
              'stroke-linejoin': 'round',
              'stroke-width': '2',
            }"
            d="M12 16v-4"
          ></path>
          <circle
            :style="{
              fill: activeTab === 'basic' ? 'white' : 'currentColor',
              stroke: activeTab === 'basic' ? 'white' : 'currentColor',
              'stroke-width': '2',
            }"
            cx="12"
            cy="8"
            r="1"
          ></circle>
        </svg>
      </div>
      <p class="text-xs font-medium leading-normal tracking-[0.015em]">基本資料</p>
    </button>

    <!-- 影音紀錄 (Media Manager) -->
    <Link
      :href="`/fish/${fishId}/media-manager`"
      :class="[
        'flex flex-1 flex-col items-center justify-end gap-1',
        activeTab === 'media' ? 'text-[#0e171b]' : 'text-[#4d7f99]',
      ]"
    >
      <div
        :class="[
          'flex h-8 items-center justify-center',
          activeTab === 'media' ? 'text-[#0e171b]' : 'text-[#4d7f99]',
        ]"
      >
        <!-- Camera/Mic Icon -->
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24">
          <path
            v-if="activeTab === 'media'"
            fill="rgb(30, 85, 246)"
            d="M4 4h10l2 2v2h4v12H4V4zm2 2v10h12V8h-2l-2-2H6z"
          />
           <path
            v-else
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            d="M4 4h10l2 2v2h4v12H4V4zm2 2v10h12V8h-2l-2-2H6z"
          />
        </svg>
      </div>
      <p class="text-xs font-medium leading-normal tracking-[0.015em]">捕獲與發音</p>
    </Link>

    <!-- 知識筆記 (Knowledge Manager) -->
    <Link
      :href="`/fish/${fishId}/knowledge-manager`"
      :class="[
        'flex flex-1 flex-col items-center justify-end gap-1',
        activeTab === 'knowledge' ? 'text-[#0e171b]' : 'text-[#4d7f99]',
      ]"
    >
      <div
        :class="[
          'flex h-8 items-center justify-center',
          activeTab === 'knowledge' ? 'text-[#0e171b]' : 'text-[#4d7f99]',
        ]"
      >
        <!-- Book Icon -->
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24">
           <path
            v-if="activeTab === 'knowledge'"
            fill="rgb(30, 85, 246)"
            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
          />
          <path
             v-else
             fill="none"
             stroke="currentColor"
             stroke-width="2"
             stroke-linecap="round"
             stroke-linejoin="round"
             d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
          />
        </svg>
      </div>
      <p class="text-xs font-medium leading-normal tracking-[0.015em]">基本資料與知識</p>
    </Link>
  </nav>
</template>

<script setup>
import { computed } from 'vue'
import { usePage, router, Link } from '@inertiajs/vue3'

const props = defineProps({
  fishId: { type: [String, Number], required: true },
  activeTab: { type: String, default: 'basic' }, // 'basic', 'media', 'knowledge'
})

const page = usePage()
const user = computed(() => page.props.auth?.user)

const handleBasicInfoClick = () => {
  if (props.activeTab === 'basic') {
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } else {
    router.visit(`/fish/${props.fishId}`)
  }
}
</script>

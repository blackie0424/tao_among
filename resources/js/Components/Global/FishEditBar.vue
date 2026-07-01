<template>
  <!-- Desktop: sticky right column panel -->
  <div
    v-if="canEdit && (variant === 'desktop' || variant === 'auto')"
    class="hidden lg:flex sticky top-24 flex-col gap-2 w-44 shrink-0"
  >
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
      <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-100">
        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
        </svg>
        <span class="text-elder-aux font-semibold text-gray-700">編輯這筆資料</span>
      </div>
      <div class="flex flex-col">
        <Link
          v-for="action in actions"
          :key="action.label"
          :href="action.href"
          class="flex items-center gap-3 px-4 py-3 text-elder-aux text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition border-b border-gray-50 last:border-0 min-h-touch-secondary"
        >
          <component :is="action.icon" class="w-5 h-5 shrink-0 text-gray-400" />
          {{ action.label }}
        </Link>
      </div>
    </div>
  </div>

  <!-- Mobile: fixed bottom bar -->
  <div
    v-if="canEdit && (variant === 'mobile' || variant === 'auto')"
    class="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-gray-200 shadow-lg"
    :style="`padding-bottom: env(safe-area-inset-bottom)`"
  >
    <div class="flex items-stretch justify-around h-14">
      <Link
        v-for="action in mobileActions"
        :key="action.label"
        :href="action.href"
        class="flex flex-col items-center justify-center flex-1 text-gray-600 hover:text-blue-600 hover:bg-blue-50 transition gap-0.5 min-h-touch-primary"
      >
        <component :is="action.icon" class="w-5 h-5" />
        <span class="text-[11px] leading-tight">{{ action.label }}</span>
      </Link>
    </div>
  </div>
</template>

<script setup>
import { computed, h } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  fishId: { type: [Number, String], required: true },
  canEdit: { type: Boolean, required: true },
  variant: { type: String, default: 'auto', validator: (v) => ['auto', 'desktop', 'mobile'].includes(v) },
})

// SVG icon components
const IconPencil = { render: () => h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z' })
]) }
const IconPhoto = { render: () => h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z M15 13a3 3 0 11-6 0 3 3 0 016 0z' })
]) }
const IconVolume = { render: () => h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M15.536 8.464a5 5 0 010 7.072M12 6a7 7 0 010 12M9.879 16.121A3 3 0 1013.5 12H9a3 3 0 00-3.621 2.879' })
]) }
const IconMap = { render: () => h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z' })
]) }
const IconBook = { render: () => h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' })
]) }

const actions = computed(() => [
  { label: '名稱',     href: `/fish/${props.fishId}/edit`,                           icon: IconPencil },
  { label: '照片',     href: `/fish/${props.fishId}/media-manager`,                  icon: IconPhoto  },
  { label: '發音',     href: `/fish/${props.fishId}/audio/create`,                   icon: IconVolume },
  { label: '地方知識', href: `/fish/${props.fishId}/tribal-classifications/create`,  icon: IconMap    },
  { label: '進階知識', href: `/fish/${props.fishId}/knowledge-manager`,              icon: IconBook   },
])

// Mobile only shows 4 most common actions (space limited)
const mobileActions = computed(() => [
  { label: '名稱',     href: `/fish/${props.fishId}/edit`,                          icon: IconPencil },
  { label: '照片',     href: `/fish/${props.fishId}/media-manager`,                 icon: IconPhoto  },
  { label: '發音',     href: `/fish/${props.fishId}/audio/create`,                  icon: IconVolume },
  { label: '知識',     href: `/fish/${props.fishId}/knowledge-manager`,             icon: IconBook   },
])
</script>

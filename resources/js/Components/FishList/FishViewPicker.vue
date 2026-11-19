<template>
  <transition name="sheet">
    <div
      v-if="show"
      data-testid="view-picker"
      class="fixed inset-0 z-[100] flex items-end justify-center bg-black/40 lg:items-center"
      @click.self="$emit('close')"
    >
      <div
        ref="panel"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        tabindex="-1"
        class="sheet-panel relative max-h-full w-full max-w-md overflow-y-auto rounded-t-2xl border-t border-rule-strong bg-paper px-6 pb-8 pt-8 focus:outline-none lg:max-w-2xl lg:rounded-2xl lg:border"
      >
        <button
          type="button"
          data-testid="view-picker-close"
          aria-label="關閉"
          class="absolute right-3 top-3 flex h-12 w-12 items-center justify-center text-elder-subtext hover:text-elder-text focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/30"
          @click="$emit('close')"
        >
          ✕
        </button>
        <h2 :id="titleId" class="pr-10 font-serif text-elder-title text-elder-text">
          你想先認識哪些魚？
        </h2>
        <p class="mt-2 flex items-center gap-1 text-elder-aux text-elder-subtext">
          之後可以點右下角的
          <svg
            aria-hidden="true"
            class="inline h-5 w-5"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
          >
            <rect x="4" y="4" width="6.5" height="6.5" rx="1" />
            <rect x="13.5" y="4" width="6.5" height="6.5" rx="1" />
            <rect x="4" y="13.5" width="6.5" height="6.5" rx="1" />
            <rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1" />
          </svg>
          再換
        </p>
        <div class="mt-5 grid grid-cols-1 gap-3 lg:grid-cols-2">
          <button
            v-for="option in options"
            :key="option.key"
            type="button"
            :data-testid="`view-option-${option.key}`"
            :class="optionClass(option)"
            @click="$emit('select', option.key)"
          >
            <span class="flex flex-col items-start text-left">
              <span class="text-elder-name font-bold">{{ option.label }}</span>
              <span v-if="option.hint" class="text-elder-aux font-normal opacity-90">{{
                option.hint
              }}</span>
            </span>
            <span class="ml-3 flex-shrink-0 text-elder-aux font-normal">{{
              countText(option)
            }}</span>
          </button>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'

const ALL_VIEW_KEY = 'all'
const GROUP_UNIT_VIEWS = ['same_name']

const props = defineProps({
  show: { type: Boolean, default: false },
  // [{ key, label, hint, count }]，由後端依序給
  options: { type: Array, default: () => [] },
})

const emit = defineEmits(['select', 'close'])

const titleId = 'fish-view-picker-title'
const panel = ref(null)

const countText = (option) =>
  `${option.count} ${GROUP_UNIT_VIEWS.includes(option.key) ? '組' : '種'}`

// 「全部都看」是次要樣式，其他是主要樣式
const optionClass = (option) => [
  'flex min-h-touch-primary items-center justify-between rounded-xl px-4 py-2 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/30',
  option.key === ALL_VIEW_KEY
    ? 'border-2 border-brand bg-paper text-brand hover:bg-paper-raised'
    : 'bg-brand text-white hover:bg-brand-dark',
]

const onKeydown = (event) => {
  if (event.key === 'Escape') emit('close')
}

const unlockPage = () => {
  document.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = ''
}

// 開啟時：焦點進面板、鎖住背景捲動、監聽 Esc；關閉時還原
watch(
  () => props.show,
  async (open) => {
    if (!open) return unlockPage()
    document.addEventListener('keydown', onKeydown)
    document.body.style.overflow = 'hidden'
    await nextTick()
    panel.value?.focus()
  },
  { immediate: true }
)

onBeforeUnmount(unlockPage)
</script>

<style scoped>
.sheet-enter-active,
.sheet-leave-active {
  transition: opacity 0.25s ease;
}
.sheet-enter-active .sheet-panel,
.sheet-leave-active .sheet-panel {
  transition: transform 0.25s ease;
}
.sheet-enter-from,
.sheet-leave-to {
  opacity: 0;
}
.sheet-enter-from .sheet-panel,
.sheet-leave-to .sheet-panel {
  transform: translateY(100%);
}
</style>

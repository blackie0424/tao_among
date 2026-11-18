<template>
  <transition name="sheet">
    <div
      v-if="show"
      data-testid="sheet-overlay"
      class="fixed inset-0 z-[100] flex items-end justify-center bg-black/40 lg:items-center"
      @click.self="$emit('close')"
    >
      <div
        ref="panel"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        tabindex="-1"
        class="sheet-panel relative w-full max-w-md rounded-t-2xl border-t border-rule-strong bg-paper px-6 pb-8 pt-8 focus:outline-none lg:rounded-2xl lg:border"
      >
        <button
          type="button"
          data-testid="sheet-close"
          aria-label="關閉"
          class="absolute right-3 top-3 flex h-12 w-12 items-center justify-center text-elder-subtext hover:text-elder-text focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/30"
          @click="$emit('close')"
        >
          ✕
        </button>
        <h2
          :id="titleId"
          data-testid="sheet-title"
          class="pr-10 font-serif text-elder-title text-elder-text"
        >
          想認識「{{ displayName }}」？
        </h2>
        <p data-testid="sheet-body" class="mt-3 text-elder-body text-elder-text">
          {{ pendingActivation ? PENDING_TEXT : LOGIN_TEXT }}
        </p>
        <div class="mt-6 flex flex-col gap-3">
          <Link
            v-if="!pendingActivation"
            :href="loginHref"
            data-testid="sheet-login"
            class="inline-flex min-h-touch-primary items-center justify-center rounded-xl bg-brand px-4 text-elder-name font-bold text-white hover:bg-brand-dark focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/30"
          >
            登入，繼續看這條魚
          </Link>
          <button
            type="button"
            data-testid="sheet-browse"
            class="inline-flex min-h-touch-primary items-center justify-center rounded-xl border border-rule-strong bg-paper px-4 text-elder-name text-elder-text hover:bg-paper-raised focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/30"
            @click="$emit('close')"
          >
            先逛逛清單
          </button>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'

const LOGIN_TEXT = '登入後可以看這條魚的詳細資料。'
const PENDING_TEXT = '你的帳號還在等待開通'
const UNNAMED_TITLE_NAME = '這條魚'

const props = defineProps({
  show: { type: Boolean, default: false },
  fishId: { type: [Number, String], default: null },
  fishName: { type: String, default: '' },
  // 已登入但帳號還沒開通（role 為 guest）
  pendingActivation: { type: Boolean, default: false },
})

const emit = defineEmits(['close'])

const titleId = 'guest-login-sheet-title'
const panel = ref(null)
const displayName = computed(() => props.fishName?.trim() || UNNAMED_TITLE_NAME)
const loginHref = computed(() => `/login?redirect=/fish/${props.fishId}`)

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

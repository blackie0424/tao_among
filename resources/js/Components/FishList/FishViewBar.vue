<template>
  <div
    data-testid="view-bar"
    class="mb-4 rounded-xl border-2 border-brand bg-paper-raised px-4 py-3"
  >
    <p class="text-elder-aux text-elder-subtext">正在看</p>
    <p class="text-elder-name font-bold text-elder-text">{{ title }}</p>
    <p v-if="hint" class="text-elder-aux text-elder-subtext">{{ hint }}</p>

    <p
      v-if="empty"
      data-testid="view-empty"
      role="status"
      class="mt-3 text-elder-body text-elder-text"
    >
      這一組目前沒有魚
    </p>
    <div
      v-if="failed"
      data-testid="view-error"
      role="alert"
      class="mt-3 text-elder-body text-elder-text"
    >
      <p>載入失敗，請重試。</p>
    </div>

    <div class="mt-3 flex gap-2">
      <button
        v-if="failed"
        type="button"
        data-testid="view-error-retry"
        :class="buttonClass"
        @click="$emit('retry')"
      >
        重試
      </button>
      <button
        v-if="canChange && !empty && !failed"
        type="button"
        data-testid="view-bar-change"
        :class="buttonClass"
        @click="$emit('change')"
      >
        換一個
      </button>
      <button type="button" :data-testid="allTestId" :class="buttonClass" @click="$emit('all')">
        看全部
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  label: { type: String, default: '' },
  count: { type: Number, default: null },
  hint: { type: String, default: '' },
  // 選了看法後清單是 0 條
  empty: { type: Boolean, default: false },
  // 選了看法後載入失敗
  failed: { type: Boolean, default: false },
  // 小視窗可用（選項計算失敗時沒有小視窗，不顯示「換一個」）
  canChange: { type: Boolean, default: true },
})

defineEmits(['change', 'all', 'retry'])

const buttonClass =
  'flex min-h-touch-secondary flex-1 items-center justify-center rounded-xl border border-brand px-3 text-elder-body text-brand hover:bg-rule focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/30'

const title = computed(() =>
  props.count === null ? props.label : `${props.label}（${props.count}）`
)
// 0 筆與載入失敗時的「看全部」各有自己的測試識別
const allTestId = computed(() => {
  if (props.failed) return 'view-error-all'
  if (props.empty) return 'view-empty-all'
  return 'view-bar-all'
})
</script>

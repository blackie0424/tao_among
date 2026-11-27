<template>
  <!-- 魚島 3D（#201）：走近家屋某一件時，畫面下緣的提示橫條；打開後是問好的對話框。
       spot 是這一件的資料（名稱、稱呼、問好語）；橫條只顯示稱呼與操作說明，地點名稱在建物上方的招牌（規格修訂 2）。
       點橫條或對話框發出 activate，由上層切換 open -->
  <div v-if="spot" class="pointer-events-none absolute inset-x-3 bottom-3 flex justify-center">
    <button
      v-if="open"
      type="button"
      data-testid="spot-dialog"
      class="pointer-events-auto w-full max-w-xl rounded-island bg-white px-4 py-3 text-left shadow-lg"
      @click="emit('activate')"
    >
      <span
        data-testid="spot-dialog-speaker"
        class="block text-elder-aux font-bold text-island-muted"
      >
        {{ speaker }}
      </span>
      <span class="mt-1 block text-elder-name font-extrabold">{{ spot.greeting }}</span>
    </button>
    <button
      v-else
      type="button"
      data-testid="spot-prompt"
      class="pointer-events-auto flex w-full max-w-xl flex-wrap items-center justify-between gap-x-4 gap-y-1 rounded-island bg-white px-4 py-2.5 text-left shadow-lg"
      @click="emit('activate')"
    >
      <span class="text-elder-body font-extrabold">{{ spot.npc }}</span>
      <span class="text-elder-aux font-bold text-island-accent">{{ hint }}</span>
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  spot: { type: Object, default: null },
  open: { type: Boolean, default: false },
  speaker: { type: String, required: true },
  touch: { type: Boolean, default: false },
})
const emit = defineEmits(['activate'])

// 電腦按空白鍵；觸控裝置點那位家人
const hint = computed(() => (props.touch ? '點一下對方' : '按空白鍵'))
</script>

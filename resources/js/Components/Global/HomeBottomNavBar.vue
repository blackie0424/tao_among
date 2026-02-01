<template>
  <nav
    v-if="user"
    class="fixed bottom-0 left-0 right-0 z-50 border-t border-[#e7eff3] bg-slate-50 px-4 pb-3 pt-2 flex gap-2"
  >
    <!-- 新增魚類功能 -->
    <div class="flex flex-1 flex-col items-center justify-end gap-1 text-[#0e171b]">
      <a
        href="/fish/create"
        class="text-[#0e171b] hover:text-green-600 flex h-8 items-center justify-center transition-colors rounded-full"
      >
        <!-- Add Fish Icon -->
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24">
          <circle
            style="
              fill: none;
              stroke: currentColor;
              stroke-linecap: round;
              stroke-linejoin: round;
              stroke-width: 2;
            "
            cx="12"
            cy="12"
            r="10"
          ></circle>
          <line
            style="
              fill: none;
              stroke: currentColor;
              stroke-linecap: round;
              stroke-linejoin: round;
              stroke-width: 2;
            "
            x1="12"
            y1="8"
            x2="12"
            y2="16"
          ></line>
          <line
            style="
              fill: none;
              stroke: currentColor;
              stroke-linecap: round;
              stroke-linejoin: round;
              stroke-width: 2;
            "
            x1="8"
            y1="12"
            x2="16"
            y2="12"
          ></line>
        </svg>
      </a>
      <p class="text-[#0e171b] text-xs font-medium leading-normal tracking-[0.015em]">新增魚類</p>
    </div>
  </nav>
</template>

<script setup>
// 這個元件專門用於首頁的底部導航
// 包含三個主要功能：搜尋、比較、新增魚類
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const user = computed(() => page.props.auth?.user)
</script>

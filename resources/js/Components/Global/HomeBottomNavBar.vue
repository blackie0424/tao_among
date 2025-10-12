<template>
  <nav
    class="fixed bottom-0 left-0 right-0 z-50 border-t border-[#e7eff3] bg-slate-50 px-4 pb-3 pt-2 flex gap-2"
  >
    <!-- 搜尋功能 -->
    <a
      class="flex flex-1 flex-col items-center justify-end gap-1 rounded-full text-[#0e171b] hover:text-blue-600 transition-colors"
      href="/search"
    >
      <div
        class="text-[#0e171b] hover:text-blue-600 flex h-8 items-center justify-center transition-colors"
      >
        <!-- Search Icon -->
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24">
          <circle
            style="
              fill: none;
              stroke: currentColor;
              stroke-linecap: round;
              stroke-linejoin: round;
              stroke-width: 2;
            "
            cx="11"
            cy="11"
            r="8"
          ></circle>
          <path
            style="
              fill: none;
              stroke: currentColor;
              stroke-linecap: round;
              stroke-linejoin: round;
              stroke-width: 2;
            "
            d="M21 21l-4.35-4.35"
          ></path>
        </svg>
      </div>
      <p class="text-[#0e171b] text-xs font-medium leading-normal tracking-[0.015em]">搜尋</p>
    </a>

    <!-- 比較功能 (暫時停用，等待實作) -->
    <div
      class="flex flex-1 flex-col items-center justify-end gap-1 rounded-full text-[#b0b9be] cursor-not-allowed"
      title="比較功能即將推出"
    >
      <div class="text-[#b0b9be] flex h-8 items-center justify-center">
        <!-- Compare Icon -->
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24">
          <path
            style="
              fill: none;
              stroke: currentColor;
              stroke-linecap: round;
              stroke-linejoin: round;
              stroke-width: 2;
            "
            d="M9 12l2 2 4-4"
          ></path>
          <path
            style="
              fill: none;
              stroke: currentColor;
              stroke-linecap: round;
              stroke-linejoin: round;
              stroke-width: 2;
            "
            d="M21 12c.552 0 1-.448 1-1V5c0-.552-.448-1-1-1h-6c-.552 0-1 .448-1 1v6c0 .552.448 1 1 1h6zM10 12c.552 0 1-.448 1-1V5c0-.552-.448-1-1-1H4c-.552 0-1 .448-1 1v6c0 .552.448 1 1 1h6zM21 20c.552 0 1-.448 1-1v-6c0-.552-.448-1-1-1h-6c-.552 0-1 .448-1 1v6c0 .552.448 1 1 1h6zM10 20c.552 0 1-.448 1-1v-6c0-.552-.448-1-1-1H4c-.552 0-1 .448-1 1v6c0 .552.448 1 1 1h6z"
          ></path>
        </svg>
      </div>
      <p class="text-[#b0b9be] text-xs font-medium leading-normal tracking-[0.015em]">比較</p>
    </div>

    <!-- 新增魚類功能 -->
    <a
      class="flex flex-1 flex-col items-center justify-end gap-1 rounded-full text-[#0e171b] hover:text-green-600 transition-colors"
      href="/fish/create"
    >
      <div
        class="text-[#0e171b] hover:text-green-600 flex h-8 items-center justify-center transition-colors"
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
      </div>
      <p class="text-[#0e171b] text-xs font-medium leading-normal tracking-[0.015em]">新增魚類</p>
    </a>
  </nav>
</template>

<script setup>
// 這個元件專門用於首頁的底部導航
// 包含三個主要功能：搜尋、比較、新增魚類
</script>

<template>
  <!--
    蘭嶼六部落地圖（參照國土署截圖手繪）
    島嶼呈 NW 寬、SE 細長尾部的形狀
    主要分界線從北往南（x≈200），左側 朗島/椰油/漁人，右側 東清/野銀，紅頭橫跨最南
    可點選：iraraley (朗島)、imowrod (紅頭)
  -->
  <div class="w-full max-w-xs">
    <svg
      viewBox="0 0 400 460"
      class="w-full drop-shadow-sm"
      aria-label="蘭嶼六部落地圖"
      role="img"
    >
      <!-- 海洋背景 -->
      <rect width="400" height="460" fill="#bfdbfe" rx="10" />

      <!-- 島嶼底圖（白底，藍色海岸線） -->
      <path
        d="M 50,50 L 100,12 L 210,8 L 310,50 L 355,125
           L 325,178 L 348,220
           L 390,318 L 382,368 L 338,442 L 288,445
           L 260,442 L 242,430 L 158,408 L 95,388 L 45,335
           L 28,285 L 30,262 L 5,228 L 28,185 L 30,175
           L 48,105 Z"
        fill="#f0fdf4"
        stroke="none"
      />

      <!-- 朗島 (iraraley) — 北，可點選 -->
      <polygon
        data-tribe="iraraley"
        points="30,175 48,105 50,50 100,12 210,8 198,170"
        :fill="activeFill"
        :stroke="borderColor"
        stroke-width="1"
        class="cursor-pointer transition-all duration-150 hover:brightness-110 focus:outline-none"
        tabindex="0"
        role="button"
        aria-label="朗島部落"
        @click="emit('tribe-click', 'iraraley')"
        @keydown.enter="emit('tribe-click', 'iraraley')"
        @keydown.space.prevent="emit('tribe-click', 'iraraley')"
      />

      <!-- 東清 (yayo) — 東北，禁用 -->
      <polygon
        data-tribe="yayo"
        points="210,8 310,50 355,125 325,178 348,220 275,250 198,170"
        :fill="disabledFill"
        :stroke="borderColor"
        stroke-width="1"
        class="cursor-not-allowed"
        aria-disabled="true"
      />

      <!-- 椰油 (ivalino) — 西側含左突出部，禁用 -->
      <polygon
        data-tribe="ivalino"
        points="198,170 30,175 28,185 5,228 8,248 5,262 28,270 28,285 198,275"
        :fill="disabledFill"
        :stroke="borderColor"
        stroke-width="1"
        class="cursor-not-allowed"
        aria-disabled="true"
      />

      <!-- 漁人 (iranmeilek) — 中央，禁用 -->
      <polygon
        data-tribe="iranmeilek"
        points="198,275 28,285 45,335 95,360 198,340"
        :fill="disabledFill"
        :stroke="borderColor"
        stroke-width="1"
        class="cursor-not-allowed"
        aria-disabled="true"
      />

      <!-- 紅頭 (imowrod) — 南，可點選 -->
      <polygon
        data-tribe="imowrod"
        points="45,335 95,360 95,388 158,408 220,435 260,442 198,340"
        :fill="activeFill"
        :stroke="borderColor"
        stroke-width="1"
        class="cursor-pointer transition-all duration-150 hover:brightness-110 focus:outline-none"
        tabindex="0"
        role="button"
        aria-label="紅頭部落"
        @click="emit('tribe-click', 'imowrod')"
        @keydown.enter="emit('tribe-click', 'imowrod')"
        @keydown.space.prevent="emit('tribe-click', 'imowrod')"
      />

      <!-- 野銀 (iratay) — 東南細長尾部，禁用 -->
      <polygon
        data-tribe="iratay"
        points="275,250 348,220 390,318 382,368 338,442 288,445 260,442 198,340 198,275"
        :fill="disabledFill"
        :stroke="borderColor"
        stroke-width="1"
        class="cursor-not-allowed"
        aria-disabled="true"
      />

      <!-- 海岸線（覆蓋最上層） -->
      <path
        d="M 50,50 L 100,12 L 210,8 L 310,50 L 355,125
           L 325,178 L 348,220
           L 390,318 L 382,368 L 338,442 L 288,445
           L 260,442 L 242,430 L 158,408 L 95,388 L 45,335
           L 28,285 L 30,262 L 5,228 L 28,185 L 30,175
           L 48,105 Z"
        fill="none"
        stroke="#1e40af"
        stroke-width="2"
        pointer-events="none"
      />

      <!-- 部落標籤 -->
      <!-- 朗島 centroid ≈ (106, 86) -->
      <text x="108" y="82" text-anchor="middle" font-size="12" font-weight="700" fill="white" pointer-events="none">朗島</text>
      <text x="108" y="97" text-anchor="middle" font-size="9" fill="#ccfbf1" pointer-events="none">iraraley</text>

      <!-- 東清 centroid ≈ (278, 142) -->
      <text x="285" y="138" text-anchor="middle" font-size="12" font-weight="700" fill="#374151" pointer-events="none">東清</text>
      <text x="285" y="153" text-anchor="middle" font-size="9" fill="#6b7280" pointer-events="none">yayo</text>

      <!-- 椰油 — 避開凸起，置於主體中上 -->
      <text x="100" y="215" text-anchor="middle" font-size="12" font-weight="700" fill="#374151" pointer-events="none">椰油</text>
      <text x="100" y="230" text-anchor="middle" font-size="9" fill="#6b7280" pointer-events="none">ivalino</text>

      <!-- 漁人 centroid ≈ (113, 319) -->
      <text x="113" y="315" text-anchor="middle" font-size="12" font-weight="700" fill="#374151" pointer-events="none">漁人</text>
      <text x="113" y="330" text-anchor="middle" font-size="9" fill="#6b7280" pointer-events="none">iranmeilek</text>

      <!-- 紅頭 centroid ≈ (163, 393) -->
      <text x="163" y="390" text-anchor="middle" font-size="12" font-weight="700" fill="white" pointer-events="none">紅頭</text>
      <text x="163" y="405" text-anchor="middle" font-size="9" fill="#ccfbf1" pointer-events="none">imowrod</text>

      <!-- 野銀 centroid ≈ (300, 340) -->
      <text x="305" y="338" text-anchor="middle" font-size="12" font-weight="700" fill="#374151" pointer-events="none">野銀</text>
      <text x="305" y="353" text-anchor="middle" font-size="9" fill="#6b7280" pointer-events="none">iratay</text>
    </svg>

    <p class="mt-2 text-center text-xs text-gray-400">
      綠色區域可點選，查看該部落的魚類紀錄
    </p>
  </div>
</template>

<script setup>
const emit = defineEmits(['tribe-click'])

const activeFill = '#0d9488'
const disabledFill = '#9ca3af'
const borderColor = '#e5e7eb'
</script>

<style scoped>
polygon:focus {
  outline: none;
}
polygon[data-tribe="iraraley"]:focus,
polygon[data-tribe="imowrod"]:focus {
  stroke: #1d4ed8;
  stroke-width: 2.5;
}
</style>

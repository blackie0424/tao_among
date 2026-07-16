<template>
  <!--
    蘭嶼六部落近似邊界地圖（手繪多邊形，依部落主體性分區）
    六部落位置：朗島(N)、東清(NE)、野銀(SE)、紅頭(S)、漁人(SW)、椰油(W)
    可點選：iraraley (朗島)、imowrod (紅頭)
    其餘四個部落禁用
  -->
  <div class="w-full max-w-sm">
    <svg
      viewBox="0 0 360 410"
      class="w-full drop-shadow-sm"
      aria-label="蘭嶼六部落地圖"
      role="img"
    >
      <!-- 海洋背景 -->
      <rect width="360" height="410" fill="#dbeafe" rx="12" />

      <!-- 六部落多邊形（以島嶼中心點 195,210 為匯集點） -->

      <!-- 朗島 (iraraley) — 北，可點選 -->
      <polygon
        data-tribe="iraraley"
        points="105,62 180,15 225,28 195,210"
        :fill="activeFill"
        :stroke="activeStroke"
        stroke-width="1.5"
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
        points="225,28 270,50 330,115 328,185 195,210"
        :fill="disabledFill"
        :stroke="disabledStroke"
        stroke-width="1.5"
        class="cursor-not-allowed opacity-80"
        aria-disabled="true"
      />

      <!-- 野銀 (iratay) — 東南，禁用 -->
      <polygon
        data-tribe="iratay"
        points="328,185 315,295 240,368 195,210"
        :fill="disabledFill"
        :stroke="disabledStroke"
        stroke-width="1.5"
        class="cursor-not-allowed opacity-80"
        aria-disabled="true"
      />

      <!-- 紅頭 (imowrod) — 南，可點選 -->
      <polygon
        data-tribe="imowrod"
        points="240,368 200,390 150,375 110,348 195,210"
        :fill="activeFill"
        :stroke="activeStroke"
        stroke-width="1.5"
        class="cursor-pointer transition-all duration-150 hover:brightness-110 focus:outline-none"
        tabindex="0"
        role="button"
        aria-label="紅頭部落"
        @click="emit('tribe-click', 'imowrod')"
        @keydown.enter="emit('tribe-click', 'imowrod')"
        @keydown.space.prevent="emit('tribe-click', 'imowrod')"
      />

      <!-- 漁人 (iranmeilek) — 西南，禁用 -->
      <polygon
        data-tribe="iranmeilek"
        points="110,348 80,295 50,200 195,210"
        :fill="disabledFill"
        :stroke="disabledStroke"
        stroke-width="1.5"
        class="cursor-not-allowed opacity-80"
        aria-disabled="true"
      />

      <!-- 椰油 (ivalino) — 西，禁用 -->
      <polygon
        data-tribe="ivalino"
        points="50,200 45,170 75,95 105,62 195,210"
        :fill="disabledFill"
        :stroke="disabledStroke"
        stroke-width="1.5"
        class="cursor-not-allowed opacity-80"
        aria-disabled="true"
      />

      <!-- 部落標籤 -->
      <!-- 朗島 (176, 79) -->
      <text x="155" y="77" text-anchor="middle" font-size="11" font-weight="700" fill="white" pointer-events="none">朗島</text>
      <text x="155" y="91" text-anchor="middle" font-size="8.5" fill="#ccfbf1" pointer-events="none">iraraley</text>

      <!-- 東清 (270, 118) -->
      <text x="275" y="116" text-anchor="middle" font-size="11" font-weight="700" fill="#374151" pointer-events="none">東清</text>
      <text x="275" y="130" text-anchor="middle" font-size="8.5" fill="#6b7280" pointer-events="none">yayo</text>

      <!-- 野銀 (270, 265) -->
      <text x="278" y="263" text-anchor="middle" font-size="11" font-weight="700" fill="#374151" pointer-events="none">野銀</text>
      <text x="278" y="277" text-anchor="middle" font-size="8.5" fill="#6b7280" pointer-events="none">iratay</text>

      <!-- 紅頭 (179, 358) -->
      <text x="179" y="352" text-anchor="middle" font-size="11" font-weight="700" fill="white" pointer-events="none">紅頭</text>
      <text x="179" y="366" text-anchor="middle" font-size="8.5" fill="#ccfbf1" pointer-events="none">imowrod</text>

      <!-- 漁人 (109, 263) -->
      <text x="105" y="261" text-anchor="middle" font-size="11" font-weight="700" fill="#374151" pointer-events="none">漁人</text>
      <text x="105" y="275" text-anchor="middle" font-size="8.5" fill="#6b7280" pointer-events="none">iranmeilek</text>

      <!-- 椰油 (94, 147) -->
      <text x="90" y="145" text-anchor="middle" font-size="11" font-weight="700" fill="#374151" pointer-events="none">椰油</text>
      <text x="90" y="159" text-anchor="middle" font-size="8.5" fill="#6b7280" pointer-events="none">ivalino</text>

      <!-- 島名 -->
      <text x="195" y="210" text-anchor="middle" font-size="10" fill="white" opacity="0.6" pointer-events="none">蘭嶼</text>
    </svg>

    <p class="mt-2 text-center text-xs text-gray-400">
      綠色區域可點選，查看該部落的魚類紀錄
    </p>
  </div>
</template>

<script setup>
const emit = defineEmits(['tribe-click'])

const activeFill = '#0d9488'
const activeStroke = '#0f766e'
const disabledFill = '#9ca3af'
const disabledStroke = '#6b7280'
</script>

<style scoped>
polygon:focus {
  outline: none;
}
polygon[data-tribe="iraraley"]:focus,
polygon[data-tribe="imowrod"]:focus {
  stroke: #1d4ed8;
  stroke-width: 3;
}
</style>

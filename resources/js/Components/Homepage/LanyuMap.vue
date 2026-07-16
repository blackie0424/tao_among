<template>
  <!--
    蘭嶼六部落近似邊界地圖
    參照國土署截圖手繪，六個地籍段：
    朗島(VA0022, N)、東清(VA0024, NE)、椰油(VA0021, W含左突出部)
    漁人(VA0020, 中)、紅頭(VA0023, S)、野銀(VA0019, SE大半島)
    可點選：iraraley (朗島)、imowrod (紅頭)
  -->
  <div class="w-full max-w-sm">
    <svg
      viewBox="0 0 415 375"
      class="w-full drop-shadow-sm"
      aria-label="蘭嶼六部落地圖"
      role="img"
    >
      <!-- 海洋背景 -->
      <rect width="415" height="375" fill="#bfdbfe" rx="10" />

      <!-- 島嶼底圖輪廓（淺色填充，白色海岸線） -->
      <path
        d="M 18,132 L 50,95 L 95,8 L 175,3 L 188,3
           L 305,38 L 360,105 L 330,158 L 352,190
           L 405,280 L 400,315 L 340,358 L 285,352
           L 238,343 L 132,325 L 50,288
           L 24,200 L 4,168 Z"
        fill="#f0fdf4"
        stroke="#93c5fd"
        stroke-width="2"
      />

      <!-- 朗島 (iraraley) — 北，可點選 -->
      <polygon
        data-tribe="iraraley"
        points="18,132 50,95 95,8 175,3 188,3 198,160"
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
        points="188,3 305,38 360,105 330,158 352,190 275,250 198,160"
        :fill="disabledFill"
        :stroke="borderColor"
        stroke-width="1"
        class="cursor-not-allowed"
        aria-disabled="true"
      />

      <!-- 椰油 (ivalino) — 西（含左突出部），禁用 -->
      <polygon
        data-tribe="ivalino"
        points="18,132 198,160 24,200 4,168"
        :fill="disabledFill"
        :stroke="borderColor"
        stroke-width="1"
        class="cursor-not-allowed"
        aria-disabled="true"
      />

      <!-- 漁人 (iranmeilek) — 中央，禁用 -->
      <polygon
        data-tribe="iranmeilek"
        points="24,200 198,160 275,250 182,275 50,288"
        :fill="disabledFill"
        :stroke="borderColor"
        stroke-width="1"
        class="cursor-not-allowed"
        aria-disabled="true"
      />

      <!-- 紅頭 (imowrod) — 南，可點選 -->
      <polygon
        data-tribe="imowrod"
        points="50,288 182,275 275,250 285,352 238,343 132,325"
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

      <!-- 野銀 (iratay) — 東南大半島，禁用 -->
      <polygon
        data-tribe="iratay"
        points="275,250 352,190 405,280 400,315 340,358 285,352"
        :fill="disabledFill"
        :stroke="borderColor"
        stroke-width="1"
        class="cursor-not-allowed"
        aria-disabled="true"
      />

      <!-- 部落標籤 -->
      <!-- 朗島 centroid ≈ (121, 67) -->
      <text x="115" y="65" text-anchor="middle" font-size="12" font-weight="700" fill="white" pointer-events="none">朗島</text>
      <text x="115" y="80" text-anchor="middle" font-size="9" fill="#ccfbf1" pointer-events="none">iraraley</text>

      <!-- 東清 centroid ≈ (287, 129) -->
      <text x="295" y="127" text-anchor="middle" font-size="12" font-weight="700" fill="#374151" pointer-events="none">東清</text>
      <text x="295" y="142" text-anchor="middle" font-size="9" fill="#6b7280" pointer-events="none">yayo</text>

      <!-- 椰油 centroid ≈ (61, 165) — 往右移避免重疊 -->
      <text x="78" y="165" text-anchor="middle" font-size="12" font-weight="700" fill="#374151" pointer-events="none">椰油</text>
      <text x="78" y="180" text-anchor="middle" font-size="9" fill="#6b7280" pointer-events="none">ivalino</text>

      <!-- 漁人 centroid ≈ (146, 235) -->
      <text x="148" y="233" text-anchor="middle" font-size="12" font-weight="700" fill="#374151" pointer-events="none">漁人</text>
      <text x="148" y="248" text-anchor="middle" font-size="9" fill="#6b7280" pointer-events="none">iranmeilek</text>

      <!-- 紅頭 centroid ≈ (194, 306) -->
      <text x="185" y="308" text-anchor="middle" font-size="12" font-weight="700" fill="white" pointer-events="none">紅頭</text>
      <text x="185" y="323" text-anchor="middle" font-size="9" fill="#ccfbf1" pointer-events="none">imowrod</text>

      <!-- 野銀 centroid ≈ (343, 291) -->
      <text x="348" y="291" text-anchor="middle" font-size="12" font-weight="700" fill="#374151" pointer-events="none">野銀</text>
      <text x="348" y="306" text-anchor="middle" font-size="9" fill="#6b7280" pointer-events="none">iratay</text>

      <!-- 海岸線（覆蓋在最上層，確保島嶼邊框清晰） -->
      <path
        d="M 18,132 L 50,95 L 95,8 L 175,3 L 188,3
           L 305,38 L 360,105 L 330,158 L 352,190
           L 405,280 L 400,315 L 340,358 L 285,352
           L 238,343 L 132,325 L 50,288
           L 24,200 L 4,168 Z"
        fill="none"
        stroke="#1e40af"
        stroke-width="1.8"
        pointer-events="none"
      />
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

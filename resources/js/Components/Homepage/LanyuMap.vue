<template>
  <!--
    蘭嶼六部落示意地圖
    可點選部落：iraraley (朗島)、imowrod (紅頭)
    其餘部落顯示為禁用狀態
  -->
  <svg
    viewBox="0 0 360 380"
    class="w-full max-w-sm"
    aria-label="蘭嶼部落地圖"
    role="img"
  >
    <!-- 島嶼輪廓 -->
    <ellipse cx="180" cy="190" rx="150" ry="160" fill="#e8f4e8" stroke="#9ca3af" stroke-width="2" />

    <!-- ivalino 椰油（西北） -->
    <g
      data-tribe="ivalino"
      class="tribe-disabled"
      aria-disabled="true"
    >
      <circle cx="105" cy="110" r="32" fill="#d1d5db" stroke="#9ca3af" stroke-width="1.5" />
      <text x="105" y="106" text-anchor="middle" font-size="11" font-weight="600" fill="#6b7280">椰油</text>
      <text x="105" y="120" text-anchor="middle" font-size="9" fill="#9ca3af">ivalino</text>
    </g>

    <!-- iranmeilek 漁人（西） -->
    <g
      data-tribe="iranmeilek"
      class="tribe-disabled"
      aria-disabled="true"
    >
      <circle cx="75" cy="200" r="32" fill="#d1d5db" stroke="#9ca3af" stroke-width="1.5" />
      <text x="75" y="196" text-anchor="middle" font-size="11" font-weight="600" fill="#6b7280">漁人</text>
      <text x="75" y="210" text-anchor="middle" font-size="9" fill="#9ca3af">iranmeilek</text>
    </g>

    <!-- imowrod 紅頭（西南，可點選） -->
    <g
      data-tribe="imowrod"
      class="tribe-active"
      tabindex="0"
      role="button"
      aria-label="紅頭部落"
      @click="emit('tribe-click', 'imowrod')"
      @keydown.enter="emit('tribe-click', 'imowrod')"
      @keydown.space.prevent="emit('tribe-click', 'imowrod')"
    >
      <circle cx="115" cy="295" r="34" fill="#0d9488" stroke="#0f766e" stroke-width="2" />
      <text x="115" y="291" text-anchor="middle" font-size="11" font-weight="700" fill="white">紅頭</text>
      <text x="115" y="305" text-anchor="middle" font-size="9" fill="#ccfbf1">imowrod</text>
    </g>

    <!-- iratay 野銀（東南） -->
    <g
      data-tribe="iratay"
      class="tribe-disabled"
      aria-disabled="true"
    >
      <circle cx="255" cy="295" r="32" fill="#d1d5db" stroke="#9ca3af" stroke-width="1.5" />
      <text x="255" y="291" text-anchor="middle" font-size="11" font-weight="600" fill="#6b7280">野銀</text>
      <text x="255" y="305" text-anchor="middle" font-size="9" fill="#9ca3af">iratay</text>
    </g>

    <!-- yayo 東清（東） -->
    <g
      data-tribe="yayo"
      class="tribe-disabled"
      aria-disabled="true"
    >
      <circle cx="285" cy="200" r="32" fill="#d1d5db" stroke="#9ca3af" stroke-width="1.5" />
      <text x="285" y="196" text-anchor="middle" font-size="11" font-weight="600" fill="#6b7280">東清</text>
      <text x="285" y="210" text-anchor="middle" font-size="9" fill="#9ca3af">yayo</text>
    </g>

    <!-- iraraley 朗島（東北，可點選） -->
    <g
      data-tribe="iraraley"
      class="tribe-active"
      tabindex="0"
      role="button"
      aria-label="朗島部落"
      @click="emit('tribe-click', 'iraraley')"
      @keydown.enter="emit('tribe-click', 'iraraley')"
      @keydown.space.prevent="emit('tribe-click', 'iraraley')"
    >
      <circle cx="255" cy="110" r="34" fill="#0d9488" stroke="#0f766e" stroke-width="2" />
      <text x="255" y="106" text-anchor="middle" font-size="11" font-weight="700" fill="white">朗島</text>
      <text x="255" y="120" text-anchor="middle" font-size="9" fill="#ccfbf1">iraraley</text>
    </g>

    <!-- 中央島名 -->
    <text x="180" y="186" text-anchor="middle" font-size="15" font-weight="700" fill="#374151">蘭嶼</text>
    <text x="180" y="204" text-anchor="middle" font-size="11" fill="#6b7280">Ponso no Tao</text>
  </svg>
</template>

<script setup>
const emit = defineEmits(['tribe-click'])
</script>

<style scoped>
.tribe-active {
  cursor: pointer;
}
.tribe-active circle {
  transition: filter 0.15s ease;
}
.tribe-active:hover circle,
.tribe-active:focus circle {
  filter: brightness(1.15);
}
.tribe-active:focus {
  outline: none;
}
.tribe-active:focus circle {
  stroke: #1d4ed8;
  stroke-width: 3;
}
.tribe-disabled {
  cursor: not-allowed;
  opacity: 0.7;
}
</style>

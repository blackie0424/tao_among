<template>
  <nav
    class="fixed bottom-0 left-0 right-0 z-50 border-t border-[#e7eff3] bg-slate-50 px-4 pb-3 pt-2 flex gap-2"
  >
    <a
      class="flex flex-1 flex-col items-center justify-end gap-1 rounded-full text-[#0e171b]"
      href="/fishs"
    >
      <div class="text-[#0e171b] flex h-8 items-center justify-center">
        <!-- Home Icon -->
        <svg
          data-icon-name="fish"
          data-style="flat-line"
          icon_origin_id="19639"
          viewBox="0 0 24 24"
          xmlns="http://www.w3.org/2000/svg"
          data-name="Flat Line"
          id="fish"
          class="icon flat-line"
          width="24"
          height="24"
        >
          <path
            style="fill: none; stroke: rgb(0, 0, 0); stroke-width: 2"
            d="M14.5,7A9.93,9.93,0,0,0,8.1,9.74,4.83,4.83,0,0,0,6.77,8.55,3.65,3.65,0,0,0,3,8.51,4.14,4.14,0,0,0,5.08,12,4.14,4.14,0,0,0,3,15.49a3.65,3.65,0,0,0,3.77,0A4.83,4.83,0,0,0,8.1,14.26,9.93,9.93,0,0,0,14.5,17c5,0,6.5-5,6.5-5S19.5,7,14.5,7Z"
            id="secondary"
          ></path>
          <path
            style="
              fill: none;
              stroke: rgb(0, 0, 0);
              stroke-linecap: round;
              stroke-linejoin: round;
              stroke-width: 2;
            "
            d="M14.5,7A9.93,9.93,0,0,0,8.1,9.74,4.83,4.83,0,0,0,6.77,8.55,3.65,3.65,0,0,0,3,8.51,4.14,4.14,0,0,0,5.08,12,4.14,4.14,0,0,0,3,15.49a3.65,3.65,0,0,0,3.77,0A4.83,4.83,0,0,0,8.1,14.26,9.93,9.93,0,0,0,14.5,17c5,0,6.5-5,6.5-5S19.5,7,14.5,7Z"
            id="primary"
          ></path>
        </svg>
      </div>
      <p class="text-[#0e171b] text-xs font-medium leading-normal tracking-[0.015em]">圖鑑</p>
    </a>

    <!-- 魚類基本資料連結 -->
    <a
      v-if="fishBasicInfo"
      :class="[
        'flex flex-1 flex-col items-center justify-end gap-1',
        currentPage === 'fishBasicInfo' ? 'text-[#0e171b]' : 'text-[#4d7f99]',
      ]"
      :href="fishBasicInfo"
    >
      <div
        :class="[
          'flex h-8 items-center justify-center',
          currentPage === 'fishBasicInfo' ? 'text-[#0e171b]' : 'text-[#4d7f99]',
        ]"
      >
        <!-- Fish Info Icon -->
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24">
          <circle
            :style="{
              fill: currentPage === 'fishBasicInfo' ? 'rgb(30, 85, 246)' : 'none',
              stroke: 'rgb(0, 0, 0)',
              'stroke-linecap': 'round',
              'stroke-linejoin': 'round',
              'stroke-width': '2',
            }"
            cx="12"
            cy="12"
            r="10"
          ></circle>
          <path
            :style="{
              fill: 'none',
              stroke: currentPage === 'fishBasicInfo' ? 'rgb(255, 255, 255)' : 'rgb(0, 0, 0)',
              'stroke-linecap': 'round',
              'stroke-linejoin': 'round',
              'stroke-width': '2',
            }"
            d="M12 16v-4"
          ></path>
          <circle
            :style="{
              fill: currentPage === 'fishBasicInfo' ? 'rgb(255, 255, 255)' : 'rgb(0, 0, 0)',
              stroke: currentPage === 'fishBasicInfo' ? 'rgb(255, 255, 255)' : 'rgb(0, 0, 0)',
              'stroke-width': '2',
            }"
            cx="12"
            cy="8"
            r="1"
          ></circle>
        </svg>
      </div>
      <p class="text-[#0e171b] text-xs font-medium leading-normal tracking-[0.015em]">基本資料</p>
    </a>

    <!-- 捕獲紀錄連結 -->
    <a
      v-if="captureRecords"
      :class="[
        'flex flex-1 flex-col items-center justify-end gap-1',
        currentPage === 'captureRecords' ? 'text-[#0e171b]' : 'text-[#4d7f99]',
      ]"
      :href="captureRecords"
    >
      <div
        :class="[
          'flex h-8 items-center justify-center',
          currentPage === 'captureRecords' ? 'text-[#0e171b]' : 'text-[#4d7f99]',
        ]"
      >
        <!-- Camera Icon -->
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24">
          <path
            v-if="currentPage === 'captureRecords'"
            style="fill: rgb(30, 85, 246); stroke-width: 2"
            d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
          ></path>
          <path
            style="
              fill: none;
              stroke: rgb(0, 0, 0);
              stroke-linecap: round;
              stroke-linejoin: round;
              stroke-width: 2;
            "
            d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
          ></path>
          <circle
            style="
              fill: none;
              stroke: rgb(0, 0, 0);
              stroke-linecap: round;
              stroke-linejoin: round;
              stroke-width: 2;
            "
            cx="12"
            cy="13"
            r="3"
          ></circle>
        </svg>
      </div>
      <p class="text-[#0e171b] text-xs font-medium leading-normal tracking-[0.015em]">捕獲紀錄</p>
    </a>

    <!-- 進階知識連結 -->
    <a
      v-if="knowledgeList"
      :class="[
        'flex flex-1 flex-col items-center justify-end gap-1',
        currentPage === 'knowledgeList' ? 'text-[#0e171b]' : 'text-[#4d7f99]',
      ]"
      :href="knowledgeList"
    >
      <div
        :class="[
          'flex h-8 items-center justify-center',
          currentPage === 'knowledgeList' ? 'text-[#0e171b]' : 'text-[#4d7f99]',
        ]"
      >
        <!-- Knowledge/Book Icon -->
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24">
          <path
            v-if="currentPage === 'knowledgeList'"
            style="fill: rgb(30, 85, 246); stroke-width: 2"
            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
          ></path>
          <path
            style="
              fill: none;
              stroke: rgb(0, 0, 0);
              stroke-linecap: round;
              stroke-linejoin: round;
              stroke-width: 2;
            "
            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
          ></path>
        </svg>
      </div>
      <p class="text-[#0e171b] text-xs font-medium leading-normal tracking-[0.015em]">進階知識</p>
    </a>

    <!-- 發音列表連結 -->
    <a
      v-if="audioList"
      :class="[
        'flex flex-1 flex-col items-center justify-end gap-1',
        currentPage === 'audioList' ? 'text-[#0e171b]' : 'text-[#4d7f99]',
      ]"
      :href="audioList"
    >
      <div
        :class="[
          'flex h-8 items-center justify-center',
          currentPage === 'audioList' ? 'text-[#0e171b]' : 'text-[#4d7f99]',
        ]"
      >
        <!-- Volume/Audio Icon -->
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24">
          <path
            v-if="currentPage === 'audioList'"
            style="fill: rgb(30, 85, 246); stroke-width: 2"
            d="M11 5L6 9H2v6h4l5 4V5zM19.07 4.93a10 10 0 010 14.14M15.54 8.46a5 5 0 010 7.07"
          ></path>
          <path
            style="
              fill: none;
              stroke: rgb(0, 0, 0);
              stroke-linecap: round;
              stroke-linejoin: round;
              stroke-width: 2;
            "
            d="M11 5L6 9H2v6h4l5 4V5zM19.07 4.93a10 10 0 010 14.14M15.54 8.46a5 5 0 010 7.07"
          ></path>
        </svg>
      </div>
      <p class="text-[#0e171b] text-xs font-medium leading-normal tracking-[0.015em]">發音列表</p>
    </a>

    <a class="flex flex-1 flex-col items-center justify-end gap-1 text-[#4d7f99]" :href="to">
      <div class="text-[#4d7f99] flex h-8 items-center justify-center">
        <!-- Add/New Icon -->
        <svg
          data-icon-name="add-file-5"
          data-style="line"
          icon_origin_id="18134"
          viewBox="0 0 24 24"
          xmlns="http://www.w3.org/2000/svg"
          id="add-file-5"
          class="icon line"
          width="24"
          height="24"
        >
          <path
            style="
              fill: none;
              stroke: rgb(0, 0, 0);
              stroke-linecap: round;
              stroke-linejoin: round;
              stroke-width: 2;
            "
            d="M18,13V5L16,3H5A1,1,0,0,0,4,4V20a1,1,0,0,0,1,1h7"
            id="primary"
          ></path>
          <path
            style="
              fill: none;
              stroke: rgb(0, 0, 0);
              stroke-linecap: round;
              stroke-linejoin: round;
              stroke-width: 2;
            "
            d="M16,19h4m-2-2v4M16,3V5h2ZM8,13h6m0-4H8"
            data-name="primary"
            id="primary-2"
          ></path>
        </svg>
      </div>
      <p class="text-[#0e171b] text-xs font-medium leading-normal tracking-[0.015em]">
        {{ label }}
      </p>
    </a>
    <a class="flex flex-1 flex-col items-center justify-end gap-1 text-[#4d7f99]" :href="audio">
      <div class="text-[#b0b9be] flex h-8 items-center justify-center">
        <svg
          data-icon-name="microphone-right"
          data-style="line"
          icon_origin_id="21448"
          viewBox="0 0 24 24"
          xmlns="http://www.w3.org/2000/svg"
          data-name="Flat Line"
          id="microphone-right"
          class="icon line"
          width="24"
          height="24"
        >
          <path
            style="fill: none; stroke: rgb(0, 0, 0); stroke-width: 2; stroke: rgb(0, 0, 0)"
            d="M16.75,11l-7.53,6.7a1,1,0,0,1-1.37,0l-1.5-1.5a1,1,0,0,1,0-1.37L13,7.25Z"
            id="secondary"
          ></path>
          <path
            style="
              fill: none;
              stroke: rgb(0, 0, 0);
              stroke-linecap: round;
              stroke-linejoin: round;
              stroke-width: 2;
            "
            d="M3,21l4.1-4.1M13,7.25l-6.7,7.53a1,1,0,0,0,0,1.37l1.5,1.5a1,1,0,0,0,1.37,0L16.75,11Zm6.82,2.58A4,4,0,0,1,16.73,11L13,7.27a4,4,0,0,1,1.14-3.1,4,4,0,1,1,5.66,5.66ZM17.71,12,12.05,6.29"
            id="primary"
          ></path>
        </svg>
      </div>
      <p class="text-[#0e171b] text-xs font-medium leading-normal tracking-[0.015em]">新增發音</p>
    </a>
  </nav>
</template>

<script setup>
const props = defineProps({
  label: { type: String, default: '新增知識' },
  icon: { type: String, default: '+' },
  to: { type: String, default: '/fishs' },
  audio: { type: String, default: '/' },
  fishBasicInfo: { type: String, default: null },
  tribalKnowledge: { type: String, default: null },
  captureRecords: { type: String, default: null },
  knowledgeList: { type: String, default: null },
  audioList: { type: String, default: null },
  currentPage: { type: String, default: null }, // 'fishBasicInfo', 'tribalKnowledge', 'captureRecords', 'knowledgeList', 'audioList' 等
})
</script>

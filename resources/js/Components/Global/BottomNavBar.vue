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

    <!-- 地方知識連結 -->
    <a
      v-if="tribalKnowledge"
      :class="[
        'flex flex-1 flex-col items-center justify-end gap-1',
        currentPage === 'tribalKnowledge' ? 'text-[#0e171b]' : 'text-[#4d7f99]',
      ]"
      :href="tribalKnowledge"
    >
      <div
        :class="[
          'flex h-8 items-center justify-center',
          currentPage === 'tribalKnowledge' ? 'text-[#0e171b]' : 'text-[#4d7f99]',
        ]"
      >
        <!-- Tribal Knowledge Icon -->
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24">
          <path
            v-if="currentPage === 'tribalKnowledge'"
            style="fill: rgb(30, 85, 246); stroke-width: 2"
            d="M12 2L2 7v10c0 5.55 3.84 9.74 9 11 5.16-1.26 9-5.45 9-11V7l-10-5z"
          ></path>
          <path
            style="
              fill: none;
              stroke: rgb(0, 0, 0);
              stroke-linecap: round;
              stroke-linejoin: round;
              stroke-width: 2;
            "
            d="M12 2L2 7v10c0 5.55 3.84 9.74 9 11 5.16-1.26 9-5.45 9-11V7l-10-5z"
          ></path>
          <path
            style="
              fill: none;
              stroke: rgb(0, 0, 0);
              stroke-linecap: round;
              stroke-linejoin: round;
              stroke-width: 2;
            "
            d="M9 12l2 2 4-4"
          ></path>
        </svg>
      </div>
      <p class="text-[#0e171b] text-xs font-medium leading-normal tracking-[0.015em]">地方知識</p>
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
            style="fill: rgb(246, 146, 30); stroke-width: 2; stroke: rgb(0, 0, 0)"
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
  tribalKnowledge: { type: String, default: null },
  captureRecords: { type: String, default: null },
  currentPage: { type: String, default: null }, // 'tribalKnowledge', 'captureRecords' 等
})
</script>

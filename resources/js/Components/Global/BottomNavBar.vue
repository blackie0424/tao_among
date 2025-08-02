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
          xmlns="http://www.w3.org/2000/svg"
          width="24px"
          height="24px"
          fill="currentColor"
          viewBox="0 0 256 256"
        >
          <path
            d="M224,115.55V208a16,16,0,0,1-16,16H168a16,16,0,0,1-16-16V168a8,8,0,0,0-8-8H112a8,8,0,0,0-8,8v40a16,16,0,0,1-16,16H48a16,16,0,0,1-16-16V115.55a16,16,0,0,1,5.17-11.78l80-75.48.11-.11a16,16,0,0,1,21.53,0,1.14,1.14,0,0,0,.11.11l80,75.48A16,16,0,0,1,224,115.55Z"
          ></path>
        </svg>
      </div>
      <p class="text-[#0e171b] text-xs font-medium leading-normal tracking-[0.015em]">圖鑑</p>
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
      <p class="text-[#4d7f99] text-xs font-medium leading-normal tracking-[0.015em]">
        {{ label }}
      </p>
    </a>
    <span
      class="flex flex-1 flex-col items-center justify-end gap-1 text-[#b0b9be] cursor-not-allowed opacity-50"
    >
      <div class="text-[#b0b9be] flex h-8 items-center justify-center">
        <!-- Browse/Search Icon -->
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="24px"
          height="24px"
          fill="currentColor"
          viewBox="0 0 256 256"
        >
          <path
            d="M229.66,218.34l-50.07-50.06a88.11,88.11,0,1,0-11.31,11.31l50.06,50.07a8,8,0,0,0,11.32-11.32ZM40,112a72,72,0,1,1,72,72A72.08,72.08,0,0,1,40,112Z"
          ></path>
        </svg>
      </div>
      <p class="text-[#b0b9be] text-xs font-medium leading-normal tracking-[0.015em]">搜尋</p>
    </span>
    <span
      class="flex flex-1 flex-col items-center justify-end gap-1 text-[#b0b9be] cursor-not-allowed opacity-50"
    >
      <div class="text-[#b0b9be] flex h-8 items-center justify-center">
        <!-- Profile/Settings Icon -->
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="24px"
          height="24px"
          fill="currentColor"
          viewBox="0 0 256 256"
        >
          <path
            d="M230.92,212c-15.23-26.33-38.7-45.21-66.09-54.16a72,72,0,1,0-73.66,0C63.78,166.78,40.31,185.66,25.08,212a8,8,0,1,0,13.85,8c18.84-32.56,52.14-52,89.07-52s70.23,19.44,89.07,52a8,8,0,1,0,13.85-8ZM72,96a56,56,0,1,1,56,56A56.06,56.06,0,0,1,72,96Z"
          ></path>
        </svg>
      </div>
      <p class="text-[#b0b9be] text-xs font-medium leading-normal tracking-[0.015em]">設定</p>
    </span>
  </nav>
</template>

<script setup>
const props = defineProps({
  label: { type: String, default: '新增知識' },
  icon: { type: String, default: '+' },
  to: { type: String, default: '/fishs' },
})
</script>

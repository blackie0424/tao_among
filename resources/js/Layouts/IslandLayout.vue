<template>
  <!-- 魚島自己的版面：不共用 among 的導覽列與頁尾（風格見 design-spec.md「魚島」） -->
  <div class="min-h-screen bg-island-sea text-island-ink">
    <div class="mx-auto w-full max-w-md px-3 pb-8 pt-4">
      <header v-if="$slots.header" class="flex items-center justify-between gap-3 px-1 pb-3">
        <slot name="header" />
      </header>
      <main>
        <slot />
      </main>
    </div>
  </div>
</template>

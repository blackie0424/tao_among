<template>
  <button
    type="button"
    data-testid="floating-view"
    aria-label="換看法"
    class="flex h-14 w-14 items-center justify-center rounded-full border-2 border-brand bg-paper-raised text-brand hover:bg-rule focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/30"
  >
    <svg
      aria-hidden="true"
      class="h-6 w-6"
      fill="none"
      stroke="currentColor"
      stroke-width="2"
      viewBox="0 0 24 24"
    >
      <rect x="4" y="4" width="6.5" height="6.5" rx="1" />
      <rect x="13.5" y="4" width="6.5" height="6.5" rx="1" />
      <rect x="4" y="13.5" width="6.5" height="6.5" rx="1" />
      <rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1" />
    </svg>
  </button>
</template>

<template>
  <Head title="魚島" />

  <main class="mx-auto min-h-screen w-full max-w-md px-4 py-8">
    <h1 class="text-elder-title font-bold">魚島</h1>
    <dl class="mt-6 space-y-4 text-elder-body">
      <div>
        <dt class="text-elder-aux">你的名字</dt>
        <dd class="text-elder-name font-bold">{{ character.name }}</dd>
      </div>
      <div>
        <dt class="text-elder-aux">你的部落</dt>
        <dd class="text-elder-name font-bold">{{ character.tribe }}</dd>
      </div>
    </dl>
  </main>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'

defineProps({
  character: { type: Object, required: true },
})
</script>

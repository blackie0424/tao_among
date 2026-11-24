<template>
  <Head title="魚島" />

  <IslandLayout>
    <template #header>
      <h1 class="text-elder-name font-extrabold">魚島</h1>
      <p class="rounded-island bg-white px-3 py-1.5 text-elder-aux">{{ character.name }}</p>
    </template>

    <section class="rounded-island bg-white p-4">
      <dl class="space-y-4">
        <div>
          <dt class="text-elder-aux text-island-muted">你的名字</dt>
          <dd class="text-elder-name font-extrabold">{{ character.name }}</dd>
        </div>
        <div>
          <dt class="text-elder-aux text-island-muted">你的部落</dt>
          <dd class="text-elder-name font-extrabold">{{ islandTribeLabel(character.tribe) }}</dd>
        </div>
      </dl>
    </section>
  </IslandLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import IslandLayout from '@/Layouts/IslandLayout.vue'
import { islandTribeLabel } from '@/constants/island'

defineProps({
  character: { type: Object, required: true },
})
</script>

<template>
  <Head title="魚島" />

  <IslandLayout wide>
    <template #header>
      <h1 class="text-elder-name font-extrabold">魚島</h1>
      <p class="rounded-island bg-white px-3 py-1.5 text-elder-aux">{{ character.name }}</p>
    </template>

    <IslandWorld :tribe="current_tribe" />
    <p class="mt-2 px-1 text-elder-aux text-island-muted">用方向鍵走路，或點地面走過去</p>

    <section class="mt-3 rounded-island bg-white p-4">
      <dl class="space-y-3">
        <div>
          <dt class="text-elder-aux text-island-muted">你現在在</dt>
          <dd class="text-elder-name font-extrabold text-island-accent">
            {{ islandTribeLabel(current_tribe) }}
          </dd>
        </div>
        <div>
          <dt class="text-elder-aux text-island-muted">你的部落</dt>
          <dd class="text-elder-body font-bold">{{ islandTribeLabel(character.tribe) }}</dd>
        </div>
      </dl>
    </section>
  </IslandLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import IslandLayout from '@/Layouts/IslandLayout.vue'
import IslandWorld from '@/Components/Island/IslandWorld.vue'
import { islandTribeLabel } from '@/constants/island'

defineProps({
  character: { type: Object, required: true },
  tribes: { type: Array, required: true },
  current_tribe: { type: String, required: true },
})
</script>

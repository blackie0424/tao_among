<template>
  <Head title="魚島" />

  <IslandLayout>
    <template #header>
      <h1 class="text-elder-name font-extrabold">魚島</h1>
      <p class="rounded-island bg-white px-3 py-1.5 text-elder-aux">{{ character.name }}</p>
    </template>

    <IslandMap :tribes="tribes" :current-tribe="current_tribe" @arrive="goToTribe" />
    <p class="mt-2 px-1 text-elder-aux text-island-muted">點部落，角色就會走過去</p>

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
      <Link
        :href="tribeUrl(current_tribe)"
        class="mt-4 flex min-h-touch-primary items-center justify-center rounded-island bg-island-accent px-4 text-elder-name font-bold text-white"
      >
        看這裡的魚
      </Link>
    </section>
  </IslandLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import IslandLayout from '@/Layouts/IslandLayout.vue'
import IslandMap from '@/Components/Island/IslandMap.vue'
import { islandTribeLabel } from '@/constants/island'

defineProps({
  character: { type: Object, required: true },
  tribes: { type: Array, required: true },
  current_tribe: { type: String, required: true },
})

const tribeUrl = (tribe) => `/island/tribes/${tribe}`

const goToTribe = (tribe) => router.visit(tribeUrl(tribe))
</script>

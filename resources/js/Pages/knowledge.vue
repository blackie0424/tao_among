<template>
  <Head :title="`${fish.name}的知識管理`" />
  <div class="container mx-auto p-4 relative">
    <h1>knowledge</h1>

    <BottomNavBar
      :fishBasicInfo="`/fish/${fish.id}`"
      :tribalKnowledge="`/fish/${fish.id}/tribal-classifications`"
      :captureRecords="`/fish/${fish.id}/capture-records`"
      :knowledge="`/fish/${fish.id}/knowledge`"
      :audioList="`/fish/${fish.id}/audio-list`"
      :currentPage="'knowledge'"
    />
  </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import BottomNavBar from '../Components/Global/BottomNavBar.vue'

const props = defineProps({
  fish: Object,
})
</script>

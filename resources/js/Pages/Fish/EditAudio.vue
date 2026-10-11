<template>
  <div class="container mx-auto p-4 relative">
    <Link :href="`/fish/${fish.id}?tab=audio`" class="inline-block mb-4 text-blue-600 underline">取消</Link>
    <EditFishAudioForm
      :audio="audio"
      :fishId="fish.id"
      :fishName="fish.name"
      :fishImage="fish.display_image_url || fish.image_url"
    />
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import EditFishAudioForm from '../../Components/FishAudio/EditFishAudioForm.vue'

defineProps({
  fish: Object,
  audio: Object,
})
</script>

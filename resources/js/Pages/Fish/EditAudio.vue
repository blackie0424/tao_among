<template>
  <div class="container mx-auto p-4 relative">
    <EditFishAudioForm
      :audio="audio"
      :fishId="fish.id"
      :fishName="fish.name"
      :fishImage="fish.display_image_url || fish.image_url"
    />
  </div>
</template>

<script setup>
import EditFishAudioForm from '../../Components/FishAudio/EditFishAudioForm.vue'

defineProps({
  fish: Object,
  audio: Object,
})
</script>

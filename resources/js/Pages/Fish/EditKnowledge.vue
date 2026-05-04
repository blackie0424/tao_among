<template>
  <div class="container mx-auto p-4 relative">
    <EditFishNoteForm
      :note="note"
      :noteTypes="noteTypes"
      :tribes="tribes"
      :fishId="fish.id"
      :fishName="fish.name"
      :fishImage="fish.display_image_url || fish.image_url"
    />
  </div>
</template>

<script setup>
import EditFishNoteForm from '../../Components/FishNote/EditFishNoteForm.vue'

defineProps({
  fish: Object,
  note: Object,
  noteTypes: Array,
  tribes: Array,
})
</script>

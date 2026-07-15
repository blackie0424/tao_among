<template>
  <div class="container mx-auto p-4 relative">
    <FishNoteForm
      :initialData="note"
      :noteTypes="noteTypes"
      :tribes="tribes"
      :fishName="fish.name"
      :fishImage="fish.display_image_url || fish.image_url"
      @submit="onFormSubmit"
      ref="formRef"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { markFishStale } from '@/utils/fishListCache'
import FishNoteForm from '../../Components/FishNote/FishNoteForm.vue'

const props = defineProps({
  fish: Object,
  note: Object,
  noteTypes: Array,
  tribes: Array,
})

const formRef = ref(null)

function onFormSubmit(formData) {
  router.post(`/fish/${props.fish.id}/knowledge/${props.note.id}`, formData, {
    onSuccess: () => {
      markFishStale(props.fish.id)
      router.visit(`/fish/${props.fish.id}/knowledge-manager`)
    },
    onError: (e) => { formRef.value?.setErrors?.(e) },
  })
}
</script>

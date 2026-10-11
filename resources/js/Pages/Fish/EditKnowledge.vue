<template>
  <div class="container mx-auto p-4 relative">
    <Link :href="`/fish/${fish.id}?tab=advanced`" class="inline-block mb-4 text-blue-600 underline">取消</Link>
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
import { Link, router } from '@inertiajs/vue3'
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
      router.visit(`/fish/${props.fish.id}?tab=advanced`)
    },
    onError: (e) => { formRef.value?.setErrors?.(e) },
  })
}
</script>

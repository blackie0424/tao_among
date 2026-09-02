<template>
  <ItemCard
    :href="`/fish/${fish.id}`"
    :imageUrl="fish.display_image_url || fish.image_url"
    :title="fish.name"
    :imageStyle="displayImgStyle"
    :audioUrl="fish.audio_url"
    :index="index"
  />
</template>

<script setup>
import ItemCard from '@/Components/UI/ItemCard.vue'
import { computed } from 'vue'
import { buildImageDisplayStyle } from '@/composables/useImageDisplayStyle'

const props = defineProps({
  fish: {
    type: Object,
    required: true,
  },
  index: {
    type: Number,
    default: 0,
  },
})

const displayImgStyle = computed(() =>
  buildImageDisplayStyle(props.fish.display_image_position, props.fish.display_image_scale)
)
</script>

<template>
  <ItemCard
    :href="`/fish/${fish.id}`"
    :imageUrl="fish.display_image_url || fish.image_url"
    :title="displayName"
    :framing="fish.display_image_framing"
    :audioUrl="hasAudioAccess ? fish.audio_url : null"
    :index="index"
    variant="fish"
    :gated="gated"
    @blocked="$emit('blocked', $event)"
  />
</template>

<script setup>
import ItemCard from '@/Components/UI/ItemCard.vue'
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { canAccessAudio } from '@/constants/roles'

const props = defineProps({
  fish: {
    type: Object,
    required: true,
  },
  index: {
    type: Number,
    default: 0,
  },
  gated: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['blocked'])


const UNNAMED_FISH = '未命名'
const displayName = computed(() => props.fish.name?.trim() || UNNAMED_FISH)

const hasAudioAccess = computed(() => canAccessAudio(usePage().props.auth?.user?.role))
</script>

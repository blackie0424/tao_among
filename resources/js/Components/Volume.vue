<template>
  <span
    class="inline-flex items-center justify-center rounded-full bg-gray-200 ml-2 cursor-pointer"
    style="width: 2.2rem; height: 2.2rem"
    @click="playAudio"
  >
    <img :src="volumeIcon" alt="volume" class="w-5 h-5 opacity-50" style="filter: grayscale(1)" />
  </span>
</template>

<script setup>
const props = defineProps({
  audioUrl: {
    type: String,
    required: true,
  },
})

const volumeIcon = '/images/volume.png'

function playAudio() {
  const audio = new Audio(props.audioUrl)
  audio.play()
}
</script>

<template>
  <div class="container mx-auto p-4">
    <Breadcrumb second="新增魚類" />
    <FishImageUploader v-if="step === 1" @uploaded="onImageUploaded" />
    <FishNameForm
      v-if="step === 2"
      :uploadedFileName="uploadedFileName"
      @submitted="onFishSubmitted"
    />
    <FishSizeSelector v-if="step === 3" :fishId="fishId" @finished="onSizeFinished" />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

import Breadcrumb from '@/Components/Global/Breadcrumb.vue'
import FishImageUploader from '@/Components/FishImageUploader.vue'
import FishNameForm from '@/Components/FishNameForm.vue'
import FishSizeSelector from '@/Components/FishSizeSelector.vue'

const step = ref(1)
const uploadedFileName = ref('')
const fishId = ref(null)

function onImageUploaded(filename) {
  uploadedFileName.value = filename
  step.value = 2
}
function onFishSubmitted(id) {
  fishId.value = id
  step.value = 3
}
function onSizeFinished() {
  router.visit('/fishs')
}
</script>

<template>
  <form class="space-y-5" @submit.prevent="$emit('submit')">
    <SessionFields
      :form="form"
      :errors="form.errors"
      :tribes="tribes"
      :capture-methods="captureMethods"
      :initial-place-name="initialPlaceName"
      :disabled="form.processing"
    />
    <div v-if="firstError" role="alert" class="text-sm text-red-600">{{ firstError }}</div>
    <button type="submit" class="rounded-lg bg-teal-600 px-5 py-3 font-bold text-white" :disabled="form.processing">儲存</button>
  </form>
</template>

<script setup>
import { computed } from 'vue'
import SessionFields from '@/Components/CaptureSessions/SessionFields.vue'
import { firstValidationError } from '@/utils/validationErrors'

const props = defineProps({ form: { type: Object, required: true }, tribes: { type: Array, default: () => [] }, captureMethods: { type: Object, default: () => ({}) }, initialPlaceName: { type: String, default: '' } })
defineEmits(['submit'])
const firstError = computed(() => firstValidationError(props.form.errors))
</script>

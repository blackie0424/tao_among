<template><Head title="編輯捕獲情境" /><FishAppLayout pageTitle="編輯捕獲情境" mobileBackUrl="/capture-sessions" mobileBackText="情境清單"><div class="max-w-xl mx-auto bg-white rounded-xl border p-6"><SessionForm :form="form" :tribes="tribes" :capture-methods="captureMethods" :initial-place-name="session.place?.name || ''" @submit="form.put(`/capture-sessions/${session.id}`)" /></div></FishAppLayout></template>
<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import SessionForm from './Form.vue'
const props = defineProps({ session: Object, tribes: Array, captureMethods: Object })
const form = useForm({ capture_date: props.session.capture_date?.slice(0, 10), tribe: props.session.tribe, capture_method: props.session.capture_method, place_id: props.session.place_id, place_name: '', notes: props.session.notes || '' })
</script>

<template><Head title="新增捕獲情境" /><FishAppLayout pageTitle="新增捕獲情境" mobileBackUrl="/capture-sessions" mobileBackText="情境清單"><div class="max-w-xl mx-auto bg-white rounded-xl border p-6"><SessionForm :form="form" :tribes="tribes" :capture-methods="captureMethods" @submit="form.post('/capture-sessions')" /></div></FishAppLayout></template>
<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import SessionForm from './Form.vue'
import { formatLocalDate } from '@/utils/localDate'
defineProps({ tribes: Array, captureMethods: Object })
const form = useForm({ capture_date: formatLocalDate(), tribe: '', capture_method: '', place_id: null, notes: '' })
</script>

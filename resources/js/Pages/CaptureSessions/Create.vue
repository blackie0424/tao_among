<template><Head title="新增捕獲情境" /><FishAppLayout pageTitle="新增捕獲情境" mobileBackUrl="/capture-sessions" mobileBackText="情境清單"><div class="max-w-xl mx-auto bg-white rounded-xl border p-6"><SessionForm :form="form" :tribes="tribes" :capture-methods="captureMethods" @submit="form.post('/capture-sessions')" /></div></FishAppLayout></template>
<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import SessionForm from './Form.vue'
defineProps({ tribes: Array, captureMethods: Object })
const form = useForm({ capture_date: new Date().toISOString().slice(0, 10), tribe: '', capture_method: '', place_id: null, notes: '' })
</script>

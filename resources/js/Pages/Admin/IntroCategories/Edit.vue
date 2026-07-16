<template>
  <Head title="編輯分類" />

  <AdminLayout title="編輯分類">
    <div class="mx-auto max-w-3xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
      <h1 class="mb-6 text-2xl font-bold text-gray-900">編輯投影片分類</h1>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">分類名稱 <span class="text-red-500">*</span></label>
          <input
            v-model="form.name"
            type="text"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            :class="{ 'border-red-500': errors.name }"
          />
          <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">排序</label>
          <input
            v-model.number="form.sort_order"
            type="number"
            min="0"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div class="flex items-center gap-3 pt-2">
          <button
            type="submit"
            :disabled="processing"
            class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
          >
            儲存變更
          </button>
          <Link href="/admin/intro-categories" class="text-sm text-gray-500 hover:text-gray-700">取消</Link>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  category: Object,
})

const form = reactive({
  name: props.category.name,
  sort_order: props.category.sort_order,
})
const errors = ref({})
const processing = ref(false)

function submit() {
  processing.value = true
  router.put(`/admin/intro-categories/${props.category.id}`, form, {
    onError: (e) => { errors.value = e },
    onFinish: () => { processing.value = false },
  })
}
</script>

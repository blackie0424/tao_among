<template>
  <form class="space-y-4" @submit.prevent="submitForm">
    <div>
      <label for="name" class="block text-sm font-medium text-gray-700 mb-1">文獻名稱</label>
      <input
        id="name"
        v-model="form.name"
        type="text"
        class="w-full rounded-lg border border-gray-300 px-3 py-2"
      />
      <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
    </div>

    <div>
      <label for="image_url" class="block text-sm font-medium text-gray-700 mb-1">圖片網址</label>
      <input
        id="image_url"
        v-model="form.image_url"
        type="url"
        class="w-full rounded-lg border border-gray-300 px-3 py-2"
      />
      <p v-if="form.errors.image_url" class="mt-1 text-sm text-red-600">{{ form.errors.image_url }}</p>
    </div>

    <div>
      <label for="external_url" class="block text-sm font-medium text-gray-700 mb-1">文獻連結</label>
      <input
        id="external_url"
        v-model="form.external_url"
        type="url"
        class="w-full rounded-lg border border-gray-300 px-3 py-2"
      />
      <p v-if="form.errors.external_url" class="mt-1 text-sm text-red-600">{{ form.errors.external_url }}</p>
    </div>

    <div>
      <label for="author" class="block text-sm font-medium text-gray-700 mb-1">作者</label>
      <input
        id="author"
        v-model="form.author"
        type="text"
        class="w-full rounded-lg border border-gray-300 px-3 py-2"
      />
      <p v-if="form.errors.author" class="mt-1 text-sm text-red-600">{{ form.errors.author }}</p>
    </div>

    <div>
      <label for="status" class="block text-sm font-medium text-gray-700 mb-1">狀態</label>
      <select
        id="status"
        v-model="form.status"
        class="w-full rounded-lg border border-gray-300 px-3 py-2"
      >
        <option value="enabled">啟用</option>
        <option value="disabled">停用</option>
      </select>
      <p v-if="form.errors.status" class="mt-1 text-sm text-red-600">{{ form.errors.status }}</p>
    </div>

    <div class="flex justify-end gap-3">
      <Link href="/admin/references" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700">
        取消
      </Link>
      <button
        type="submit"
        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
        :disabled="form.processing"
      >
        {{ submitLabel }}
      </button>
    </div>
  </form>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
  reference: {
    type: Object,
    default: null,
  },
  submitUrl: {
    type: String,
    required: true,
  },
  method: {
    type: String,
    default: 'post',
  },
  submitLabel: {
    type: String,
    default: '儲存',
  },
})

const form = useForm({
  name: props.reference?.name ?? '',
  image_url: props.reference?.image_url ?? '',
  external_url: props.reference?.external_url ?? '',
  author: props.reference?.author ?? '',
  status: props.reference?.status ?? 'enabled',
})

function submitForm() {
  form[props.method](props.submitUrl)
}
</script>


<template>
  <form class="space-y-4" @submit.prevent="submitForm">
    <div>
      <label for="reference_id" class="block text-sm font-medium text-gray-700 mb-1">引用文獻</label>
      <select
        id="reference_id"
        v-model="form.reference_id"
        class="w-full rounded-lg border border-gray-300 px-3 py-2"
      >
        <option value="">請選擇文獻</option>
        <option v-for="reference in references" :key="reference.id" :value="reference.id">
          {{ reference.name }}
        </option>
      </select>
      <p v-if="form.errors.reference_id" class="mt-1 text-sm text-red-600">
        {{ form.errors.reference_id }}
      </p>
    </div>

    <div>
      <label for="pages" class="block text-sm font-medium text-gray-700 mb-1">頁碼</label>
      <input
        id="pages"
        v-model="form.pages"
        type="text"
        class="w-full rounded-lg border border-gray-300 px-3 py-2"
        placeholder="例如：12、12-15、12,18,25-27"
      />
      <p v-if="form.errors.pages" class="mt-1 text-sm text-red-600">{{ form.errors.pages }}</p>
    </div>

    <div>
      <label for="content" class="block text-sm font-medium text-gray-700 mb-1">內容</label>
      <textarea
        id="content"
        v-model="form.content"
        rows="8"
        class="w-full rounded-lg border border-gray-300 px-3 py-2"
      />
      <p v-if="form.errors.content" class="mt-1 text-sm text-red-600">{{ form.errors.content }}</p>
    </div>

    <div>
      <label for="note" class="block text-sm font-medium text-gray-700 mb-1">備註</label>
      <textarea
        id="note"
        v-model="form.note"
        rows="3"
        class="w-full rounded-lg border border-gray-300 px-3 py-2"
      />
      <p v-if="form.errors.note" class="mt-1 text-sm text-red-600">{{ form.errors.note }}</p>
    </div>

    <div class="flex justify-end gap-3">
      <Link :href="cancelUrl" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700">
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
  knowledge: {
    type: Object,
    default: null,
  },
  references: {
    type: Array,
    default: () => [],
  },
  submitUrl: {
    type: String,
    required: true,
  },
  cancelUrl: {
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
  reference_id: props.knowledge?.reference_id ?? '',
  content: props.knowledge?.content ?? '',
  pages: props.knowledge?.pages ?? '',
  note: props.knowledge?.note ?? '',
})

function submitForm() {
  form[props.method](props.submitUrl)
}
</script>


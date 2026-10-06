<template>
  <Head title="編輯知識項目" />
  <AdminLayout title="編輯知識項目">
    <div class="mx-auto max-w-3xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
      <h1 class="mb-6 text-2xl font-bold text-gray-900">編輯知識項目</h1>
      <form class="space-y-5" @submit.prevent="submit">
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">分類 <span class="text-red-500">*</span></label>
          <select v-model="form.topic_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option :value="null">請選擇分類</option>
            <option v-for="topic in topics" :key="topic.id" :value="topic.id">{{ topic.title }}</option>
          </select>
          <p v-if="errors.topic_id" class="mt-1 text-sm text-red-700">{{ errors.topic_id }}</p>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">標題 <span class="text-red-500">*</span></label>
          <input v-model="form.title" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
          <p v-if="errors.title" class="mt-1 text-sm text-red-700">{{ errors.title }}</p>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">說明文字</label>
          <textarea v-model="form.description" rows="5" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
        </div>

        <TopicItemMediaEditor v-model="form.media" :errors="errors" :disabled="processing" />

        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
          <input v-model="form.is_published" type="checkbox" class="rounded" /> 已發布
        </label>
        <div class="flex items-center gap-3 pt-2">
          <button type="submit" :disabled="processing" class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white disabled:opacity-50">儲存變更</button>
          <Link href="/admin/topic-items" class="text-sm text-gray-500">取消</Link>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import TopicItemMediaEditor from '@/Components/Admin/TopicItemMediaEditor.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ item: Object, topics: Array })
const form = reactive({
  topic_id: props.item.topic_id,
  title: props.item.title,
  description: props.item.description ?? '',
  media: (props.item.media ?? []).map((media) => ({
    id: media.id,
    type: media.type,
    source: media.type === 'youtube' ? media.youtube_url : media.source,
    image_url: media.image_url,
  })),
  is_published: props.item.is_published,
})
const errors = ref({})
const processing = ref(false)

function serializeMedia() {
  return form.media.map(({ id, type, source }) => ({ ...(id ? { id } : {}), type, source }))
}

function submit() {
  processing.value = true
  errors.value = {}
  router.put(`/admin/topic-items/${props.item.id}`, { ...form, media: serializeMedia() }, {
    onError: (value) => { errors.value = value },
    onFinish: () => { processing.value = false },
  })
}
</script>

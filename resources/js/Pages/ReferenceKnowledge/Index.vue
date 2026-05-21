<template>
  <Head :title="`${fish.name}的文獻知識`" />

  <FishAppLayout
    :pageTitle="`${fish.name}的文獻知識`"
    :mobileBackUrl="`/fish/${fish.id}/knowledge-manager`"
    mobileBackText="知識管理"
  >
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">文獻知識</h1>
        <p class="mt-1 text-sm text-gray-500">管理 {{ fish.name }} 的文獻摘錄與頁碼來源。</p>
      </div>
      <Link
        :href="`/fish/${fish.id}/reference-knowledge/create`"
        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
      >
        新增文獻知識
      </Link>
    </div>

    <div class="space-y-4">
      <div
        v-if="knowledge.data.length === 0"
        class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-10 text-center text-gray-500"
      >
        尚未建立文獻知識
      </div>

      <article
        v-for="item in knowledge.data"
        :key="item.id"
        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="space-y-2">
            <div class="flex flex-wrap items-center gap-2">
              <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-700">
                {{ item.reference?.name }}
              </span>
              <span class="text-xs text-gray-500">頁碼：{{ item.pages }}</span>
            </div>
            <p class="whitespace-pre-line text-gray-800">{{ item.content }}</p>
            <p v-if="item.note" class="text-sm text-gray-500">備註：{{ item.note }}</p>
          </div>
          <div class="flex shrink-0 items-center gap-3 text-sm">
            <Link :href="`/fish/${fish.id}/reference-knowledge/${item.id}/edit`" class="text-blue-600 hover:text-blue-700">
              編輯
            </Link>
            <button class="text-red-600 hover:text-red-700" @click="remove(item)">刪除</button>
          </div>
        </div>
      </article>
    </div>
  </FishAppLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'

const props = defineProps({
  fish: Object,
  knowledge: Object,
})

function remove(item) {
  if (confirm('確定要刪除此筆文獻知識嗎？')) {
    router.delete(`/fish/${props.fish.id}/reference-knowledge/${item.id}`)
  }
}
</script>


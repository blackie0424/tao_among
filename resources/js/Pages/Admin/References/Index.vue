<template>
  <Head title="文獻管理" />

  <FishAppLayout pageTitle="文獻管理" mobileBackUrl="/dashboard" mobileBackText="統計面板">
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">文獻管理</h1>
        <p class="mt-1 text-sm text-gray-500">維護可供文獻知識引用的文獻主檔。</p>
      </div>
      <Link
        href="/admin/references/create"
        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
      >
        新增文獻
      </Link>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left font-semibold text-gray-600">文獻名稱</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-600">作者</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-600">狀態</th>
            <th class="px-4 py-3 text-right font-semibold text-gray-600">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="references.data.length === 0">
            <td colspan="4" class="px-4 py-8 text-center text-gray-400">尚未建立文獻資料</td>
          </tr>
          <tr v-for="reference in references.data" :key="reference.id" class="border-t border-gray-100">
            <td class="px-4 py-3">
              <div class="font-medium text-gray-900">{{ reference.name }}</div>
              <a
                v-if="reference.external_url"
                :href="reference.external_url"
                target="_blank"
                rel="noreferrer"
                class="mt-1 inline-block text-xs text-blue-600 hover:text-blue-700"
              >
                查看連結
              </a>
            </td>
            <td class="px-4 py-3 text-gray-600">{{ reference.author }}</td>
            <td class="px-4 py-3">
              <span
                class="rounded-full px-2.5 py-1 text-xs font-medium"
                :class="reference.status === 'enabled' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
              >
                {{ reference.status === 'enabled' ? '啟用' : '停用' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <Link :href="`/admin/references/${reference.id}/edit`" class="text-blue-600 hover:text-blue-700">
                編輯
              </Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </FishAppLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'

defineProps({
  references: Object,
})
</script>


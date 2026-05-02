<template>
  <Head title="LINE 使用者管理" />

  <FishAppLayout pageTitle="LINE 使用者管理" mobileBackUrl="/fishs">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">LINE 使用者管理</h1>
      <p class="text-sm text-gray-500 mt-1">
        管理 LINE 使用者角色。指派 editor 後，使用者選單將立即切換為三格完整功能。
      </p>
    </div>

      <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-200">
              <th class="px-4 py-3 text-left font-semibold text-gray-600">使用者</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 hidden sm:table-cell">
                加入時間
              </th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600">角色</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="lineUsers.data.length === 0">
              <td colspan="3" class="px-4 py-10 text-center text-gray-400">
                尚未有 LINE 使用者資料。<br />
                使用者加好友或與 Bot 互動後會自動出現。
              </td>
            </tr>
            <tr
              v-for="user in lineUsers.data"
              :key="user.id"
              class="border-b border-gray-100 hover:bg-gray-50 transition"
            >
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <img
                    v-if="user.picture_url"
                    :src="user.picture_url"
                    :alt="user.name"
                    class="w-9 h-9 rounded-full object-cover bg-gray-200"
                  />
                  <div
                    v-else
                    class="w-9 h-9 rounded-full bg-gray-200 flex items-center justify-center text-gray-400 text-xs font-bold"
                  >
                    {{ user.name?.charAt(0) }}
                  </div>
                  <div>
                    <div class="font-medium text-gray-900">{{ user.name }}</div>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3 text-gray-500 hidden sm:table-cell">
                {{ formatDate(user.created_at) }}
              </td>
              <td class="px-4 py-3">
                <select
                  :value="user.role"
                  :disabled="updatingId === user.id"
                  @change="updateRole(user, $event.target.value)"
                  class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                  :class="roleClass(user.role)"
                >
                  <option value="viewer">viewer（瀏覽者）</option>
                  <option value="editor">editor（田調人員）</option>
                </select>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 分頁 -->
      <div v-if="lineUsers.last_page > 1" class="flex justify-center gap-2 mt-6">
        <Link
          v-for="page in lineUsers.last_page"
          :key="page"
          :href="`/line-users?page=${page}`"
          class="px-3 py-1.5 rounded-lg text-sm border transition"
          :class="
            page === lineUsers.current_page
              ? 'bg-blue-600 text-white border-blue-600'
              : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'
          "
        >
          {{ page }}
        </Link>
      </div>
  </FishAppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import axios from 'axios'
import { formatDate } from '@/utils/formatDate'

const props = defineProps({
  lineUsers: Object,
})

const updatingId = ref(null)

async function updateRole(user, newRole) {
  if (updatingId.value) return
  updatingId.value = user.id

  try {
    const response = await axios.put(`/line-users/${user.id}/role`, { role: newRole })
    user.role = response.data.role
  } catch (error) {
    alert('角色更新失敗，請稍後再試。')
  } finally {
    updatingId.value = null
  }
}

function roleClass(role) {
  return {
    'bg-gray-50 text-gray-700': role === 'viewer',
    'bg-blue-50 text-blue-700 border-blue-300': role === 'editor',
    'bg-purple-50 text-purple-700 border-purple-300': role === 'admin',
  }
}
</script>

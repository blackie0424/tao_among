<template>
  <div>
    <!-- Dropdown Panel -->
    <div
      class="absolute right-0 top-full mt-2 w-64 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-50 animate-fade-in-down"
    >
      <!-- User Info Header (optional) -->
      <div
        v-if="showUserInfo"
        data-testid="user-info-header"
        class="px-4 py-3 border-b border-gray-50 bg-gray-50/50"
      >
        <div class="text-elder-body font-bold text-elder-text truncate">{{ user.name }}</div>
        <div
          v-if="roleLabel"
          data-testid="user-role-label"
          class="text-elder-aux text-blue-700 font-medium mt-0.5"
        >
          {{ roleLabel }}
        </div>
      </div>

      <!-- Editor/Admin Links -->
      <template v-if="user?.role === 'editor' || user?.role === 'admin'">
        <Link
          href="/workspace"
          data-testid="link-workspace"
          class="flex min-h-touch-primary w-full items-center px-4 text-elder-body text-elder-text hover:bg-gray-50 hover:text-blue-700 transition"
          @click="$emit('close')"
        >
          田調工作區
        </Link>
        <Link
          href="/fish/batch-create"
          data-testid="link-batch-create"
          class="flex min-h-touch-primary w-full items-center px-4 text-elder-body text-elder-text hover:bg-gray-50 hover:text-blue-700 transition"
          @click="$emit('close')"
        >
          ＋ 新增魚種
        </Link>
      </template>

      <!-- Admin Links -->
      <Link
        v-if="user?.role === 'admin'"
        href="/admin"
        data-testid="link-admin-hub"
        class="flex min-h-touch-primary w-full items-center px-4 text-elder-body text-elder-text hover:bg-gray-50 hover:text-blue-700 transition"
        @click="$emit('close')"
      >
        系統管理後台
      </Link>

      <!-- Logout -->
      <Link
        href="/logout"
        method="post"
        as="button"
        class="flex min-h-touch-primary w-full items-center px-4 text-elder-body text-red-700 hover:bg-gray-50 transition"
      >
        登出
      </Link>
    </div>

    <!-- Backdrop -->
    <div
      data-testid="dropdown-backdrop"
      class="fixed inset-0 z-40"
      style="background: transparent"
      @click="$emit('close')"
    ></div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { ROLE_LABELS } from '@/constants/roles'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  user: { type: Object, required: true },
  showUserInfo: { type: Boolean, default: false },
})

const roleLabel = computed(() => ROLE_LABELS[props.user?.role])

defineEmits(['close'])
</script>

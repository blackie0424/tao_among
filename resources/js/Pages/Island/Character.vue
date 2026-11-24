<template>
  <Head title="魚島：設定角色" />

  <IslandLayout>
    <div class="px-1 pb-4 pt-4 text-center">
      <h1 class="text-elder-title font-extrabold">歡迎來到魚島</h1>
      <p class="mt-1 text-elder-body text-island-muted">不用登入，取個名字就能開始</p>
    </div>

    <form class="space-y-3" @submit.prevent="submit">
      <div class="rounded-island bg-white p-4">
        <label for="island-name" class="block text-elder-body font-bold">你的名字</label>
        <input
          id="island-name"
          ref="nameInput"
          v-model="form.name"
          name="name"
          type="text"
          autocomplete="nickname"
          :maxlength="name_max_length"
          :aria-describedby="
            form.errors.name ? 'island-name-hint island-name-error' : 'island-name-hint'
          "
          :aria-invalid="form.errors.name ? 'true' : 'false'"
          class="mt-2 min-h-touch-primary w-full rounded-island border-2 px-3 text-elder-body focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-island-ink/30"
          :class="form.errors.name ? 'border-red-700' : 'border-island-line'"
        />
        <p id="island-name-hint" class="mt-1 text-elder-aux text-island-muted">
          別人會看到這個名字，最多 {{ name_max_length }} 個字
        </p>
        <p
          v-if="form.errors.name"
          id="island-name-error"
          role="alert"
          class="mt-1 text-elder-body text-red-700"
        >
          {{ form.errors.name }}
        </p>
      </div>

      <fieldset class="rounded-island bg-white p-4">
        <legend class="float-left mb-3 w-full text-elder-body font-bold">你是哪個部落的人</legend>
        <div class="clear-both grid grid-cols-2 gap-2">
          <label
            v-for="tribe in tribes"
            :key="tribe"
            class="flex min-h-touch-primary cursor-pointer items-center justify-center rounded-island border-2 px-2 text-center text-elder-body font-bold has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-island-ink/30"
            :class="
              form.tribe === tribe
                ? 'border-island-ink bg-island-ink text-white'
                : 'border-island-line bg-white text-island-ink'
            "
          >
            <input
              v-model="form.tribe"
              type="radio"
              name="tribe"
              :value="tribe"
              class="sr-only"
              :aria-describedby="form.errors.tribe ? 'island-tribe-error' : undefined"
            />
            {{ islandTribeLabel(tribe) }}
          </label>
        </div>
        <p
          v-if="form.errors.tribe"
          id="island-tribe-error"
          role="alert"
          class="mt-2 text-elder-body text-red-700"
        >
          {{ form.errors.tribe }}
        </p>
      </fieldset>

      <button
        type="submit"
        :disabled="form.processing"
        class="min-h-touch-primary w-full rounded-island bg-island-accent text-elder-body font-bold text-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-island-ink/40 disabled:opacity-50"
      >
        {{ form.processing ? '進入中…' : '進入魚島' }}
      </button>
    </form>
  </IslandLayout>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { nextTick, ref } from 'vue'
import IslandLayout from '@/Layouts/IslandLayout.vue'
import { islandTribeLabel } from '@/constants/island'

defineProps({
  tribes: { type: Array, required: true },
  name_max_length: { type: Number, required: true },
})

const form = useForm({ name: '', tribe: '' })
const nameInput = ref(null)

// 送出後有錯：把焦點移到第一個有錯的欄位（名字在前、部落在後）
async function focusFirstError(errors) {
  await nextTick()
  if (errors.name) {
    nameInput.value?.focus()
  } else if (errors.tribe) {
    document.querySelector('input[name="tribe"]')?.focus()
  }
}

function submit() {
  form.post('/island/character', { onError: focusFirstError })
}
</script>

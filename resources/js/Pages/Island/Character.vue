<template>
  <Head title="魚島：設定角色" />

  <main class="mx-auto min-h-screen w-full max-w-md px-4 py-8">
    <h1 class="text-center text-elder-title font-bold">歡迎來到魚島</h1>
    <p class="mt-2 text-center text-elder-body">不用登入，取個名字就能開始</p>

    <form class="mt-6 space-y-6" @submit.prevent="submit">
      <p v-if="form.errors.form_token" role="alert" class="text-elder-body text-red-700">
        {{ form.errors.form_token }}
      </p>

      <div>
        <label for="island-name" class="block text-elder-body font-bold">你的名字</label>
        <input
          id="island-name"
          v-model="form.name"
          name="name"
          type="text"
          autocomplete="nickname"
          :maxlength="name_max_length"
          :aria-describedby="
            form.errors.name ? 'island-name-hint island-name-error' : 'island-name-hint'
          "
          :aria-invalid="form.errors.name ? 'true' : 'false'"
          class="mt-2 min-h-touch-primary w-full rounded-lg border-2 px-3 text-elder-body"
        />
        <p id="island-name-hint" class="mt-1 text-elder-aux">
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

      <fieldset>
        <legend class="text-elder-body font-bold">你是哪個部落的人</legend>
        <div class="mt-2 grid grid-cols-2 gap-2">
          <label
            v-for="tribe in tribes"
            :key="tribe"
            class="flex min-h-touch-primary cursor-pointer items-center justify-center rounded-lg border-2 px-2 text-elder-body font-bold"
            :class="form.tribe === tribe ? 'border-black bg-black text-white' : 'border-gray-300'"
          >
            <input v-model="form.tribe" type="radio" name="tribe" :value="tribe" class="sr-only" />
            {{ tribe }}
          </label>
        </div>
        <p v-if="form.errors.tribe" role="alert" class="mt-1 text-elder-body text-red-700">
          {{ form.errors.tribe }}
        </p>
      </fieldset>

      <button
        type="submit"
        :disabled="form.processing"
        class="min-h-touch-primary w-full rounded-lg bg-black text-elder-name font-bold text-white disabled:opacity-50"
      >
        進入魚島
      </button>
    </form>
  </main>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { watch } from 'vue'

const props = defineProps({
  tribes: { type: Array, required: true },
  name_max_length: { type: Number, required: true },
  form_token: { type: String, required: true },
})

const form = useForm({ name: '', tribe: '', form_token: props.form_token })

// 送出失敗回到這一頁時元件不會重建，伺服器換發的新代碼要同步進表單，否則重送仍帶舊代碼
watch(
  () => props.form_token,
  (token) => {
    form.form_token = token
  }
)

function submit() {
  form.post('/island/character')
}
</script>

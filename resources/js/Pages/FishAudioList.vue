<template>
  <Head :title="`${fish.name}・管理發音`" />

  <FishAppLayout
    :pageTitle="`${fish.name}・管理發音`"
    :mobileBackUrl="`/fish/${fish.id}?tab=audio`"
    mobileBackText="魚類資料"
  >
    <div class="mx-auto max-w-3xl">
      <h1 class="font-serif text-elder-title text-elder-text">{{ fish.name }}・管理發音</h1>

      <p v-if="audioGroups.length === 0" class="mt-6 text-elder-body text-elder-text">
        這隻魚還沒有名稱，請先
        <Link :href="`/fish/${fish.id}/edit`" class="text-brand underline">編輯名稱</Link>。
      </p>

      <section
        v-for="group in audioGroups"
        :key="group.name"
        class="mt-6 border-t border-rule pt-4"
        :data-testid="`name-group-${group.name}`"
      >
        <div class="flex items-center justify-between gap-3">
          <h2 class="font-serif text-elder-name font-bold text-elder-text">{{ group.name }}</h2>
          <Link
            :href="`/fish/${fish.id}/audio/create?name=${encodeURIComponent(group.name)}`"
            class="inline-flex min-h-touch-secondary items-center bg-brand px-4 text-elder-body font-bold text-white hover:bg-brand-dark"
          >
            ＋ 錄音
          </Link>
        </div>
        <p v-if="group.audios.length === 0" class="mt-3 text-elder-body text-elder-subtext">還沒有錄音</p>
        <AudioClipRow
          v-for="audio in group.audios"
          :key="audio.id"
          :audio="audio"
          :fishId="fish.id"
          :isCurrent="isCurrent(audio)"
        />
      </section>

      <section v-if="unassignedAudios.length" class="mt-6 border-t border-rule pt-4" data-testid="unassigned">
        <h2 class="text-elder-body font-bold text-elder-text">未對應名稱</h2>
        <AudioClipRow
          v-for="audio in unassignedAudios"
          :key="audio.id"
          :audio="audio"
          :fishId="fish.id"
          :isCurrent="isCurrent(audio)"
        >
          <template #actions>
            <div class="flex items-center gap-2">
              <select
                v-model="assignTo[audio.id]"
                class="min-h-touch-secondary border border-rule-strong bg-paper-raised px-2 text-elder-body"
                :aria-label="`把這段錄音指派到名稱`"
              >
                <option :value="undefined" disabled>選名稱</option>
                <option v-for="group in audioGroups" :key="group.name" :value="group.name">{{ group.name }}</option>
              </select>
              <button
                type="button"
                class="min-h-touch-secondary px-2 text-elder-body text-brand underline disabled:opacity-50"
                :disabled="!assignTo[audio.id]"
                @click="assign(audio)"
              >
                指派名稱
              </button>
            </div>
          </template>
        </AudioClipRow>
      </section>
    </div>
  </FishAppLayout>
</template>

<script setup>
import { reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import AudioClipRow from '@/Components/FishAudio/AudioClipRow.vue'

const props = defineProps({
  fish: { type: Object, required: true },
  audioGroups: { type: Array, default: () => [] },
  unassignedAudios: { type: Array, default: () => [] },
})

const assignTo = reactive({})

// 魚頁目前使用的那一段（主要發音，維持現狀；是否調整見 #174）
const isCurrent = (audio) => !!props.fish.audio_filename && audio.name === props.fish.audio_filename

function assign(audio) {
  router.put(`/fish/${props.fish.id}/audio/${audio.id}/name`, { name: assignTo[audio.id] })
}
</script>

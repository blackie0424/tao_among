<template>
  <!-- 魚島作答頁一題的結果：選項人數、多數、其中本部落人數、誰答的（預設收合） -->
  <div data-testid="result" class="mt-3">
    <p data-testid="result-status" class="text-elder-body text-island-muted">{{ status }}</p>
    <p class="text-elder-aux text-island-muted">其中本部落 {{ result.own_tribe_count }} 人</p>

    <ul class="mt-3 space-y-2">
      <li
        v-for="choice in result.choices"
        :key="choice.value"
        data-testid="choice"
        class="rounded-island border-2 p-3"
        :class="choice.value === result.majority ? 'border-island-accent' : 'border-island-line'"
      >
        <div class="flex items-center justify-between gap-2 text-elder-body">
          <span class="font-bold">
            {{ choice.value }}
            <span
              v-if="choice.value === result.majority"
              class="ml-1 rounded-island bg-island-accent px-2 text-elder-aux text-white"
              >多數</span
            >
            <span v-if="choice.value === myAnswer" class="ml-1 text-elder-aux text-island-muted"
              >你選的</span
            >
          </span>
          <span data-testid="choice-count">{{ choice.count }} 人</span>
        </div>
        <ul
          v-if="showResponders && choice.responders.length"
          data-testid="responders"
          class="mt-2 space-y-1"
        >
          <li
            v-for="(person, i) in choice.responders"
            :key="i"
            data-testid="responder"
            class="text-elder-aux"
          >
            {{ responderLabel(person) }}
          </li>
        </ul>
      </li>
    </ul>

    <button
      v-if="result.total > 0"
      type="button"
      :aria-expanded="showResponders ? 'true' : 'false'"
      class="mt-3 min-h-touch-primary w-full rounded-island text-elder-body font-bold underline"
      @click="showResponders = !showResponders"
    >
      {{ showResponders ? '收起' : '看是誰答的' }}
    </button>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { islandTribeLabel } from '@/constants/island'

// 一題至少要有這麼多人回答才可能標出多數（與後端 FishTribeResponse::ISLAND_MAJORITY_MIN_RESPONSES 相同）
const MAJORITY_MIN_RESPONSES = 3

const props = defineProps({
  result: { type: Object, required: true },
  myAnswer: { type: String, default: null },
})

const showResponders = ref(false)

/** 例如「阿海 yayo（椰油）」「訪客甲 訪客 imowrod（紅頭）」；沒有魚島角色的只有「部落未填」 */
function responderLabel(person) {
  const tribe = person.tribe === null ? '部落未填' : islandTribeLabel(person.tribe)
  return [person.name, person.is_guest ? '訪客' : null, tribe]
    .filter((part) => part !== null)
    .join(' ')
}

const status = computed(() => {
  const total = props.result.total
  if (total === 0) return '還沒有人回答'
  if (total < MAJORITY_MIN_RESPONSES) return `人數還不夠，目前 ${total} 人回答`
  return `${total} 人回答`
})
</script>

<template>
  <Head title="魚島" />

  <IslandLayout wide>
    <template #header>
      <h1 class="text-elder-name font-extrabold">魚島</h1>
      <p class="rounded-island bg-white px-3 py-1.5 text-elder-aux">{{ character.name }}</p>
    </template>

    <IslandWorld :tribe="currentTribe" @tribe-change="enterTribe" />
    <p class="mt-2 px-1 text-elder-aux text-island-muted">
      用方向鍵走路，或點地面走過去；拖曳畫面轉鏡頭，滾輪或雙指拉遠拉近
    </p>
    <!-- 海岸線、公路、山的位置取自 OpenStreetMap，授權要求標示來源（#207） -->
    <p data-testid="map-credit" class="px-1 text-elder-aux text-island-muted">
      地圖資料 © OpenStreetMap contributors
    </p>

    <section class="mt-3 rounded-island bg-white p-4">
      <dl class="space-y-3">
        <div>
          <dt class="text-elder-aux text-island-muted">你現在在</dt>
          <dd class="text-elder-name font-extrabold text-island-accent">
            {{ islandTribeLabel(currentTribe) }}
          </dd>
        </div>
        <div>
          <dt class="text-elder-aux text-island-muted">你的部落</dt>
          <dd class="text-elder-body font-bold">{{ islandTribeLabel(character.tribe) }}</dd>
        </div>
      </dl>
    </section>
  </IslandLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import IslandLayout from '@/Layouts/IslandLayout.vue'
import IslandWorld from '@/Components/Island/IslandWorld.vue'
import { islandTribeLabel } from '@/constants/island'

const props = defineProps({
  character: { type: Object, required: true },
  tribes: { type: Array, required: true },
  current_tribe: { type: String, required: true },
})

const LOCATION_URL = '/island/location'

// 目前所在部落：一開始是伺服器記的，之後是角色最後走進的部落格子
const currentTribe = ref(props.current_tribe)

// 記到伺服器一次只送一筆：前一筆還沒回來時只留最新的部落，回來後（不論成敗）再送，
// 所以最後送出的一定是最後走進的部落，不會被較早的請求蓋掉
let sending = false
let waitingTribe = null

async function rememberLocation(tribe) {
  if (sending) {
    waitingTribe = tribe
    return
  }
  sending = true
  try {
    await window.axios.post(LOCATION_URL, { tribe })
  } catch (error) {
    // 畫面照常顯示新部落、不重送這一筆；重新整理會回到上一次成功記住的部落
    console.warn('魚島：所在部落沒有記到伺服器', error)
  }
  sending = false
  if (waitingTribe !== null) {
    const next = waitingTribe
    waitingTribe = null
    rememberLocation(next)
  }
}

function enterTribe(tribe) {
  currentTribe.value = tribe
  rememberLocation(tribe)
}
</script>

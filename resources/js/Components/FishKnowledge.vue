<template>
  <!-- 地區按鈕 -->
  <div class="section-buttons flex flex-wrap md:flex-nowrap gap-2 md:gap-4 my-4 p-4 rounded-lg">
    <button
      v-for="locate in locates"
      :key="locate.value"
      type="button"
      class="block w-full sm:w-1/2 md:w-auto md:min-w-[120px] px-6 py-2 min-h-[48px] flex items-center justify-center text-blue-800 dark:text-blue-200 rounded-full"
      :class="getBtnClass(locate.value)"
      @click="changeLocate(locate.value)"
    >
      {{ locate.label }}
    </button>
  </div>

  <!-- 筆記區塊 -->
  <div v-if="notes && notes.length" class="w-full flex flex-col items-center">
    <div
      v-for="note in notes"
      :key="note.id"
      class="section w-full max-w-md p-4 bg-beige-100 rounded-lg shadow-custom mb-4"
    >
      <div class="section-title text-xl font-semibold text-primary mb-2">{{ note.note_type }}</div>
      <div class="text text-secondary">{{ note.note }}</div>
    </div>
  </div>
  <div v-else class="text-center text-gray-500 mt-4">沒有筆記資料</div>
</template>

<script setup>
import { ref } from 'vue';
const emit = defineEmits(['update:locateData']);

const props = defineProps({
  locates: Array,
  fishId: Number,
  currentLocate: String,
  notes: Array,
});

const loading = ref(false);

function getBtnClass(value) {
  if (props.currentLocate === value) {
    return [
      'bg-yellow-500',
      'dark:bg-yellow-600',
      '!hover:bg-yellow-500',
      '!dark:hover:bg-yellow-600'
    ];
  }
  return [
    'bg-blue-100',
    'dark:bg-blue-800',
    'hover:bg-blue-200',
    'dark:hover:bg-blue-700'
  ];
}

function changeLocate(locate) {
  // 1. 立即 emit，讓父層 currentLocate 立刻變色，notes 設為空陣列
  emit('update:locateData', { locate, notes: [] });

  // 2. 再發 API 取得新資料
  loading.value = true;
  fetch(`/prefix/api/fish/${props.fishId}/notes?locate=${locate}`)
    .then(res => res.json())
    .then(data => {
      emit('update:locateData', { locate, notes: data.data || [] });
    })
    .finally(() => {
      loading.value = false;
    });
}
</script>
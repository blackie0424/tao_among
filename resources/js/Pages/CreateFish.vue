<template>
  <div class="container mx-auto p-4">
    <Breadcrumb second="新增魚類"/>

    <!-- 步驟一：上傳圖片 -->
    <div v-if="!imageUploaded && !showArmSelector" class="bg-white p-6 rounded shadow-md max-w-md mx-auto mb-6">
      <div class="mb-4">
        <label for="image" class="block font-semibold mb-2">魚類圖片</label>
        <input type="file" id="image" @change="onFileChange" accept="image/*" class="w-full border rounded px-3 py-2">
        <div v-if="selectedFile" class="text-gray-700 mt-2">已選擇檔案：{{ selectedFile.name }}</div>
      </div>
      <button type="button" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700"
              @click="uploadImage" :disabled="!selectedFile || uploading">
        <span v-if="uploading">上傳中...</span>
        <span v-else>上傳圖片</span>
      </button>
      <div v-if="uploadError" class="text-red-600 mt-2">{{ uploadError }}</div>
    </div>

    <!-- 步驟二：輸入名稱並送出 -->
    <form v-if="imageUploaded && !showArmSelector" @submit.prevent="submitFish" class="bg-white p-6 rounded shadow-md max-w-md mx-auto">
      <div class="mb-4">
        <div class="text-green-600 mb-2">圖片已上傳，檔名：{{ uploadedFileName }}</div>
        <label for="name" class="block font-semibold mb-2">魚類名稱</label>
        <input type="text" id="name" v-model="fishName" class="w-full border rounded px-3 py-2" required>
      </div>
      <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700"
              :disabled="submitting">
        <span v-if="submitting">送出中...</span>
        <span v-else>送出</span>
      </button>
      <div v-if="submitError" class="text-red-600 mt-2">{{ submitError }}</div>
      <div v-if="submitSuccess" class="text-green-600 mt-2">魚類新增成功！</div>
    </form>

    <!-- 步驟三 -->
    <div v-if="showArmSelector" class="bg-white p-6 rounded shadow-md max-w-md mx-auto">
      <h3 class="text-xl font-bold mb-4">選擇魚的尺寸</h3>
      <ArmSelector @update:selectedSegments="onSelectedParts" />
      <pre>{{ selectedParts }}</pre>
      <button class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 mt-4"
              @click="submitFishSize"
              :disabled="sizeSubmitting">
        <span v-if="sizeSubmitting">送出中...</span>
        <span v-else>送出尺寸</span>
      </button>
      <div v-if="sizeSubmitError" class="text-red-600 mt-2">{{ sizeSubmitError }}</div>
      <div v-if="sizeSubmitSuccess" class="text-green-600 mt-2">尺寸新增成功！</div>
    </div>

  </div>
</template>

<script setup>
import { ref } from 'vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';


const selectedFile = ref(null);
const uploading = ref(false);
const imageUploaded = ref(false);
const uploadedFileName = ref('');
const uploadError = ref('');
const fishName = ref('');
const submitting = ref(false);
const submitError = ref('');
const submitSuccess = ref(false);


//ArmSelector
import ArmSelector from '@/Components/ArmSelector.vue'; // 請依實際路徑調整
const fishId = ref(null); // 新增，儲存新增魚的 id
const showArmSelector = ref(false);
const selectedParts = ref([]);
const sizeSubmitting = ref(false);
const sizeSubmitError = ref('');
const sizeSubmitSuccess = ref(false);

const onSelectedParts = (val) => {
  selectedParts.value = val
}

function onFileChange(e) {
  selectedFile.value = e.target.files[0];
  imageUploaded.value = false;
  uploadedFileName.value = '';
  uploadError.value = '';
}

async function uploadImage() {
  if (!selectedFile.value) return;
  uploading.value = true;
  uploadError.value = '';
  submitSuccess.value = false;

  // 產生安全檔名
  const fileName = Date.now() + '_' + selectedFile.value.name.replace(/[^a-zA-Z0-9._-]/g, '_');
  try {
    // 1. 取得 signed upload url
    const res = await fetch('/prefix/api/supabase/signed-upload-url', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ filename: fileName })
    });
    const data = await res.json();
    if (!data.url || !data.filename) throw new Error(data.message || '取得上傳網址失敗');

    // 2. 用 PUT 上傳檔案到 Supabase
    const uploadRes = await fetch(data.url, {
      method: 'PUT',
      body: selectedFile.value
    });
    if (!uploadRes.ok) throw new Error('圖片上傳失敗');

    // 3. 保留後端回傳的 filename
    imageUploaded.value = true;
    uploadedFileName.value = data.filename;
  } catch (e) {
    uploadError.value = e.message || '上傳失敗';
  } finally {
    uploading.value = false;
  }
}

async function submitFish() {
  if (!fishName.value || !uploadedFileName.value) return;
  submitting.value = true;
  submitError.value = '';
  submitSuccess.value = false;
  try {
    const res = await fetch('/prefix/api/fish', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: fishName.value,
        image: uploadedFileName.value
      })
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || '新增失敗');
    submitSuccess.value = true;
    fishName.value = '';
    fishId.value = data.data.id; // 假設後端回傳新魚的 id
    showArmSelector.value = true; // 顯示第三步驟
  } catch (e) {
    submitError.value = e.message || '新增失敗';
  } finally {
    submitting.value = false;
  }
}

async function submitFishSize() {
  if (!fishId.value || !selectedParts.value.length) {
    sizeSubmitError.value = '請選擇尺寸';
    return;
  }
  sizeSubmitting.value = true;
  sizeSubmitError.value = '';
  sizeSubmitSuccess.value = false;
  try {
    const res = await fetch('/prefix/api/fishSize', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        fish_id: fishId.value,
        parts: selectedParts.value
      })
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || '尺寸新增失敗');
    sizeSubmitSuccess.value = true;
    setTimeout(() => {
      window.location.href = '/';
    }, 1000);
  } catch (e) {
    sizeSubmitError.value = e.message || '尺寸新增失敗';
  } finally {
    sizeSubmitting.value = false;
  }
}
</script>
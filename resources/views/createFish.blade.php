<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>新增魚類</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- <link rel="stylesheet" href="{{ asset('css/createFish.css') }}"> 若有自訂CSS -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100">
<div id="app" class="container mx-auto p-4" v-cloak>
    <h2 class="text-2xl font-bold mb-4">新增魚類</h2>
    <!-- 步驟一：上傳圖片（僅在尚未成功上傳時顯示） -->
    <div v-if="!imageUploaded" class="bg-white p-6 rounded shadow-md max-w-md mx-auto mb-6">
        <div class="mb-4">
            <label for="image" class="block font-semibold mb-2">魚類圖片</label>
            <input type="file" id="image" @change="onFileChange" accept="image/*" class="w-full border rounded px-3 py-2">
            <div v-if="selectedFile" class="text-gray-700 mt-2">已選擇檔案：@{{ selectedFile.name }}</div>
        </div>
        <button type="button" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700"
                @click="uploadImage" :disabled="!selectedFile || uploading">
            <span v-if="uploading">上傳中...</span>
            <span v-else>上傳圖片</span>
        </button>
        <div v-if="uploadError" class="text-red-600 mt-2" v-text="uploadError"></div>
    </div>

    <!-- 步驟二：輸入名稱並送出（僅在圖片已上傳時顯示） -->
    <form v-if="imageUploaded" @submit.prevent="submitFish" class="bg-white p-6 rounded shadow-md max-w-md mx-auto">
        <div class="mb-4">
            <div class="text-green-600 mb-2">圖片已上傳，檔名：@{{ uploadedFileName }}</div>
            <label for="name" class="block font-semibold mb-2">魚類名稱</label>
            <input type="text" id="name" v-model="fishName" class="w-full border rounded px-3 py-2" required>
        </div>
        <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700"
                :disabled="submitting">
            <span v-if="submitting">送出中...</span>
            <span v-else>送出</span>
        </button>
        <div v-if="submitError" class="text-red-600 mt-2" v-text="submitError"></div>
        <div v-if="submitSuccess" class="text-green-600 mt-2">魚類新增成功！</div>
    </form>
</div>
</body>
</html>
import { createApp, ref } from 'vue';

createApp({
    setup() {
        const selectedFile = ref(null);
        const uploading = ref(false);
        const imageUploaded = ref(false);
        const uploadedFileName = ref('');
        const uploadError = ref('');
        const fishName = ref('');
        const submitting = ref(false);
        const submitError = ref('');
        const submitSuccess = ref(false);

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
                // 新增成功後 1 秒跳轉回首頁
                setTimeout(() => {
                    window.location.href = '/';
                }, 1000);
            } catch (e) {
                submitError.value = e.message || '新增失敗';
            } finally {
                submitting.value = false;
            }
        }

        return {
            selectedFile, uploading, imageUploaded, uploadedFileName, uploadError,
            fishName, submitting, submitError, submitSuccess,
            onFileChange, uploadImage, submitFish
        };
    }
}).mount('#app');
const { createApp, ref } = Vue;

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

            const fileName = Date.now() + '_' + selectedFile.value.name.replace(/\s+/g, '_');
            try {
                const res = await fetch('/prefix/api/supabase/signed-upload-url', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ fileName })
                });
                const data = await res.json();
                if (!data.url) throw new Error(data.message || '取得上傳網址失敗');

                const uploadRes = await fetch(data.url, {
                    method: 'PUT',
                    body: selectedFile.value
                });
                if (!uploadRes.ok) throw new Error('圖片上傳失敗');

                imageUploaded.value = true;
                uploadedFileName.value = fileName;
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
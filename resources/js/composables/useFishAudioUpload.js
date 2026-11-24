import { apiFetch } from '@/utils/apiFetch'

/**
 * 網頁錄音的上傳：取得暫存簽名 → PUT 到暫存位置。
 * 回傳暫存檔名；之後由「確認」（新增）或「編輯」請求告訴伺服器正式寫入，失敗時不會留下任何資料。
 */
export async function uploadPendingAudio(fishId, blob, ext) {
  const signRes = await apiFetch(`/prefix/api/fish/${fishId}/storage/signed-upload-audio-url`, {
    method: 'POST',
    body: JSON.stringify({ filename: `audio_${Date.now()}.${ext}` }),
  })
  const signed = await signRes.json()
  if (!signRes.ok || !signed.url) throw new Error('取得上傳資訊失敗')

  const putRes = await fetch(signed.url, { method: 'PUT', body: blob })
  if (!putRes.ok) throw new Error('音訊上傳失敗')

  return signed.filename
}

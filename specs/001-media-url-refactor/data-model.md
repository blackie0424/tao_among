# Data Model: Media URL Refactor

## Entities

### Fish (existing)
- id: int
- name: string
- image: string|null (檔名或完整 URL, 歷史資料)
- has_webp: bool|null
- audio_filename: string|null
- Relations: hasMany FishAudio, hasMany CaptureRecord, ...

### FishAudio (existing)
- id: int
- fish_id: int (FK)
- name: string (顯示名稱)
- locate: string|null (檔名)

### CaptureRecord (existing)
- id: int
- fish_id: int (FK)
- image_path: string|null (檔名)

### Derived/View Fields
- Fish.image (response): 完整 URL（has_webp=true→webp；false/null→原圖；空→default.png）
- Fish.audios[].url: 有檔名時為完整 URL；否則缺席
- CaptureRecord.image_url: 完整 URL（沿用 Fish.has_webp 規則）

## Validation (from spec)
- 簽名上傳：filename 副檔名必須符合允許清單；回傳絕對 URL。
- 音訊：空值不輸出欄位。

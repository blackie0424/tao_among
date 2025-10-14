# 音頻播放系統整合測試文檔

## 概述

本文檔描述了音頻播放系統的整合測試，專門針對任務 7.1「測試與現有系統的整合」的實現。

## 測試文件

### AudioPlaybackIntegration.spec.js

這是主要的整合測試文件，包含以下測試類別：

#### 1. AudioPlayerService 整合測試

- **Volume 組件應該正確整合 AudioPlayerService**

  - 驗證 Volume 組件能正確調用 AudioPlayerService.play 方法
  - 確認參數傳遞正確（audioId, audioElement, audioUrl）

- **useAudioPlayback 應該正確使用 AudioPlayerService 的 play 方法**

  - 測試 useAudioPlayback 組合式函數與 AudioPlayerService 的整合
  - 驗證播放方法的正確調用

- **useAudioPlayback 應該監聽 AudioPlayerService 的事件**

  - 確認事件監聽器正確註冊（ended, error, stop）
  - 驗證事件系統的整合

- **AudioPlayerService 的狀態變化應該反映到 Volume 組件**
  - 測試服務狀態變化對組件的影響
  - 驗證響應式狀態同步

#### 2. 多個 Volume 組件互斥播放測試

- **當一個 Volume 組件播放時，其他組件應該停止**

  - 測試互斥播放機制
  - 驗證 ensureMutualExclusion 方法的調用

- **多個 Volume 組件應該共享全域播放狀態**

  - 確認全域狀態管理正確
  - 測試組件間的狀態同步

- **當播放的音頻結束時，所有組件應該重置狀態**
  - 驗證播放結束事件的處理
  - 確認狀態重置機制

#### 3. 與現有音頻功能的相容性測試

- **Volume 組件應該與 FishAudioCard 組件共享播放狀態**

  - 測試不同組件類型間的狀態共享
  - 驗證統一的狀態管理

- **不同類型的音頻組件應該能夠互斥播放**

  - 確認跨組件類型的互斥機制
  - 測試整合的播放控制

- **應該保持與現有 AudioPlayerService API 的相容性**
  - 驗證所有必要方法的存在
  - 確認 API 接口的完整性

#### 4. 錯誤處理整合測試

- **AudioPlayerService 的錯誤應該正確傳播到 Volume 組件**

  - 測試錯誤事件的傳播機制
  - 驗證錯誤狀態的正確顯示

- **多個組件中的錯誤應該獨立處理**
  - 確認錯誤處理的獨立性
  - 測試錯誤狀態的隔離

#### 5. 事件系統整合測試

- **AudioPlayerService 事件應該正確觸發組件狀態更新**

  - 測試事件驅動的狀態更新
  - 驗證事件處理機制

- **組件卸載時應該正確清理事件監聽器**

  - 確認資源清理機制
  - 測試內存洩漏防護

- **全域狀態同步應該正常工作**
  - 驗證狀態同步事件系統
  - 測試全域狀態管理

#### 6. 效能和資源管理測試

- **多個組件應該共享同一個 AudioPlayerService 實例**

  - 確認單例模式的實現
  - 測試資源共享機制

- **組件卸載時應該正確清理資源**
  - 驗證資源清理邏輯
  - 確認無內存洩漏

## 測試覆蓋的需求

本整合測試覆蓋了以下需求：

### Requirement 4.1

- 系統中有其他音頻正在播放時，新的音頻播放自動停止之前的音頻
- 測試通過互斥播放機制驗證

### Requirement 4.2

- 視覺設計與 FishAudioCard 組件保持一致的設計語言
- 測試通過組件間狀態共享驗證

### Requirement 4.3

- 使用統一的 AudioPlayerService 進行狀態管理
- 測試通過服務整合和 API 相容性驗證

## 測試執行

### 運行整合測試

```bash
npx vitest run resources/js/Tests/AudioPlaybackIntegration.spec.js
```

### 運行所有相關測試

```bash
npx vitest run resources/js/Tests/Volume.spec.js resources/js/Tests/useAudioPlayback.spec.js resources/js/Tests/AudioPlaybackIntegration.spec.js
```

## 測試結果

所有 17 個整合測試都通過，確認了：

1. ✅ AudioPlayerService 與 Volume 組件的正確整合
2. ✅ 多個 Volume 組件的互斥播放行為
3. ✅ 與現有音頻功能（FishAudioCard）的相容性
4. ✅ 錯誤處理的正確傳播和獨立性
5. ✅ 事件系統的正確運作
6. ✅ 資源管理和清理機制

## 注意事項

### Vue 警告

測試中會出現一些 Vue 警告，這是因為在測試環境中直接調用 `useAudioPlayback` 組合式函數時沒有活動的組件實例。這些警告不影響實際功能，在真實的 Vue 組件中不會出現。

### Mock 實現

測試使用了完整的 AudioPlayerService mock 實現，模擬了真實服務的行為，包括：

- 事件系統
- 互斥播放邏輯
- 狀態管理
- 錯誤處理

這確保了測試的準確性和可靠性。

## 結論

整合測試成功驗證了音頻播放系統與現有系統的正確整合，確認了所有相關需求的實現，為系統的穩定性和可靠性提供了保障。

# among no tao 設計規範（博物誌）

來源：#126 設計稿（博物誌方向）。長者可用性規格（字級、觸控尺寸）沿用既有 `tailwind.config.js`。

## 配色
| 用途 | 色碼 | Tailwind |
|---|---|---|
| 底色（米白紙色） | `#f4efe6` | `bg-paper` |
| 搜尋欄、浮起區底 | `#faf7f0` | `bg-paper-raised` |
| 字（墨黑） | `#2a2622` | `text-elder-text` |
| 次要字 | `#6b6358` | `text-elder-subtext` |
| 分隔線（淡棕） | `#d9d0c0` | `border-rule` |
| 框線 | `#cfc5b3` | `border-rule-strong` |
| 主色（深青綠，只用在登入與主要按鈕） | `#2f5d62` | `text-brand` `border-brand` |
| 主色 hover | `#244a4e` | `brand-dark` |

不使用 Tailwind 預設藍與卡片陰影；分隔靠細線。

## 字型
- 魚名、品牌名：思源宋體（Noto Serif TC，網路載入，`font-display: swap`），載入前依序用 Source Han Serif TC、Songti TC、系統 serif。Tailwind：`font-serif`。
- 其餘文字沿用現有 sans。

## 字級與觸控（長者規格，不變）
- 字級 15／18／22／28px：`text-elder-aux`／`-body`／`-name`／`-title`。
- 魚詳細頁（#132）：說明、標籤、次要字一律 18px（`text-elder-body`）；頁尾版權行維持 15px。
- 觸控 48／56px：`min-h-touch-secondary`／`min-h-touch-primary`。

## 元件
- 導覽列：紙底、下方 1px 淡棕線；左品牌名（宋體 28px 桌機、22px 手機），右「登入」（主色、底線）或帳號鈕（主色細框圓鈕）。清單頁沒有返回鈕；其他頁返回鈕為主色細框。
- 魚卡（第 2 步）：手機一欄、圖高 190px、1px 框線；名字 22px 宋體，下方 1px 細線；桌機四欄。
- 搜尋欄（第 2 步）：高 52px、圓角、底 `#faf7f0`、框 `#cfc5b3`、字 18px。
- 回到頂端鈕（第 3 步）：右下角 56px 圓鈕。
- 魚詳細頁分頁列（#132）：手機沿用 `mobileGridClass` 排法（3 個一排、4 個兩欄、5 個上三下二、6 個三格兩列）；每格 48px 高、18px 字；選中為主色 2px 框加粗體，未選中為淡棕 1px 框加次要字色；桌機單排。
- 編輯入口（#132）：主色底線連結，高 56px 觸控區。刪除與合併維持紅色（外框紅、實心紅），圓角 8px、48px 高、18px 字。
- 頁尾：版權行與「網站資料皆為私人所有，不同意任何授權使用。」，15px 次要字，上方細線。

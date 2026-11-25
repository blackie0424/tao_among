// 魚島：部落的中文名（chung 確認的對照）。畫面上先寫族語，中文用括號附在後面。
const TRIBE_CHINESE_NAMES = {
  iraraley: '朗島',
  yayo: '椰油',
  iratay: '漁人',
  imowrod: '紅頭',
  ivalino: '野銀',
  iranmeilek: '東清',
}

/** 例如 yayo → 「yayo（椰油）」；不在對照表裡的代碼原樣顯示 */
export function islandTribeLabel(tribe) {
  const chinese = TRIBE_CHINESE_NAMES[tribe]
  return chinese ? `${tribe}（${chinese}）` : tribe
}

// 魚卡結果長條（#183）：選項依設定順序對應顏色（已用色盲檢查工具驗過相鄰色可分辨）。
// 淺色段對白底對比不足 3:1，所以文字一律寫在長條外（標籤、圖例），不寫在色塊裡。
const RESULT_COLORS = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4']
// 「我不知道」固定灰色，只給它用
export const UNKNOWN_RESULT_COLOR = '#94a3a8'

/** 選項的長條顏色：「我不知道」灰色，其他依它在選項清單的位置 */
export function islandResultColor(options, value, unknownValue) {
  if (value === unknownValue) return UNKNOWN_RESULT_COLOR
  return RESULT_COLORS[options.indexOf(value)] ?? UNKNOWN_RESULT_COLOR
}

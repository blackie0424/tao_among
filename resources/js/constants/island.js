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

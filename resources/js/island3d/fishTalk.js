// #232 拿著魚問家人：家人回哪一種（AC 9）、「你記的／這裡多數說」並排（AC 10）。
// 「有說法」用後端算好的 fish_speech[部落][魚].has_detail（同 #216 的「誰說的？」）；
// 多數的規則同後端 IslandNpcSpeech::leaders：筆數最多、平手全列（保留傳入順序），處理方式不算「我不知道」
import { islandTribeLabel } from '@/constants/island'

/** 自己沒記的那一題 */
export const NOT_RECORDED = '還沒記'
/** 這個部落這一題沒有有效回答 */
export const NOBODY_SAID = '還沒有人說'

const NOWHERE_LINE = '這條魚，全島還沒有人說過它是哪一類、怎麼處理。'
const COMPARED_QUESTIONS = ['food_category', 'processing_method']

const hasDetail = (fishSpeech, tribe, fishId) => fishSpeech[tribe]?.[fishId]?.has_detail === true

/**
 * 家人的回答：{ kind: known（這個部落有說法）| elsewhere（別的部落有）| nowhere（全島都沒有）, line 家人說的話 }。
 * tribes 是部落設定的順序（列出可以去問的部落時照這個排）
 */
export function familyAnswer({ fishId, tribe, tribes, fishSpeech }) {
  if (hasDetail(fishSpeech, tribe, fishId)) {
    return { kind: 'known', line: fishSpeech[tribe][fishId].speech }
  }
  const others = tribes.filter((other) => hasDetail(fishSpeech, other, fishId))
  if (others.length === 0) {
    return { kind: 'nowhere', line: NOWHERE_LINE }
  }
  return {
    kind: 'elsewhere',
    line: `我不知道這條魚。你去問 ${others.map(islandTribeLabel).join('、')}的人看看。`,
  }
}

/** 這個部落這一題的多數（筆數最多、平手全列）；處理方式不算「我不知道」 */
function leadersOf(choices, question, unknown) {
  const counted = (choices ?? []).filter(
    (choice) => !(question === 'processing_method' && choice.value === unknown)
  )
  if (counted.length === 0) {
    return []
  }
  const top = Math.max(...counted.map((choice) => choice.count))
  return counted.filter((choice) => choice.count === top).map((choice) => choice.value)
}

/**
 * 並排的兩列（分類、處理方式）：{ question, mine 自己記的（沒記為 null）, leaders 這裡的多數（沒有有效回答為空）,
 * differs 自己有記、這裡有多數、而且自己的選擇不在多數裡 }。
 * results 是 island_fish 那條魚的 results（題目 → 部落 → [{ value, count }]）
 */
export function comparisonRows({ my, results, tribe, unknown }) {
  return COMPARED_QUESTIONS.map((question) => {
    const mine = my?.[question] || null
    const leaders = leadersOf(results?.[question]?.[tribe], question, unknown)
    return {
      question,
      mine,
      leaders,
      differs: mine !== null && leaders.length > 0 && !leaders.includes(mine),
    }
  })
}

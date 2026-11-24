const NAME_SEPARATOR = '、'

/** 魚名依「、」拆成拼字：去頭尾空白、略過空字串、重複的只留第一個（與後端 FishNameSplitter 同規則）。 */
export function splitFishName(name) {
  const parts = String(name ?? '')
    .split(NAME_SEPARATOR)
    .map((part) => part.trim())
    .filter((part) => part !== '')
  return [...new Set(parts)]
}

/** 一個拼字底下的錄音，魚頁播哪一段：被指定為主要發音（is_primary）的，沒有就播最早（編號最小）的一段。 */
export function chooseRecording(audios) {
  const sorted = [...(audios ?? [])].sort((a, b) => a.id - b.id)
  return sorted.find((audio) => audio.is_primary) ?? sorted[0] ?? null
}

/**
 * 每個拼字在魚頁上播哪一段（見 chooseRecording）。沒有錄音的拼字不回傳。
 * @returns {{ spelling: string, audio: object }[]}
 */
export function pickSpellingRecordings(fish) {
  const audios = fish?.audios ?? []
  return splitFishName(fish?.name).flatMap((spelling) => {
    const chosen = chooseRecording(audios.filter((audio) => audio.spoken_name === spelling && audio.url))
    return chosen ? [{ spelling, audio: chosen }] : []
  })
}

const NAME_SEPARATOR = '、'

/** 魚名依「、」拆成拼字：去頭尾空白、略過空字串、重複的只留第一個（與後端 FishNameSplitter 同規則）。 */
export function splitFishName(name) {
  const parts = String(name ?? '')
    .split(NAME_SEPARATOR)
    .map((part) => part.trim())
    .filter((part) => part !== '')
  return [...new Set(parts)]
}

/**
 * 每個拼字在魚頁上播哪一段：有被「設為魚頁使用」指定的就播它，否則播該拼字最早（編號最小）的一段。
 * 沒有錄音的拼字不回傳。
 * @returns {{ spelling: string, audio: object }[]}
 */
export function pickSpellingRecordings(fish) {
  const audios = [...(fish?.audios ?? [])].sort((a, b) => a.id - b.id)
  return splitFishName(fish?.name).flatMap((spelling) => {
    const own = audios.filter((audio) => audio.spoken_name === spelling && audio.url)
    const chosen = own.find((audio) => audio.name === fish.audio_filename) ?? own[0]
    return chosen ? [{ spelling, audio: chosen }] : []
  })
}

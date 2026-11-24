import { describe, expect, it } from 'vitest'
import { pickSpellingRecordings, splitFishName } from '@/utils/fishSpellings'

const rec = (id, spoken_name, over = {}) => ({ id, name: `f${id}.m4a`, url: `/f${id}.m4a`, spoken_name, ...over })

describe('splitFishName', () => {
  it('依「、」拆開、去頭尾空白、略過空字串、重複只留第一個並保留順序', () => {
    expect(splitFishName(' Ivey、、 Ivay 、Ivey、')).toEqual(['Ivey', 'Ivay'])
    expect(splitFishName('Angsa')).toEqual(['Angsa'])
    expect(splitFishName('')).toEqual([])
    expect(splitFishName(null)).toEqual([])
  })

  it('只差大小寫視為不同拼字', () => {
    expect(splitFishName('Angsa、angsa')).toEqual(['Angsa', 'angsa'])
  })

  // 與後端 tests/Unit/Services/FishAudioRecordingServiceTest.php 的「去頭尾空白的字元與前端 trim() 一致」同一組案例
  it('去頭尾空白的字元與後端一致：全形空白、不換行空白、BOM 都要去掉', () => {
    expect(splitFishName('\u3000Ivey\u3000、Ivay')).toEqual(['Ivey', 'Ivay'])
    expect(splitFishName('\u00A0Ivey\u00A0、\uFEFFIvay\uFEFF')).toEqual(['Ivey', 'Ivay'])
    expect(splitFishName('\u2003Ivey\u2009、Ivay\n')).toEqual(['Ivey', 'Ivay'])
    expect(splitFishName('Iv\u3000ey')).toEqual(['Iv\u3000ey'])
    expect(splitFishName('\u3000、\u3000')).toEqual([])
  })
})

describe('pickSpellingRecordings', () => {
  it('每個拼字預設播最早（編號最小）的一段，不管陣列順序', () => {
    const fish = { name: 'Ivey、Ivay', audio_filename: null, audios: [rec(7, 'Ivey'), rec(3, 'Ivey'), rec(5, 'Ivay')] }
    const picked = pickSpellingRecordings(fish)
    expect(picked.map((p) => [p.spelling, p.audio.id])).toEqual([['Ivey', 3], ['Ivay', 5]])
  })

  it('該拼字底下有「設為魚頁使用」指定的那一段就播它', () => {
    const fish = { name: 'Ivey、Ivay', audio_filename: 'f7.m4a', audios: [rec(3, 'Ivey'), rec(7, 'Ivey'), rec(5, 'Ivay')] }
    expect(pickSpellingRecordings(fish).map((p) => p.audio.id)).toEqual([7, 5])
  })

  it('指定的那一段屬於別的拼字時，不影響這個拼字（仍播它自己最早的一段）', () => {
    const fish = { name: 'Ivey、Ivay', audio_filename: 'f5.m4a', audios: [rec(3, 'Ivey'), rec(5, 'Ivay')] }
    expect(pickSpellingRecordings(fish).map((p) => p.audio.id)).toEqual([3, 5])
  })

  it('魚名含全形空白：後端記成「Ivey」「Ivay」的錄音，魚頁兩個拼字都顯示播放鈕，不會漏掉', () => {
    const fish = { name: '\u3000Ivey\u3000、Ivay', audio_filename: null, audios: [rec(3, 'Ivey'), rec(5, 'Ivay')] }
    expect(pickSpellingRecordings(fish).map((p) => [p.spelling, p.audio.id])).toEqual([['Ivey', 3], ['Ivay', 5]])
  })

  it('沒有錄音的拼字不回傳；未對應（沒有拼字）的錄音、沒有網址的錄音不算', () => {
    const fish = {
      name: 'Ivey、Ivay',
      audios: [rec(1, null), rec(2, 'Ivey', { url: null }), rec(3, 'Ivey'), rec(4, '不在魚名裡的拼字')],
    }
    expect(pickSpellingRecordings(fish).map((p) => [p.spelling, p.audio.id])).toEqual([['Ivey', 3]])
  })

  it('完全沒有錄音或沒有 audios 時回空陣列', () => {
    expect(pickSpellingRecordings({ name: 'Ivey、Ivay', audios: [] })).toEqual([])
    expect(pickSpellingRecordings({ name: 'Ivey、Ivay' })).toEqual([])
  })
})

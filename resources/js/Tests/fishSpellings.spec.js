import { describe, expect, it } from 'vitest'
import { chooseRecording, pickSpellingRecordings, splitFishName } from '@/utils/fishSpellings'

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
    // trim() 不會去掉、PHP 的 \s 會去掉的字元：兩邊都必須保留
    expect(splitFishName('\u0085Ivey\u0085、Ivay')).toEqual(['\u0085Ivey\u0085', 'Ivay'])
    expect(splitFishName('\u180EIvey、Ivay')).toEqual(['\u180EIvey', 'Ivay'])
    expect(splitFishName('\u3000、\u3000')).toEqual([])
  })
})

describe('pickSpellingRecordings', () => {
  it('每個拼字預設播最早（編號最小）的一段，不管陣列順序', () => {
    const fish = { name: 'Ivey、Ivay', audio_filename: null, audios: [rec(7, 'Ivey'), rec(3, 'Ivey'), rec(5, 'Ivay')] }
    const picked = pickSpellingRecordings(fish)
    expect(picked.map((p) => [p.spelling, p.audio.id])).toEqual([['Ivey', 3], ['Ivay', 5]])
  })

  it('該拼字底下有指定的主要發音（is_primary）就播它', () => {
    const fish = { name: 'Ivey、Ivay', audios: [rec(3, 'Ivey'), rec(7, 'Ivey', { is_primary: true }), rec(5, 'Ivay')] }
    expect(pickSpellingRecordings(fish).map((p) => p.audio.id)).toEqual([7, 5])
  })

  it('各拼字各自判斷：一個拼字有指定、另一個沒有（播最早的一段），互不影響', () => {
    const fish = {
      name: 'Ivey、Ivay',
      audios: [rec(3, 'Ivey'), rec(7, 'Ivey', { is_primary: true }), rec(5, 'Ivay'), rec(9, 'Ivay')],
    }
    expect(pickSpellingRecordings(fish).map((p) => [p.spelling, p.audio.id])).toEqual([['Ivey', 7], ['Ivay', 5]])
  })

  it('魚的目前發音檔（audio_filename）不影響各拼字的選擇', () => {
    const fish = { name: 'Ivey、Ivay', audio_filename: 'f7.m4a', audios: [rec(3, 'Ivey'), rec(7, 'Ivey'), rec(5, 'Ivay')] }
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

describe('chooseRecording', () => {
  it('有主要發音就選它，否則選編號最小的一段；沒有錄音回 null', () => {
    expect(chooseRecording([rec(7, 'a'), rec(3, 'a')]).id).toBe(3)
    expect(chooseRecording([rec(3, 'a'), rec(7, 'a', { is_primary: true })]).id).toBe(7)
    expect(chooseRecording([])).toBeNull()
    expect(chooseRecording(undefined)).toBeNull()
  })
})

import { describe, expect, it } from 'vitest'
import {
  assembleAudioChunks,
  formatBytes,
  formatDuration,
  selectRecordingMimeType,
} from '../utils/fieldSurveyTechnicalAudio.js'

describe('field survey technical audio helpers', () => {
  it('優先選擇 audio/mp4', () => {
    const isSupported = (type) => ['audio/mp4', 'audio/aac'].includes(type)

    expect(selectRecordingMimeType(isSupported)).toBe('audio/mp4')
  })

  it('audio/mp4 不支援時降級至 audio/aac', () => {
    const isSupported = (type) => type === 'audio/aac'

    expect(selectRecordingMimeType(isSupported)).toBe('audio/aac')
  })

  it('沒有支援格式時回傳 null', () => {
    expect(selectRecordingMimeType(() => false)).toBeNull()
  })

  it('依 sequence 組回多個音訊 chunk 並保留 MIME', async () => {
    const chunks = [
      { sequence: 2, blob: new Blob(['B'], { type: 'audio/mp4' }) },
      { sequence: 1, blob: new Blob(['A'], { type: 'audio/mp4' }) },
      { sequence: 3, blob: new Blob(['C'], { type: 'audio/mp4' }) },
    ]

    const result = assembleAudioChunks(chunks, 'audio/mp4')

    expect(result.type).toBe('audio/mp4')
    const text = await new Promise((resolve) => {
      const reader = new FileReader()
      reader.addEventListener('load', () => resolve(reader.result))
      reader.readAsText(result)
    })
    expect(text).toBe('ABC')
  })

  it('0 個 chunk 仍建立指定 MIME 的空 Blob', () => {
    const result = assembleAudioChunks([], 'audio/mp4')

    expect(result.size).toBe(0)
    expect(result.type).toBe('audio/mp4')
  })

  it('格式化跨分鐘時長', () => {
    expect(formatDuration(0)).toBe('00:00')
    expect(formatDuration(65_000)).toBe('01:05')
    expect(formatDuration(3_605_000)).toBe('60:05')
  })

  it('格式化容量邊界', () => {
    expect(formatBytes(0)).toBe('0 B')
    expect(formatBytes(1023)).toBe('1023 B')
    expect(formatBytes(1024)).toBe('1.00 KB')
    expect(formatBytes(1024 * 1024)).toBe('1.00 MB')
  })
})

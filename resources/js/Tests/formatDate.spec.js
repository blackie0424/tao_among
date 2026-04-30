import { describe, it, expect } from 'vitest'
import { formatDate, formatDateTime } from '@/utils/formatDate'

// ──────────────────────────────────────────────
// formatDate
// ──────────────────────────────────────────────
describe('formatDate', () => {
  it('應將 ISO 日期字串格式化為 YYYY/MM/DD', () => {
    expect(formatDate('2024-03-05T00:00:00.000Z')).toBe('2024/03/05')
  })

  it('月份與日期應補零（zero-padding）', () => {
    expect(formatDate('2024-01-07T00:00:00.000Z')).toBe('2024/01/07')
  })

  it('null 應回傳空字串', () => {
    expect(formatDate(null)).toBe('')
  })

  it('undefined 應回傳空字串', () => {
    expect(formatDate(undefined)).toBe('')
  })

  it('空字串應回傳空字串', () => {
    expect(formatDate('')).toBe('')
  })

  it('無效日期字串應原樣回傳', () => {
    expect(formatDate('not-a-date')).toBe('not-a-date')
  })
})

// ──────────────────────────────────────────────
// formatDateTime
// ──────────────────────────────────────────────
describe('formatDateTime', () => {
  it('null 應回傳空字串', () => {
    expect(formatDateTime(null)).toBe('')
  })

  it('undefined 應回傳空字串', () => {
    expect(formatDateTime(undefined)).toBe('')
  })

  it('空字串應回傳空字串', () => {
    expect(formatDateTime('')).toBe('')
  })

  it('有效日期應回傳包含日期資訊的字串', () => {
    const result = formatDateTime('2024-03-05T10:30:00.000Z')
    expect(typeof result).toBe('string')
    expect(result.length).toBeGreaterThan(0)
  })
})

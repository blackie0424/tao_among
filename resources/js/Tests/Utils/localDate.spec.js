import { describe, expect, it } from 'vitest'
import { formatLocalDate } from '@/utils/localDate'

describe('formatLocalDate', () => {
  it('uses the browser local calendar date near midnight', () => {
    expect(formatLocalDate(new Date(2026, 9, 8, 0, 30))).toBe('2026-10-08')
  })

  it('keeps the local date at the end of the year', () => {
    expect(formatLocalDate(new Date(2026, 11, 31, 23, 59))).toBe('2026-12-31')
  })
})

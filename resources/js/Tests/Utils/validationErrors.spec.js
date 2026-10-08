import { describe, expect, it } from 'vitest'
import { firstValidationError } from '@/utils/validationErrors'

describe('firstValidationError', () => {
  it('returns the first readable string from an Inertia error array', () => {
    expect(firstValidationError({ capture_date: ['日期不可晚於今天'] })).toBe('日期不可晚於今天')
  })

  it('keeps a plain validation message readable', () => {
    expect(firstValidationError({ name: '請輸入地名' })).toBe('請輸入地名')
  })
})

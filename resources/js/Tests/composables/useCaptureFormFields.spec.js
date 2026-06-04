import { describe, it, expect } from 'vitest'
import { useCaptureFormFields } from '@/composables/useCaptureFormFields'

describe('useCaptureFormFields', () => {
  // ─── 初始狀態 ────────────────────────────────────────────────────────────

  it('無 initialData 時，所有欄位預設為空字串', () => {
    const { form } = useCaptureFormFields()
    expect(form.tribe).toBe('')
    expect(form.location).toBe('')
    expect(form.capture_method).toBe('')
    expect(form.capture_date).toBe('')
    expect(form.notes).toBe('')
  })

  it('傳入 initialData 時，正確初始化欄位值', () => {
    const { form } = useCaptureFormFields({
      tribe: 'ivalino',
      location: '小蘭嶼',
      capture_method: 'mapazat',
      capture_date: '2026-05-20',
      notes: '備註',
    })
    expect(form.tribe).toBe('ivalino')
    expect(form.location).toBe('小蘭嶼')
    expect(form.capture_method).toBe('mapazat')
    expect(form.capture_date).toBe('2026-05-20')
    expect(form.notes).toBe('備註')
  })

  it('initialData 只提供部分欄位時，其餘欄位為空字串', () => {
    const { form } = useCaptureFormFields({ tribe: 'yayo' })
    expect(form.tribe).toBe('yayo')
    expect(form.location).toBe('')
    expect(form.capture_date).toBe('')
  })

  it('errors 初始狀態為空物件', () => {
    const { errors } = useCaptureFormFields()
    expect(errors.value).toEqual({})
  })

  // ─── validateCaptureFields ───────────────────────────────────────────────

  it('tribe 為空時 validateCaptureFields 回傳 false，並設定 tribe 錯誤', () => {
    const { form, errors, validateCaptureFields } = useCaptureFormFields()
    form.location = '小蘭嶼'
    form.capture_date = '2026-05-20'

    const result = validateCaptureFields()
    expect(result).toBe(false)
    expect(errors.value.tribe).toBeTruthy()
  })

  it('location 為空時 validateCaptureFields 回傳 false，並設定 location 錯誤', () => {
    const { form, errors, validateCaptureFields } = useCaptureFormFields()
    form.tribe = 'ivalino'
    form.capture_date = '2026-05-20'

    const result = validateCaptureFields()
    expect(result).toBe(false)
    expect(errors.value.location).toBeTruthy()
  })

  it('capture_date 為空時 validateCaptureFields 回傳 false，並設定 capture_date 錯誤', () => {
    const { form, errors, validateCaptureFields } = useCaptureFormFields()
    form.tribe = 'ivalino'
    form.location = '小蘭嶼'

    const result = validateCaptureFields()
    expect(result).toBe(false)
    expect(errors.value.capture_date).toBeTruthy()
  })

  it('capture_method 為空時 validateCaptureFields 回傳 false，並設定 capture_method 錯誤', () => {
    const { form, errors, validateCaptureFields } = useCaptureFormFields()
    form.tribe = 'ivalino'
    form.location = '小蘭嶼'
    form.capture_date = '2026-05-20'

    const result = validateCaptureFields()
    expect(result).toBe(false)
    expect(errors.value.capture_method).toBeTruthy()
  })

  it('所有必填欄位填寫後 validateCaptureFields 回傳 true，errors 清空', () => {
    const { form, errors, validateCaptureFields } = useCaptureFormFields()
    form.tribe = 'ivalino'
    form.location = '小蘭嶼'
    form.capture_date = '2026-05-20'
    form.capture_method = 'mapazat'

    // 先製造錯誤
    errors.value = { tribe: '請選擇' }

    const result = validateCaptureFields()
    expect(result).toBe(true)
    expect(errors.value).toEqual({})
  })

  // ─── buildFormData ───────────────────────────────────────────────────────

  it('buildFormData 回傳包含所有欄位的純物件', () => {
    const { form, buildFormData } = useCaptureFormFields({
      tribe: 'iraraley',
      location: '漁港',
      capture_method: 'mamasil',
      capture_date: '2026-06-01',
      notes: '天氣晴',
    })
    const data = buildFormData()
    expect(data).toEqual({
      tribe: 'iraraley',
      location: '漁港',
      capture_method: 'mamasil',
      capture_date: '2026-06-01',
      notes: '天氣晴',
    })
  })

  it('buildFormData 回傳的是純物件，不是 reactive proxy', () => {
    const { buildFormData } = useCaptureFormFields()
    const data = buildFormData()
    // 可以安全 JSON.stringify 而不報錯
    expect(() => JSON.stringify(data)).not.toThrow()
  })
})

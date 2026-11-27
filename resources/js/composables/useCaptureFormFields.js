import { reactive, ref } from 'vue'
import { framingToFields } from '@/composables/useImageFraming'

/**
 * @param {object} initialData
 */
export function useCaptureFormFields(initialData = {}) {
  const form = reactive({
    tribe: initialData.tribe ?? '',
    location: initialData.location ?? '',
    capture_method: initialData.capture_method ?? '',
    capture_date: initialData.capture_date ?? '',
    notes: initialData.notes ?? '',
    framing: initialData.framing ?? null, // 照片調整值 { x, y, zoom, rotation }；null＝沒調整
  })

  const errors = ref({})

  function validateCaptureFields() {
    const e = {}
    if (!form.tribe) e.tribe = '請選擇部落'
    if (!form.location) e.location = '請輸入地點'
    if (!form.capture_date) e.capture_date = '請選擇日期'
    if (!form.capture_method) e.capture_method = '請選擇捕獲方式'

    if (Object.keys(e).length) {
      errors.value = e
      return false
    }
    errors.value = {}
    return true
  }

  function buildFormData() {
    return {
      tribe: form.tribe,
      location: form.location,
      capture_method: form.capture_method,
      capture_date: form.capture_date,
      notes: form.notes,
      ...framingToFields(form.framing),
    }
  }

  return { form, errors, validateCaptureFields, buildFormData }
}

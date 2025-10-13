/**
 * 表單驗證 Composable
 * 提供即時表單驗證功能
 */

import { ref, reactive, computed, watch } from 'vue'

export function useFormValidation(initialForm = {}, validationRules = {}) {
  // 表單資料
  const form = reactive({ ...initialForm })

  // 錯誤狀態
  const errors = ref({})
  const touched = ref({})

  // 驗證狀態
  const isValidating = ref(false)
  const hasErrors = computed(() => Object.keys(errors.value).length > 0)
  const isValid = computed(() => !hasErrors.value && Object.keys(touched.value).length > 0)

  /**
   * 驗證單個欄位
   * @param {string} field - 欄位名稱
   * @param {any} value - 欄位值
   * @returns {string|null} 錯誤訊息或 null
   */
  function validateField(field, value) {
    const rules = validationRules[field]
    if (!rules) return null

    for (const rule of rules) {
      const error = rule(value, form)
      if (error) return error
    }

    return null
  }

  /**
   * 驗證所有欄位
   * @returns {boolean} 是否通過驗證
   */
  function validateAll() {
    isValidating.value = true
    const newErrors = {}

    Object.keys(validationRules).forEach((field) => {
      const error = validateField(field, form[field])
      if (error) {
        newErrors[field] = error
      }
      touched.value[field] = true
    })

    errors.value = newErrors
    isValidating.value = false

    return Object.keys(newErrors).length === 0
  }

  /**
   * 清除欄位錯誤
   * @param {string} field - 欄位名稱
   */
  function clearFieldError(field) {
    if (errors.value[field]) {
      delete errors.value[field]
      errors.value = { ...errors.value }
    }
  }

  /**
   * 清除所有錯誤
   */
  function clearErrors() {
    errors.value = {}
    touched.value = {}
  }

  /**
   * 設置欄位為已觸碰
   * @param {string} field - 欄位名稱
   */
  function touchField(field) {
    touched.value[field] = true
  }

  /**
   * 即時驗證欄位
   * @param {string} field - 欄位名稱
   */
  function validateFieldRealtime(field) {
    if (!touched.value[field]) return

    const error = validateField(field, form[field])
    if (error) {
      errors.value[field] = error
    } else {
      clearFieldError(field)
    }
  }

  /**
   * 設置伺服器端錯誤
   * @param {object} serverErrors - 伺服器端錯誤
   */
  function setServerErrors(serverErrors) {
    errors.value = { ...errors.value, ...serverErrors }
  }

  /**
   * 重置表單
   */
  function resetForm() {
    Object.keys(form).forEach((key) => {
      form[key] = initialForm[key] || ''
    })
    clearErrors()
  }

  // 監聽表單變化，進行即時驗證
  Object.keys(validationRules).forEach((field) => {
    watch(
      () => form[field],
      () => {
        validateFieldRealtime(field)
      }
    )
  })

  return {
    form,
    errors,
    touched,
    isValidating,
    hasErrors,
    isValid,
    validateField,
    validateAll,
    clearFieldError,
    clearErrors,
    touchField,
    validateFieldRealtime,
    setServerErrors,
    resetForm,
  }
}

// 常用驗證規則
export const validationRules = {
  required:
    (message = '此欄位為必填') =>
    (value) => {
      if (!value || (typeof value === 'string' && !value.trim())) {
        return message
      }
      return null
    },

  minLength: (min, message) => (value) => {
    if (value && value.length < min) {
      return message || `最少需要 ${min} 個字元`
    }
    return null
  },

  maxLength: (max, message) => (value) => {
    if (value && value.length > max) {
      return message || `最多只能 ${max} 個字元`
    }
    return null
  },

  email:
    (message = '請輸入有效的電子郵件地址') =>
    (value) => {
      if (value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
        return message
      }
      return null
    },

  url:
    (message = '請輸入有效的網址') =>
    (value) => {
      if (value && !/^https?:\/\/.+/.test(value)) {
        return message
      }
      return null
    },

  fileSize: (maxSize, message) => (file) => {
    if (file && file.size > maxSize) {
      return message || `檔案大小不能超過 ${Math.round(maxSize / 1024 / 1024)}MB`
    }
    return null
  },

  fileType: (allowedTypes, message) => (file) => {
    if (file && !allowedTypes.includes(file.type)) {
      return message || `只允許 ${allowedTypes.join(', ')} 格式的檔案`
    }
    return null
  },

  audioFile:
    (message = '請選擇有效的音頻檔案') =>
    (file) => {
      if (file && !file.type.startsWith('audio/')) {
        return message
      }
      return null
    },
}

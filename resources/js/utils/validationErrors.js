export function firstValidationError(errors = {}) {
  const error = Object.values(errors)[0]

  return Array.isArray(error) ? (error[0] || '') : (error || '')
}

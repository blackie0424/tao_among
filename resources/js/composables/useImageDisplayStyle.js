/**
 * Compute imgStyle object for position/scale applied to object-cover images.
 * @param {string|null} position - center | top | bottom | left | right
 * @param {number|null} scale - 1.0–3.0
 * @returns {object} style object for :style binding
 */
export function buildImageDisplayStyle(position, scale) {
  const style = {}
  const pos = position || 'center'
  const s = parseFloat(scale) || 1

  if (pos !== 'center') {
    style.objectPosition = pos
  }
  if (s !== 1) {
    style.transform = `scale(${s})`
    style.transformOrigin = pos
  }
  return style
}

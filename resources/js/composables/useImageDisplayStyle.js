/**
 * Compute imgStyle object for position/scale applied to object-cover images.
 * @param {string|null} position - center | top | bottom | left | right
 * @param {number|null} scale - 0.8–2.0
 * @returns {object} style object for :style binding
 */
export function buildImageDisplayStyle(position, scale) {
  const pos = position || 'center'
  const s = parseFloat(scale) || 1

  return {
    objectPosition: pos,
    transform: `scale(${s})`,
    transformOrigin: 'center center',
    transition: 'none',
  }
}

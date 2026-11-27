/**
 * 照片調整值（魚身中心、放大、旋轉）的顯示規則。
 * 原圖不動，只算出「照片放在框裡的位置與大小」，各頁用同一套。
 *
 * 座標以「已順時針旋轉後、使用者看到的照片」為準：左上為原點、x 向右、y 向下，單位為照片寬高的 %。
 */

const DEFAULT_FRAMING = { x: 50, y: 50, zoom: 1, rotation: 0 }
const SIDEWAYS_ROTATIONS = [90, 270]
const FULL_PERCENT = 100

const clamp = (value, min, max) => Math.min(Math.max(value, min), max)
const toPercent = (value, total) => (value / total) * FULL_PERCENT

/** 轉 90 或 270 度時，旋轉後的寬高對調 */
export function rotatedSize(photoWidth, photoHeight, rotation) {
  return SIDEWAYS_ROTATIONS.includes(rotation)
    ? { width: photoHeight, height: photoWidth }
    : { width: photoWidth, height: photoHeight }
}

/**
 * @param {{photoWidth:number, photoHeight:number, frameWidth:number, frameHeight:number,
 *          framing:{x:number,y:number,zoom:number,rotation:number}|null}} input
 * @returns {{stage:{widthPct:number,heightPct:number,leftPct:number,topPct:number},
 *            image:{widthPct:number,heightPct:number,leftPct:number,topPct:number},
 *            rotation:number}}
 *   stage：旋轉後的照片在框內的矩形；image：套上旋轉前的 <img> 本身的矩形（旋轉繞自己的中心）。皆為框寬高的 %。
 */
export function computeFramedLayout({ photoWidth, photoHeight, frameWidth, frameHeight, framing }) {
  const { x, y, zoom, rotation } = framing ?? DEFAULT_FRAMING
  const rotated = rotatedSize(photoWidth, photoHeight, rotation)

  // cover 基準縮放 × 放大倍率
  const scale = Math.max(frameWidth / rotated.width, frameHeight / rotated.height) * zoom
  const stageWidth = rotated.width * scale
  const stageHeight = rotated.height * scale

  // 魚身中心放在框中央，再夾限到照片邊緣不離開框邊（不露白）
  const stageLeft = clamp(frameWidth / 2 - (x / FULL_PERCENT) * stageWidth, frameWidth - stageWidth, 0)
  const stageTop = clamp(frameHeight / 2 - (y / FULL_PERCENT) * stageHeight, frameHeight - stageHeight, 0)

  const imageWidth = photoWidth * scale
  const imageHeight = photoHeight * scale
  const imageLeft = stageLeft + stageWidth / 2 - imageWidth / 2
  const imageTop = stageTop + stageHeight / 2 - imageHeight / 2

  return {
    stage: {
      widthPct: toPercent(stageWidth, frameWidth),
      heightPct: toPercent(stageHeight, frameHeight),
      leftPct: toPercent(stageLeft, frameWidth),
      topPct: toPercent(stageTop, frameHeight),
    },
    image: {
      widthPct: toPercent(imageWidth, frameWidth),
      heightPct: toPercent(imageHeight, frameHeight),
      leftPct: toPercent(imageLeft, frameWidth),
      topPct: toPercent(imageTop, frameHeight),
    },
    rotation,
  }
}

/** 把版面換成 <img> 的行內樣式（框需為 position: relative 且 overflow: hidden） */
export function framedImageStyle(layout) {
  const { image, rotation } = layout

  return {
    position: 'absolute',
    maxWidth: 'none',
    objectFit: 'fill',
    width: `${image.widthPct}%`,
    height: `${image.heightPct}%`,
    left: `${image.leftPct}%`,
    top: `${image.topPct}%`,
    transform: rotation ? `rotate(${rotation}deg)` : 'none',
    transition: 'none',
  }
}

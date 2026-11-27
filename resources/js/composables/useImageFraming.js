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

// ── 編輯器用：拖曳、放大、旋轉、存檔欄位 ─────────────────────────

export const MIN_ZOOM = 1
export const MAX_ZOOM = 3
const ROTATION_STEP = 90
const FULL_TURN = 360
const DECIMALS = 100

const round = (value) => Math.round(value * DECIMALS) / DECIMALS

const normalize = (framing) => framing ?? DEFAULT_FRAMING

const layoutOf = (framing, photo, frame) =>
  computeFramedLayout({
    photoWidth: photo.width,
    photoHeight: photo.height,
    frameWidth: frame.width,
    frameHeight: frame.height,
    framing: normalize(framing),
  })

/** 目前實際在框中央的照片點（夾限後，單位為旋轉後照片寬高的 %）；拖曳與放大後寫回的是它，不是手指指的點 */
export function effectiveCenter(layout) {
  const { stage } = layout

  return {
    x: round(((FULL_PERCENT / 2 - stage.leftPct) / stage.widthPct) * FULL_PERCENT),
    y: round(((FULL_PERCENT / 2 - stage.topPct) / stage.heightPct) * FULL_PERCENT),
  }
}

/** 拖曳：照片跟著手指移動 dx、dy（像素），所以魚身中心往反方向移動 */
export function dragFraming(framing, photo, frame, dx, dy) {
  const base = { ...normalize(framing), ...effectiveCenter(layoutOf(framing, photo, frame)) }
  const { stage } = layoutOf(base, photo, frame)
  const moved = {
    ...base,
    x: clamp(base.x - (dx / ((stage.widthPct / FULL_PERCENT) * frame.width)) * FULL_PERCENT, 0, FULL_PERCENT),
    y: clamp(base.y - (dy / ((stage.heightPct / FULL_PERCENT) * frame.height)) * FULL_PERCENT, 0, FULL_PERCENT),
  }

  return { ...moved, ...effectiveCenter(layoutOf(moved, photo, frame)) }
}

/** 放大：保持目前在框中央的點不動 */
export function zoomFraming(framing, photo, frame, zoom) {
  const center = effectiveCenter(layoutOf(framing, photo, frame))
  const zoomed = { ...normalize(framing), ...center, zoom: round(clamp(zoom, MIN_ZOOM, MAX_ZOOM)) }

  return { ...zoomed, ...effectiveCenter(layoutOf(zoomed, photo, frame)) }
}

/** 旋轉：座標以旋轉後的照片為準，所以中心與放大重設為預設，只換角度 */
export function rotateFraming(framing, direction) {
  const rotation = (normalize(framing).rotation + direction * ROTATION_STEP + FULL_TURN) % FULL_TURN

  return { ...DEFAULT_FRAMING, rotation }
}

const FRAMING_FIELD_NAMES = { x: 'image_focus_x', y: 'image_focus_y', zoom: 'image_zoom', rotation: 'image_rotation' }

/** 送給後端的四個欄位；沒調整（null）＝四個 null＝還原 */
export function framingToFields(framing) {
  return Object.fromEntries(
    Object.entries(FRAMING_FIELD_NAMES).map(([key, field]) => [field, framing ? framing[key] : null])
  )
}

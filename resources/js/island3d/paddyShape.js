// 魚島 3D：水芋田的樣子（#211）——好幾小塊田拼成一片（規格修訂 1），小塊之間留田埂，
// 越後面的一排越高；每小塊有土、水面、靠低處的一排石頭、芋頭。
// 只算位置與大小（以整片的中心為原點、正面朝 +z），不依賴 three.js；繪圖在 terrain.js。

export const PADDY_COLORS = {
  ridge: '#3d7f52',
  soil: '#6b5a45',
  water: '#6fb7c9',
  stones: ['#9aa0a6', '#7d848b'],
  leaves: ['#3f9a3f', '#57b04a'],
  stem: '#4f8a3a',
}

/** 拼法：[寬的塊數, 深的塊數] */
export const PADDY_LAYOUTS = {
  '2x2': [2, 2],
  '3x3': [3, 3],
  '1x3': [3, 1],
  '2x3': [3, 2],
}

// 一小塊田的大小、小塊之間的田埂、外圍留的寬
const FIELD = { width: 4, depth: 2.6 }
const FIELD_GAP = 0.6
const EDGE = 0.3
// 每往後（-z）一排高這麼多
const ROW_STEP = 0.15
// 最前一排的土有這麼厚（往後每排再加上 ROW_STEP）
const SOIL_THICKNESS = 0.04
// 水面比那一塊的土頂高這麼一點點，才不會被土蓋住
const WATER_LIFT = 0.01
// 水面左右與後面留的土邊
const SIDE_RIDGE = 0.2
const BACK_RIDGE = 0.1
// 石頭田埂：一排的深度、每顆的間距、大小
const STONE_ROW_DEPTH = 0.3
const STONE_SPACING = 0.35
const STONE_WIDTH = { min: 0.3, spread: 0.12 }
const STONE_HEIGHT = { min: 0.18, spread: 0.1 }
const STONE_DEPTH = 0.26
// 芋頭：每小塊 2 排、每排 3 株，離水面邊緣留一點
const TARO_ROWS = 2
const TARO_COLUMNS = 3
const TARO_MARGIN = 0.3
const TARO_SCALE = { min: 0.7, spread: 0.25 }
// 固定的亂數種子：每次畫出來都一樣
const SEED = 7
const LCG_MULTIPLIER = 16807
const LCG_MODULUS = 2147483647

/** 固定種子的亂數（0～1） */
function createRandom(seed) {
  let state = seed
  return () => {
    state = (state * LCG_MULTIPLIER) % LCG_MODULUS
    return state / LCG_MODULUS
  }
}

/** n 塊排成一排、中間留田埂的總長 */
const spanOf = (count, size) => count * size + (count - 1) * FIELD_GAP

/** 拼法 layout 的整片佔地：{ width, depth } */
export function paddySize([columns, rows]) {
  return {
    width: spanOf(columns, FIELD.width) + EDGE * 2,
    depth: spanOf(rows, FIELD.depth) + EDGE * 2,
  }
}

/** 第 column 欄、第 row 排（0 在最前面 +z）的小塊：位置、高度、土頂、水面 */
function fieldAt(column, row, [columns, rows]) {
  const x = -spanOf(columns, FIELD.width) / 2 + FIELD.width / 2 + column * (FIELD.width + FIELD_GAP)
  const z = spanOf(rows, FIELD.depth) / 2 - FIELD.depth / 2 - row * (FIELD.depth + FIELD_GAP)
  const level = row * ROW_STEP
  const soilTop = level + SOIL_THICKNESS
  const waterDepth = FIELD.depth - STONE_ROW_DEPTH - BACK_RIDGE
  return {
    column,
    row,
    x,
    z,
    ...FIELD,
    level,
    soilTop,
    water: {
      x,
      z: z - FIELD.depth / 2 + BACK_RIDGE + waterDepth / 2,
      width: FIELD.width - SIDE_RIDGE * 2,
      depth: waterDepth,
      level: soilTop + WATER_LIFT,
    },
  }
}

/** 一小塊靠 +z 那一邊的一排石頭，從左排到右 */
function stoneRow(field, index, random) {
  const count = Math.floor(field.width / STONE_SPACING)
  const rowZ = field.z + field.depth / 2 - STONE_ROW_DEPTH / 2
  return Array.from({ length: count }, (_, k) => ({
    field: index,
    x: field.x - field.width / 2 + (k + 0.5) * (field.width / count),
    z: rowZ,
    level: field.soilTop,
    width: Math.min(STONE_SPACING, STONE_WIDTH.min + random() * STONE_WIDTH.spread),
    height: STONE_HEIGHT.min + random() * STONE_HEIGHT.spread,
    depth: STONE_DEPTH,
    color: PADDY_COLORS.stones[random() < 0.5 ? 0 : 1],
  }))
}

/** 一小塊水面裡的芋頭：排成格子，每株大小與朝向不同 */
function taroIn(field, index, random) {
  const { water } = field
  const usableWidth = water.width - TARO_MARGIN * 2
  const usableDepth = water.depth - TARO_MARGIN * 2
  return Array.from({ length: TARO_ROWS * TARO_COLUMNS }, (_, n) => ({
    field: index,
    x: water.x - usableWidth / 2 + (((n % TARO_COLUMNS) + 0.5) * usableWidth) / TARO_COLUMNS,
    z: water.z - usableDepth / 2 + ((Math.floor(n / TARO_COLUMNS) + 0.5) * usableDepth) / TARO_ROWS,
    level: water.level,
    scale: TARO_SCALE.min + random() * TARO_SCALE.spread,
    turn: random() * Math.PI * 2,
  }))
}

/**
 * 拼法 layout（[寬的塊數, 深的塊數]）的一片水田（以整片的中心為原點）：
 * { fields: [{ column, row, x, z, width, depth, level, soilTop 土頂, water: { x, z, width, depth, level } }],
 *   stones: [{ field 第幾小塊, x, z, level 底部高度, width, height, depth, color }],
 *   taro: [{ field, x, z, level 站的高度, scale 大小, turn 朝向（弧度） }] }
 */
export function paddyShape(layout) {
  const [columns, rows] = layout
  const random = createRandom(SEED)
  const fields = Array.from({ length: columns * rows }, (_, i) =>
    fieldAt(i % columns, Math.floor(i / columns), layout)
  )
  return {
    fields,
    stones: fields.flatMap((field, i) => stoneRow(field, i, random)),
    taro: fields.flatMap((field, i) => taroIn(field, i, random)),
  }
}

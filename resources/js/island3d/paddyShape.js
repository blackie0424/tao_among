// 魚島 3D：水芋田的樣子（#211）——3 層梯田、每層靠低處的一排石頭田埂、田裡的芋頭。
// 只算位置與大小（以田的中心為原點、正面朝 +z），不依賴 three.js；繪圖在 terrain.js。

export const PADDY_COLORS = {
  soil: '#6b5a45',
  water: '#6fb7c9',
  stones: ['#9aa0a6', '#7d848b'],
  leaves: ['#3f9a3f', '#57b04a'],
  stem: '#4f8a3a',
}

const TERRACES = 3
// 每往後（-z）一層高這麼多
const TERRACE_STEP = 0.15
// 最低一層的土有這麼厚（往後每層再加上梯田的高度差）
const SOIL_THICKNESS = 0.04
// 水面比那一層的土頂高這麼一點點，才不會被土蓋住
const WATER_LIFT = 0.01
// 水面左右留的田埂寬
const SIDE_RIDGE = 0.25
// 石頭田埂：一排的深度、每顆的間距、大小
const STONE_ROW_DEPTH = 0.3
const STONE_SPACING = 0.35
const STONE_WIDTH = { min: 0.3, spread: 0.12 }
const STONE_HEIGHT = { min: 0.18, spread: 0.1 }
const STONE_DEPTH = 0.26
// 芋頭：每層 2 排、每排 4 株，離水面邊緣留一點
const TARO_ROWS = 2
const TARO_COLUMNS = 4
const TARO_MARGIN = 0.25
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

/** 第 i 層（0 最低、在 +z 那一邊）：位置、高度、水面 */
function terraceAt(i, width, depth) {
  const terraceDepth = depth / TERRACES
  const z = depth / 2 - (i + 0.5) * terraceDepth
  const level = i * TERRACE_STEP
  const soilTop = level + SOIL_THICKNESS
  const waterDepth = terraceDepth - STONE_ROW_DEPTH
  return {
    x: 0,
    z,
    width,
    depth: terraceDepth,
    level,
    soilTop,
    water: {
      x: 0,
      z: z - STONE_ROW_DEPTH / 2,
      width: width - SIDE_RIDGE * 2,
      depth: waterDepth,
      level: soilTop + WATER_LIFT,
    },
  }
}

/** 一層靠 +z 那一邊的一排石頭，從左排到右 */
function stoneRow(terrace, index, random) {
  const count = Math.floor(terrace.width / STONE_SPACING)
  const rowZ = terrace.z + terrace.depth / 2 - STONE_ROW_DEPTH / 2
  return Array.from({ length: count }, (_, k) => {
    const width = Math.min(STONE_SPACING, STONE_WIDTH.min + random() * STONE_WIDTH.spread)
    return {
      terrace: index,
      x: -terrace.width / 2 + (k + 0.5) * (terrace.width / count),
      z: rowZ,
      level: terrace.level,
      width,
      height: STONE_HEIGHT.min + random() * STONE_HEIGHT.spread,
      depth: STONE_DEPTH,
      color: PADDY_COLORS.stones[random() < 0.5 ? 0 : 1],
    }
  })
}

/** 一層水面裡的芋頭：排成格子，每株大小與朝向不同 */
function taroIn(terrace, index, random) {
  const { water } = terrace
  const usableWidth = water.width - TARO_MARGIN * 2
  const usableDepth = water.depth - TARO_MARGIN * 2
  return Array.from({ length: TARO_ROWS * TARO_COLUMNS }, (_, n) => {
    const row = Math.floor(n / TARO_COLUMNS)
    const column = n % TARO_COLUMNS
    return {
      terrace: index,
      x: water.x - usableWidth / 2 + ((column + 0.5) * usableWidth) / TARO_COLUMNS,
      z: water.z - usableDepth / 2 + ((row + 0.5) * usableDepth) / TARO_ROWS,
      level: water.level,
      scale: TARO_SCALE.min + random() * TARO_SCALE.spread,
      turn: random() * Math.PI * 2,
    }
  })
}

/**
 * 寬 width、深 depth 的水田（以中心為原點）：
 * { terraces: [{ x, z, width, depth, level 這一層的高度, soilTop 土頂, water: { x, z, width, depth, level } }]（0 最低）,
 *   stones: [{ terrace, x, z, level 底部高度, width, height, depth, color }],
 *   taro: [{ terrace, x, z, level 站的高度, scale 大小, turn 朝向（弧度） }] }
 */
export function paddyShape(width, depth) {
  const random = createRandom(SEED)
  const terraces = Array.from({ length: TERRACES }, (_, i) => terraceAt(i, width, depth))
  return {
    terraces,
    stones: terraces.flatMap((terrace, i) => stoneRow(terrace, i, random)),
    taro: terraces.flatMap((terrace, i) => taroIn(terrace, i, random)),
  }
}

// 魚島 3D：每戶人家的四件東西（#201 驗收條件 1），照 chung 確認的樣品第 3 版。不依賴 three.js。
// 零件用公尺寫在設計佔地上（寬 × 深，+z 是正面、朝海），再換算成佔地的比例。
import { part, scaledTo } from './shapeKit'

const WOOD = '#8a5a34'
const WOOD_DARK = '#5a3a22'
const DOOR = '#2e1d10'
const THATCH = '#9c8a62'
const STONE = '#7f838a'
const STONE_LIGHT = '#a4a7ab'

// vahay（7 × 5）：低矮的木屋、茅草兩面坡屋頂壓得很低，四周石牆，前面石階和石板
const VAHAY_WALL = 0.9
const VAHAY_BODY = 0.7
const VAHAY_ROOF = 1.2
const VAHAY_Z = -0.45
const VAHAY_FRONT = 0.87
const VAHAY = [
  part('box', [7.0, VAHAY_WALL, 0.5], [0, -2.25], 0, STONE),
  part('box', [0.5, VAHAY_WALL, 4.5], [-3.25, 0], 0, STONE),
  part('box', [0.5, VAHAY_WALL, 4.5], [3.25, 0], 0, STONE),
  part('box', [2.6, VAHAY_WALL, 0.45], [-2.2, 2.25], 0, STONE),
  part('box', [2.6, VAHAY_WALL, 0.45], [2.2, 2.25], 0, STONE),
  // 石階：兩階，從石牆缺口往下
  part('box', [1.8, 0.3, 0.35], [0, 2.2], 0, STONE_LIGHT),
  part('box', [1.8, 0.3, 0.35], [0, 1.85], 0, STONE_LIGHT),
  part('box', [5.4, VAHAY_BODY, 2.6], [0, VAHAY_Z], 0, WOOD),
  ...[-1.8, -0.6, 0.6, 1.8].map((x) =>
    part('box', [0.06, VAHAY_BODY, 0.04], [x, VAHAY_FRONT], 0, WOOD_DARK)
  ),
  part('box', [0.6, 0.6, 0.05], [-1.2, VAHAY_FRONT], 0, DOOR),
  part('box', [0.6, 0.6, 0.05], [1.2, VAHAY_FRONT], 0, DOOR),
  part('gable', [6.4, VAHAY_ROOF, 4.0], [0, VAHAY_Z], VAHAY_BODY, THATCH),
  part('cylinder', [6.6, 0.14, 0.14], [0, VAHAY_Z], VAHAY_BODY + VAHAY_ROOF - 0.05, WOOD_DARK, {
    axis: 'x',
  }),
  part('plane', [5.6, 0.04, 1.2], [0, 1.3], 0, STONE_LIGHT),
]

// pananadengan（3.4 × 2.4）：3 塊大小不一、頂端圓的大石頭立成一排，可以靠著坐；前面鋪圓扁的石板
const ROCK = '#5f6266'
const ROCK_DARK = '#55585c'
const ROCK_FLAT = '#7c7f83'
const BACKREST_Z = -0.75
const MIDDLE_STONE_THICKNESS = 0.6
/** 中間那塊立石的前緣（離佔地中線多遠，負的是往後）：akay 坐在它前面（#201 驗收條件 6） */
export const BACKREST_FRONT_Z = BACKREST_Z + MIDDLE_STONE_THICKNESS / 2
/** 立石：下面是方的、上面半圓（圓柱的軸朝前）；寬 width、高 height、厚 thickness */
const uprightStone = (x, width, height, thickness, color) => [
  part('box', [width, height - width / 2, thickness], [x, BACKREST_Z], 0, color),
  part('cylinder', [width, width, thickness], [x, BACKREST_Z], height - width, color, {
    axis: 'z',
  }),
]
/** 圓扁的石板：size 是寬，深是寬的 0.85 倍 */
const flatStone = ([x, z], size) =>
  part('cylinder', [size, 0.06, size * 0.85], [x, z], 0, ROCK_FLAT)
const PANANADENGAN = [
  ...uprightStone(-1.05, 0.85, 1.15, 0.55, ROCK),
  ...uprightStone(0, 1.0, 1.3, MIDDLE_STONE_THICKNESS, ROCK_DARK),
  ...uprightStone(1.05, 0.8, 0.95, 0.55, ROCK),
  ...[
    [[-1.3, 0.25], 0.7],
    [[-0.4, 0.25], 0.6],
    [[0.5, 0.25], 0.7],
    [[1.35, 0.25], 0.6],
    [[-0.9, 0.85], 0.6],
    [[0.1, 0.85], 0.6],
    [[1.0, 0.85], 0.55],
  ].map(([at, size]) => flatStone(at, size)),
  // akay 坐的石頭，在中間那塊立石前面
  part('cylinder', [0.8, 0.32, 0.6], [0, -0.25], 0, ROCK_FLAT),
]

// zazawan（4 × 3）：四角竹柱撐起四方框，框上平行橫放 7 根竹竿，竹竿下掛滿魚乾
const BAMBOO = '#c7a564'
const DRIED_FISH = ['#9a6a3e', '#b07d4c']
const FISH_TAIL = '#7a5030'
const RACK_TOP = 2.2
const RACK_POLES = 7
const FISH_PER_POLE = 11
const RACK_LEG_X = 1.85
const RACK_LEG_Z = 1.35
const poleZ = (i) => -1.2 + (i * 2.4) / (RACK_POLES - 1)
/** 一條魚乾：身體加尾巴，掛在竹竿下；長度照位置輪流變化，看起來不像一排木板 */
const driedFish = (x, z, k) => {
  const length = 0.36 + (k % 3) * 0.05
  return [
    part('box', [0.11, length, 0.04], [x, z], RACK_TOP - length - 0.02, DRIED_FISH[k % 2]),
    part('box', [0.17, 0.07, 0.03], [x, z], RACK_TOP - length - 0.09, FISH_TAIL),
  ]
}
const ZAZAWAN = [
  ...[
    [-RACK_LEG_X, -RACK_LEG_Z],
    [RACK_LEG_X, -RACK_LEG_Z],
    [-RACK_LEG_X, RACK_LEG_Z],
    [RACK_LEG_X, RACK_LEG_Z],
  ].map((at) => part('cylinder', [0.12, RACK_TOP, 0.12], at, 0, BAMBOO)),
  ...[-RACK_LEG_X, RACK_LEG_X].map((x) =>
    part('cylinder', [0.1, 0.1, 2.9], [x, 0], RACK_TOP - 0.1, BAMBOO, { axis: 'z' })
  ),
  ...Array.from({ length: RACK_POLES }, (_, i) =>
    part('cylinder', [4.0, 0.07, 0.07], [0, poleZ(i)], RACK_TOP, BAMBOO, { axis: 'x' })
  ),
  ...Array.from({ length: RACK_POLES }, (_, i) =>
    Array.from({ length: FISH_PER_POLE }, (_, k) => driedFish(-1.5 + k * 0.3, poleZ(i), k))
  ).flat(2),
]

// tagakal（3 × 3）：高腳木台、茅草頂、前面一把梯子
const TAGAKAL_DECK = 1.3
const TAGAKAL_POST_HEIGHT = 1.2
const TAGAKAL_ROOF = TAGAKAL_DECK + TAGAKAL_POST_HEIGHT
const LADDER_Z = 1.45
const tagakalPosts = (height, base) =>
  [
    [-1.2, -1.2],
    [1.2, -1.2],
    [-1.2, 1.0],
    [1.2, 1.0],
  ].map((at) => part('post', [0.16, height, 0.16], at, base, WOOD_DARK))
const TAGAKAL = [
  ...tagakalPosts(TAGAKAL_DECK, 0),
  part('box', [2.7, 0.15, 2.5], [0, -0.1], TAGAKAL_DECK, WOOD),
  ...tagakalPosts(TAGAKAL_POST_HEIGHT, TAGAKAL_DECK + 0.15),
  part('gable', [3.0, 1.0, 2.8], [0, -0.1], TAGAKAL_ROOF, THATCH),
  part('box', [0.06, 1.4, 0.06], [-0.3, LADDER_Z], 0, WOOD_DARK),
  part('box', [0.06, 1.4, 0.06], [0.3, LADDER_Z], 0, WOOD_DARK),
  ...[0.3, 0.65, 1.0].map((y) => part('box', [0.6, 0.05, 0.06], [0, LADDER_Z], y, WOOD_DARK)),
]

/** 四件的造型（換算成佔地比例），併進 landmarks.js 的造型清單 */
export const HOUSEHOLD_SHAPES = {
  vahay: scaledTo(7, 5, VAHAY),
  pananadengan: scaledTo(3.4, 2.4, PANANADENGAN),
  zazawan: scaledTo(4, 3, ZAZAWAN),
  tagakal: scaledTo(3, 3, TAGAKAL),
}

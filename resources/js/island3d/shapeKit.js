// 魚島 3D：程式拼的造型共用的寫法（#207、#211、#201）。不依賴 three.js。
// 零件用公尺寫在設計佔地上：shape、size [寬, 高, 深]、at [x, z]（離佔地中心）、base 底部離地多高、color；
// 可以再加 axis（圓柱的軸）、opacity（半透明）。

/** 一個零件（公尺） */
export const part = (shape, size, at, base, color, extra = {}) => ({
  shape,
  size,
  at,
  base,
  color,
  ...(shape === 'cylinder' ? { axis: 'y' } : {}),
  ...extra,
})

/** 把公尺寫的零件換算成設計佔地 width × depth 的比例 */
export const scaledTo = (width, depth, parts) =>
  parts.map(({ size, at, ...rest }) => ({
    ...rest,
    size: [size[0] / width, size[1], size[2] / depth],
    at: [at[0] / width, at[1] / depth],
  }))

// 魚島 3D：拉近時放大的名字標示（#228 驗收條件 4）：地標招牌、家屋招牌、動物的名稱。
// 標示都是 sizeAttenuation: false 的 Sprite，大小是畫面的比例；只改大小，不動顯示或隱藏。

/**
 * 三種名字標示的 Sprite。landmarks 是 buildLandmarks 的結果、householdSigns 是 buildHouseholdSigns 的結果、
 * animals 是 createAnimals 的結果
 */
export function nameLabelsOf({ landmarks, householdSigns, animals }) {
  return [
    ...landmarks.signs.children,
    ...householdSigns.children,
    ...animals.figures.map((figure) => figure.label),
  ]
}

/** 名字標示放大成原本大小的 factor 倍；原本的大小第一次時記在 userData.baseScale */
export function scaleNameLabels(labels, factor) {
  labels.forEach((label) => {
    label.userData.baseScale ??= label.scale.clone()
    label.scale.copy(label.userData.baseScale).multiplyScalar(factor)
  })
}

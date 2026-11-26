import { describe, expect, it } from 'vitest'
import {
  cameraDistance,
  cameraPlacement,
  boundingCircle,
  cameraRange,
  cameraTarget,
  createCameraRig,
  createGesture,
  reduceGesture,
  rotateCamera,
  zoomByPinch,
  zoomByWheel,
} from '@/island3d/cameraRig'
import { ISLAND_DECOR, ISLAND_MAP } from '@/island3d/islandMap'

// 鏡頭的俯角沿用 #199：在角色後方 13、上方 12 的方向（距離 17.692）
const PITCH_SIN = 12 / Math.hypot(12, 13)
const PITCH_COS = 13 / Math.hypot(12, 13)
// 上下視角 45 度的一半
const TAN_HALF_VERTICAL = Math.tan((22.5 * Math.PI) / 180)

/** 依序送一串事件，回傳最後的狀態與每個事件產生的效果 */
const play = (events, state = createGesture()) => {
  const effects = []
  events.forEach((event) => {
    const next = reduceGesture(state, event)
    state = next.state
    effects.push(next.effect)
  })
  return { state, effects }
}
const finger = (type, id, x = 0, y = 0) => ({ type, id, x, y, button: 0 })
// 進入頁面時的俯角（#199 的角度）：鏡頭在後方 13、上方 12 的方向
const DEFAULT_PITCH = Math.atan2(12, 13)
const degrees = (value) => (value * Math.PI) / 180
const rigOf = (overrides) => ({ heading: 0, pitch: DEFAULT_PITCH, zoom: 0, ...overrides })
const typesOf = (effects) => effects.map((effect) => effect?.type ?? null)

describe('island3d/cameraRig 轉鏡頭（#200 驗收條件 6）', () => {
  it('一開始鏡頭沒有轉、在最近的距離', () => {
    const rig = createCameraRig()

    expect(rig.heading).toBe(0)
    expect(rig.zoom).toBe(0)
    expect(rig.pitch).toBeCloseTo(DEFAULT_PITCH, 10)
  })

  it('往右拖 360 像素轉半圈；往左拖轉回來', () => {
    const turned = rotateCamera(createCameraRig(), 360)

    expect(turned.heading).toBeCloseTo(-Math.PI, 5)
    expect(rotateCamera(turned, -360).heading).toBeCloseTo(0, 5)
  })

  it('可以一直轉，沒有角度上限', () => {
    let rig = createCameraRig()
    for (let i = 0; i < 10; i++) {
      rig = rotateCamera(rig, 360)
    }

    expect(rig.heading).toBeCloseTo(-10 * Math.PI, 5)
  })

  it('轉鏡頭不改變距離', () => {
    const rig = rigOf({ heading: 0, zoom: 0.4 })

    expect(rotateCamera(rig, 100).zoom).toBe(0.4)
  })

  it('鏡頭的位置：沒轉時在角色的 +z 那一側斜上方，俯角固定', () => {
    const range = { nearest: 10, farthest: 1000 }
    const position = cameraPlacement(rigOf({ heading: 0, zoom: 0 }), { x: 10, z: 5 }, range)

    expect(position.x).toBeCloseTo(10, 5)
    expect(position.y).toBeCloseTo(10 * PITCH_SIN, 5)
    expect(position.z).toBeCloseTo(5 + 10 * PITCH_COS, 5)
  })

  it('轉四分之一圈：鏡頭繞到角色的 +x 那一側，高度不變', () => {
    const range = { nearest: 10, farthest: 1000 }
    const position = cameraPlacement(
      rigOf({ heading: Math.PI / 2, zoom: 0 }),
      { x: 10, z: 5 },
      range
    )

    expect(position.x).toBeCloseTo(10 + 10 * PITCH_COS, 5)
    expect(position.y).toBeCloseTo(10 * PITCH_SIN, 5)
    expect(position.z).toBeCloseTo(5, 5)
  })
})

describe('island3d/cameraRig 上下轉（#200 驗收條件 6，規格修訂 2）', () => {
  it('手指往下拖 100 像素：鏡頭升高 25 度、更俯視；往上拖回來', () => {
    const higher = rotateCamera(createCameraRig(), 0, 100)

    expect(higher.pitch).toBeCloseTo(DEFAULT_PITCH + degrees(25), 5)
    expect(rotateCamera(higher, 0, -100).pitch).toBeCloseTo(DEFAULT_PITCH, 5)
  })

  it('俯角最高 75 度、最低 25 度，到了上下限就不再變', () => {
    expect(rotateCamera(createCameraRig(), 0, 5000).pitch).toBeCloseTo(degrees(75), 10)
    expect(rotateCamera(createCameraRig(), 0, -5000).pitch).toBeCloseTo(degrees(25), 10)
  })

  it('上下拖不改變左右的角度；左右拖不改變俯角；斜著拖兩個都變', () => {
    const start = rigOf({ heading: 1, zoom: 0.3 })
    const diagonal = rotateCamera(start, 360, 40)

    expect(rotateCamera(start, 0, 40).heading).toBe(1)
    expect(rotateCamera(start, 90, 0).pitch).toBe(DEFAULT_PITCH)
    expect(diagonal.heading).toBeCloseTo(1 - Math.PI, 5)
    expect(diagonal.pitch).toBeCloseTo(DEFAULT_PITCH + degrees(10), 5)
    expect(diagonal.zoom).toBe(0.3)
  })

  it('鏡頭的位置跟著俯角：75 度時幾乎在正上方，25 度時壓得很低', () => {
    const range = { nearest: 10, farthest: 1000 }
    const high = cameraPlacement(rigOf({ pitch: degrees(75) }), { x: 0, z: 0 }, range)
    const low = cameraPlacement(rigOf({ pitch: degrees(25) }), { x: 0, z: 0 }, range)

    // sin 75° = 0.9659、cos 75° = 0.2588；sin 25° = 0.4226、cos 25° = 0.9063
    expect(high.y).toBeCloseTo(9.659, 2)
    expect(high.z).toBeCloseTo(2.588, 2)
    expect(low.y).toBeCloseTo(4.226, 2)
    expect(low.z).toBeCloseTo(9.063, 2)
  })

  it('最遠的距離跟著俯角（橫式、範圍半徑 100）：25 度是 192.7、75 度是 259.1', () => {
    const farthest = (pitch) => cameraRange({ aspect: 16 / 9, viewRadius: 100, pitch }).farthest

    expect(farthest(degrees(25))).toBeCloseTo(192.66, 1)
    expect(farthest(degrees(75))).toBeCloseTo(259.08, 1)
  })

  it('最近的距離不受俯角影響', () => {
    const nearest = (pitch) => cameraRange({ aspect: 16 / 9, viewRadius: 100, pitch }).nearest

    expect(nearest(degrees(25))).toBeCloseTo(17.692, 2)
    expect(nearest(degrees(75))).toBeCloseTo(17.692, 2)
  })
})

describe('island3d/cameraRig 拉遠拉近（#200 驗收條件 7）', () => {
  const RANGE = { nearest: 10, farthest: 1000 }

  it('距離在最近與最遠之間等比例變化：zoom 0 最近、1 最遠、0.5 是兩者的等比中點', () => {
    expect(cameraDistance(rigOf({ heading: 0, zoom: 0 }), RANGE)).toBeCloseTo(10, 5)
    expect(cameraDistance(rigOf({ heading: 0, zoom: 1 }), RANGE)).toBeCloseTo(1000, 5)
    expect(cameraDistance(rigOf({ heading: 0, zoom: 0.5 }), RANGE)).toBeCloseTo(100, 5)
  })

  it('滾輪往下（往自己）拉遠、往上拉近', () => {
    const out = zoomByWheel(rigOf({ heading: 0, zoom: 0.5 }), 100)
    const back = zoomByWheel(out, -100)

    expect(out.zoom).toBeGreaterThan(0.5)
    expect(back.zoom).toBeCloseTo(0.5, 5)
  })

  it('到了最近或最遠就不再變', () => {
    expect(zoomByWheel(rigOf({ heading: 0, zoom: 0 }), -5000).zoom).toBe(0)
    expect(zoomByWheel(rigOf({ heading: 0, zoom: 1 }), 5000).zoom).toBe(1)
    expect(zoomByPinch(rigOf({ heading: 0, zoom: 0 }), 50, RANGE).zoom).toBe(0)
    expect(zoomByPinch(rigOf({ heading: 0, zoom: 1 }), 0.01, RANGE).zoom).toBe(1)
  })

  it('雙指張開成 2 倍：距離變一半（拉近）；捏合成一半：距離變 2 倍', () => {
    const middle = rigOf({ heading: 0, zoom: 0.5 })

    expect(cameraDistance(zoomByPinch(middle, 2, RANGE), RANGE)).toBeCloseTo(50, 5)
    expect(cameraDistance(zoomByPinch(middle, 0.5, RANGE), RANGE)).toBeCloseTo(200, 5)
  })

  it('縮放不改變鏡頭的角度', () => {
    expect(zoomByWheel(rigOf({ heading: 1.2, zoom: 0.5 }), 100).heading).toBe(1.2)
    expect(zoomByPinch(rigOf({ heading: 1.2, zoom: 0.5 }), 2, RANGE).heading).toBe(1.2)
  })

  it('最近的距離等於 #199：橫式是 17.69；直式畫面拉遠到看得完一個格子的寬度，最多 2.4 倍', () => {
    expect(
      cameraRange({ aspect: 16 / 9, viewRadius: 100, pitch: DEFAULT_PITCH }).nearest
    ).toBeCloseTo(17.692, 2)
    expect(cameraRange({ aspect: 0.5, viewRadius: 100, pitch: DEFAULT_PITCH }).nearest).toBeCloseTo(
      35.384,
      2
    )
    expect(
      cameraRange({ aspect: 0.25, viewRadius: 100, pitch: DEFAULT_PITCH }).nearest
    ).toBeCloseTo(42.461, 2)
  })

  it('最遠的距離依寬高比計算（要看得到的範圍半徑 100）：橫式 237.2、直式 9:16 是 435.4', () => {
    expect(
      cameraRange({ aspect: 16 / 9, viewRadius: 100, pitch: DEFAULT_PITCH }).farthest
    ).toBeCloseTo(237.23, 1)
    expect(
      cameraRange({ aspect: 9 / 16, viewRadius: 100, pitch: DEFAULT_PITCH }).farthest
    ).toBeCloseTo(435.44, 1)
  })

  // 用測試自己的投影算法驗證：鏡頭拉到最遠時，地面上的點落在畫面裡
  const isInView = (camera, target, point, aspect) => {
    const toTarget = { x: target.x - camera.x, y: -camera.y, z: target.z - camera.z }
    const length = Math.hypot(toTarget.x, toTarget.y, toTarget.z)
    const forward = { x: toTarget.x / length, y: toTarget.y / length, z: toTarget.z / length }
    const flat = Math.hypot(forward.x, forward.z)
    // 鏡頭的右方（水平）與上方
    const right = { x: -forward.z / flat, y: 0, z: forward.x / flat }
    const up = {
      x: right.y * forward.z - right.z * forward.y,
      y: right.z * forward.x - right.x * forward.z,
      z: right.x * forward.y - right.y * forward.x,
    }
    const offset = { x: point.x - camera.x, y: -camera.y, z: point.z - camera.z }
    const dot = (a, b) => a.x * b.x + a.y * b.y + a.z * b.z
    const depth = dot(offset, forward)
    const across = Math.abs(dot(offset, right)) / (depth * TAN_HALF_VERTICAL * aspect)
    const upward = Math.abs(dot(offset, up)) / (depth * TAN_HALF_VERTICAL)
    return depth > 0 && across <= 1.0001 && upward <= 1.0001
  }

  it('一群點的外接範圍：中心是左右、前後的正中間，半徑是中心到最遠那一點', () => {
    const circle = boundingCircle([
      { x: 0, z: 0 },
      { x: 10, z: 0 },
      { x: 10, z: 6 },
      { x: 0, z: 6 },
    ])

    expect(circle.center).toEqual({ x: 5, z: 3 })
    expect(circle.radius).toBeCloseTo(Math.hypot(5, 3), 5)
  })

  it('鏡頭對準的位置：最近時是角色，最遠時是島的中心', () => {
    const range = { nearest: 10, farthest: 110 }
    const player = { x: 40, z: -20 }
    const island = { x: 0, z: 0 }

    expect(cameraTarget(rigOf({ heading: 0, zoom: 0 }), player, range, island)).toEqual(player)
    expect(cameraTarget(rigOf({ heading: 0, zoom: 1 }), player, range, island)).toEqual(island)
  })

  it('鏡頭對準的位置依鏡頭距離移動：距離走到最近與最遠的正中間時，在角色與島中心的正中間', () => {
    const range = { nearest: 10, farthest: 110 }
    // 距離 60 是 10 與 110 的正中間；距離等比例變化，所以 zoom = log(6) / log(11)
    const rig = rigOf({ heading: 0, zoom: Math.log(6) / Math.log(11) })
    const target = cameraTarget(rig, { x: 40, z: -20 }, range, { x: 0, z: 0 })

    expect(target.x).toBeCloseTo(20, 5)
    expect(target.z).toBeCloseTo(-10, 5)
  })

  // 魚島：海岸線圍住整座島
  const ISLAND = boundingCircle(ISLAND_DECOR.coast)
  const STOPS = [
    ...ISLAND_MAP.tribes.map((tribe) => tribe.center),
    ...ISLAND_MAP.roads.flatMap((road) => road.points),
  ]
  const HEADINGS = [0, Math.PI / 4, Math.PI / 2, Math.PI, (3 * Math.PI) / 2]
  const ASPECTS = [16 / 9, 1, 9 / 16]
  const PITCHES = [degrees(25), DEFAULT_PITCH, degrees(75)]
  /** 每種寬高比 × 每個俯角 × 角色站的每個位置 × 每個左右角度，在某個縮放程度下的鏡頭 */
  /** 幾個清單的所有組合：[[a1, b1], [a1, b2], …] */
  const combinations = (lists) =>
    lists.reduce((rows, list) => rows.flatMap((row) => list.map((item) => [...row, item])), [[]])
  const VIEWS = combinations([ASPECTS, PITCHES, STOPS, HEADINGS])
  /** 某個縮放程度下，每一種組合的鏡頭：{ zoom, aspect, pitch, player, heading, target, camera } */
  const viewsAt = (zoom) =>
    VIEWS.map(([aspect, pitch, player, heading]) => {
      const range = cameraRange({ aspect, viewRadius: ISLAND.radius, pitch })
      const rig = rigOf({ heading, pitch, zoom })
      const target = cameraTarget(rig, player, range, ISLAND.center)
      return {
        zoom,
        aspect,
        pitch,
        player,
        heading,
        target,
        camera: cameraPlacement(rig, target, range),
      }
    })
  const sees = (view, point) => isInView(view.camera, view.target, point, view.aspect)

  it('拉到最遠：不論角色在哪裡、鏡頭左右轉到哪、俯角多少、橫式或直式，整條海岸線都在畫面裡', () => {
    const missed = viewsAt(1).filter(
      (view) => !ISLAND_DECOR.coast.every((point) => sees(view, point))
    )

    expect(missed).toEqual([])
  })

  it('拉到最遠時島是置中的：鏡頭對準的是島的中心，和角色站在哪裡無關', () => {
    // 對準的位置是由角色往島中心內插算出來的，放大後的座標有小數誤差，比到小數 9 位
    viewsAt(1).forEach(({ target }) => {
      expect(target.x).toBeCloseTo(ISLAND.center.x, 9)
      expect(target.z).toBeCloseTo(ISLAND.center.z, 9)
    })
  })

  it('任何縮放程度下角色都在畫面裡', () => {
    const missed = [0, 0.1, 0.25, 0.5, 0.75, 0.9, 1]
      .flatMap(viewsAt)
      .filter((view) => !sees(view, view.player))

    expect(missed).toEqual([])
  })

  it('最遠距離沒有多拉：再近 10% 就會有地方跑出畫面', () => {
    const aspect = 16 / 9
    const range = cameraRange({ aspect, viewRadius: 100, pitch: DEFAULT_PITCH })
    const target = { x: 0, z: 0 }
    const closer = { nearest: range.nearest, farthest: range.farthest * 0.9 }
    const camera = cameraPlacement(rigOf({ heading: 0, zoom: 1 }), target, closer)

    // 沒轉時鏡頭在 +z 側：離鏡頭最近的邊緣點是 (0, 100)
    expect(isInView(camera, target, { x: 0, z: 100 }, aspect)).toBe(false)
  })
})

describe('island3d/cameraRig 手勢（#200 驗收條件 6、7）', () => {
  it('按下後沒什麼移動就放開：輕點，位置是按下的地方', () => {
    const { effects } = play([
      finger('down', 1, 100, 100),
      finger('move', 1, 104, 103),
      finger('up', 1),
    ])

    expect(effects.at(-1)).toEqual({ type: 'tap', x: 100, y: 100 })
    expect(typesOf(effects.slice(0, -1))).toEqual([null, null])
  })

  it('剛好移動 8 像素還算輕點；超過 8 像素算拖曳', () => {
    const exactly = play([
      finger('down', 1, 100, 100),
      finger('move', 1, 108, 100),
      finger('up', 1),
    ])
    const beyond = play([finger('down', 1, 100, 100), finger('move', 1, 109, 100), finger('up', 1)])

    expect(typesOf(exactly.effects)).toEqual([null, null, 'tap'])
    expect(typesOf(beyond.effects)).toEqual([null, 'rotate', null])
  })

  it('拖曳：每次移動回報這一次左右、上下各移動了多少，放開不算輕點', () => {
    const { effects } = play([
      finger('down', 1, 100, 100),
      finger('move', 1, 120, 100),
      finger('move', 1, 150, 140),
      finger('move', 1, 130, 140),
      finger('up', 1),
    ])

    expect(effects).toEqual([
      null,
      { type: 'rotate', deltaX: 20, deltaY: 0 },
      { type: 'rotate', deltaX: 30, deltaY: 40 },
      { type: 'rotate', deltaX: -20, deltaY: 0 },
      null,
    ])
  })

  it('拖超過 8 像素又拖回原位才放開：不算輕點', () => {
    const { effects } = play([
      finger('down', 1, 100, 100),
      finger('move', 1, 130, 100),
      finger('move', 1, 100, 100),
      finger('up', 1),
    ])

    expect(effects.at(-1)).toBeNull()
    expect(typesOf(effects)).not.toContain('tap')
  })

  it('單指按下後加入第二指：改成縮放、停止轉鏡頭，放開也不算輕點', () => {
    const { effects } = play([
      finger('down', 1, 100, 100),
      finger('down', 2, 200, 100),
      finger('move', 2, 300, 100),
      finger('move', 1, 0, 100),
      finger('up', 1),
      finger('up', 2),
    ])

    // 兩指距離 100 → 200（2 倍）→ 300（1.5 倍）
    expect(effects[2]).toEqual({ type: 'zoom', scale: 2 })
    expect(effects[3]).toEqual({ type: 'zoom', scale: 1.5 })
    expect(typesOf(effects)).toEqual([null, null, 'zoom', 'zoom', null, null])
  })

  it('單指已經在拖曳時加入第二指：從那一刻起不再轉鏡頭', () => {
    const { effects } = play([
      finger('down', 1, 100, 100),
      finger('move', 1, 150, 100),
      finger('down', 2, 250, 100),
      finger('move', 1, 50, 100),
    ])

    expect(typesOf(effects)).toEqual([null, 'rotate', null, 'zoom'])
  })

  it('雙指放開一指後：剩下那指移動不轉鏡頭、不縮放，放開不算輕點', () => {
    const { effects } = play([
      finger('down', 1, 100, 100),
      finger('down', 2, 200, 100),
      finger('up', 2),
      finger('move', 1, 300, 100),
      finger('move', 1, 100, 100),
      finger('up', 1),
    ])

    expect(typesOf(effects)).toEqual([null, null, null, null, null, null])
  })

  it('雙指放開一指後再放回第二指：仍然沒有作用，要全部放開才重新開始', () => {
    const { effects } = play([
      finger('down', 1, 100, 100),
      finger('down', 2, 200, 100),
      finger('up', 2),
      finger('down', 3, 200, 100),
      finger('move', 3, 400, 100),
      finger('up', 3),
      finger('up', 1),
    ])

    expect(typesOf(effects)).toEqual([null, null, null, null, null, null, null])
  })

  it('全部放開後，下一次輕點與拖曳照常', () => {
    const spent = play([
      finger('down', 1, 100, 100),
      finger('down', 2, 200, 100),
      finger('up', 2),
      finger('up', 1),
    ])
    const tap = play([finger('down', 5, 50, 60), finger('up', 5)], spent.state)
    const drag = play([finger('down', 6, 50, 60), finger('move', 6, 90, 60)], tap.state)

    expect(tap.effects.at(-1)).toEqual({ type: 'tap', x: 50, y: 60 })
    expect(drag.effects.at(-1)).toEqual({ type: 'rotate', deltaX: 40, deltaY: 0 })
  })

  it('雙指時加入第三指：不影響前兩指的縮放', () => {
    const { effects } = play([
      finger('down', 1, 100, 100),
      finger('down', 2, 200, 100),
      finger('down', 3, 500, 500),
      finger('move', 3, 600, 600),
      finger('move', 2, 300, 100),
    ])

    expect(typesOf(effects)).toEqual([null, null, null, null, 'zoom'])
    expect(effects.at(-1).scale).toBeCloseTo(2, 5)
  })

  it('滑鼠只認左鍵：右鍵按下、移動、放開都沒有作用', () => {
    const right = (type, x) => ({ type, id: 1, x, y: 100, button: 2 })
    const { effects, state } = play([right('down', 100), right('move', 200), right('up', 200)])

    expect(typesOf(effects)).toEqual([null, null, null])
    expect(state).toEqual(createGesture())
  })

  it('手勢被系統取消（例如來電、視窗失焦）：不算輕點，之後重新開始', () => {
    const cancelled = play([finger('down', 1, 100, 100), { type: 'cancel', id: 1 }])
    const next = play([finger('down', 2, 10, 10), finger('up', 2)], cancelled.state)

    expect(cancelled.effects.at(-1)).toBeNull()
    expect(next.effects.at(-1)).toEqual({ type: 'tap', x: 10, y: 10 })
  })

  it('沒按下就移動（滑鼠滑過畫面）：沒有作用', () => {
    const { effects } = play([finger('move', 1, 300, 300), finger('move', 1, 400, 300)])

    expect(typesOf(effects)).toEqual([null, null])
  })
})

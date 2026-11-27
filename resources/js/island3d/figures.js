// 魚島 3D：人物共用的大小、動作切換與模型還沒載入時的簡單人形（玩家與家人共用，#201 從 scene.js 搬出）
import * as THREE from 'three'
import { flatMesh } from './terrain'

export const ACTOR_HEIGHT = 1.7
// 動作切換的淡入淡出秒數
export const ACTION_FADE_SECONDS = 0.2
// 簡單人形的半徑與身體長度，各佔人物高度的比例
const PLACEHOLDER_RADIUS_RATIO = 0.22
const PLACEHOLDER_BODY_RATIO = 0.5

/** 模型還沒載入（或載入失敗）時先用的簡單人形，走路照常 */
export function placeholderFigure(color) {
  const figure = new THREE.Group()
  const body = flatMesh(
    new THREE.CapsuleGeometry(
      ACTOR_HEIGHT * PLACEHOLDER_RADIUS_RATIO,
      ACTOR_HEIGHT * PLACEHOLDER_BODY_RATIO
    ),
    color
  )
  body.position.y = ACTOR_HEIGHT / 2
  figure.add(body)
  return figure
}

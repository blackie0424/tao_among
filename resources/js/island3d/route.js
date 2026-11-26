// 魚島 3D：點地面走過去時走哪條路（#200 驗收條件 4）。不依賴 three.js。
// 規則：起點到目的地的直線整段可走就直接走；否則在「起點、目的地、路線點」之中，
// 把直線整段可走的兩點連起來，取總長最短的那一條。路線點是部落中心與公路折點，
// 所以寬公路的轉彎處會經過折點，不切內角。
import { routePoints } from './islandMap'

const distance = (a, b) => Math.hypot(a.x - b.x, a.z - b.z)
const copy = (point) => ({ x: point.x, z: point.z })

/** nodes 兩兩之間能直走的距離表：links[i][j] 是距離，不能直走是 Infinity */
function linkTable(nodes, canWalkStraight) {
  return nodes.map((from, i) =>
    nodes.map((to, j) => (i !== j && canWalkStraight(from, to) ? distance(from, to) : Infinity))
  )
}

/** 從 start 到 goal（都是 links 的索引）總長最短的走法：回傳依序經過的索引；走不到是 null */
function shortestPath(links, start, goal) {
  const best = links.map(() => Infinity)
  const previous = links.map(() => null)
  const pending = new Set(links.keys())
  best[start] = 0

  while (pending.size > 0) {
    const current = [...pending].reduce((a, b) => (best[b] < best[a] ? b : a))
    if (best[current] === Infinity || current === goal) {
      break
    }
    pending.delete(current)
    links[current].forEach((length, next) => {
      if (best[current] + length < best[next]) {
        best[next] = best[current] + length
        previous[next] = current
      }
    })
  }

  if (best[goal] === Infinity) {
    return null
  }
  const path = [goal]
  while (path[0] !== start) {
    path.unshift(previous[path[0]])
  }
  return path
}

/**
 * 回傳 findRoute(from, to)：從 from 走到 to 要依序走過的點（不含 from，最後一個是 to）。
 * to 不可走、或沒有路可到時回傳 null。walkable 是 createWalkable(map) 的結果。
 */
export function createRouter(map, walkable) {
  const stops = routePoints(map)
  // 路線點之間的連線不會變，先算好
  const stopLinks = linkTable(stops, walkable.isStraightWalkable)

  return function findRoute(from, to) {
    if (!walkable.isWalkable(to)) {
      return null
    }
    if (walkable.isStraightWalkable(from, to)) {
      return [copy(to)]
    }
    // 索引：0 是起點、1～stops.length 是路線點、最後一個是目的地（起點與目的地之間已知不能直走）
    const toStops = (point) =>
      stops.map((stop) =>
        walkable.isStraightWalkable(point, stop) ? distance(point, stop) : Infinity
      )
    const fromLinks = toStops(from)
    const goalLinks = toStops(to)
    const links = [
      [Infinity, ...fromLinks, Infinity],
      ...stopLinks.map((row, i) => [fromLinks[i], ...row, goalLinks[i]]),
      [Infinity, ...goalLinks, Infinity],
    ]
    const path = shortestPath(links, 0, links.length - 1)
    return path && path.slice(1).map((index) => copy([from, ...stops, to][index]))
  }
}

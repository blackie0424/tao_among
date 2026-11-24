import { describe, expect, it } from 'vitest'
import { islandTribeLabel } from '@/constants/island'

describe('魚島部落名稱', () => {
  it.each([
    ['iraraley', 'iraraley（朗島）'],
    ['yayo', 'yayo（椰油）'],
    ['iratay', 'iratay（漁人）'],
    ['imowrod', 'imowrod（紅頭）'],
    ['ivalino', 'ivalino（野銀）'],
    ['iranmeilek', 'iranmeilek（東清）'],
  ])('%s 顯示為 %s', (tribe, label) => {
    expect(islandTribeLabel(tribe)).toBe(label)
  })

  it('不在對照表裡的代碼原樣顯示', () => {
    expect(islandTribeLabel('unknown')).toBe('unknown')
  })
})

import { describe, expect, it } from 'vitest'
import { ROLE_LABELS, canAccessAudio, canAccessLocation } from '@/constants/roles'

describe('角色中文名稱', () => {
  it('定義四種角色的統一名稱', () => {
    expect(ROLE_LABELS).toEqual({
      guest: '訪客',
      viewer: '使用者',
      editor: '田調人員',
      admin: '管理者',
    })
  })
})

describe('影音存取權限', () => {
  it.each([
    ['guest', false],
    ['viewer', false],
    ['editor', true],
    ['admin', true],
    [undefined, false],
    [null, false],
    ['unknown', false],
  ])('%s 的判斷結果為 %s', (role, expected) => {
    expect(canAccessAudio(role)).toBe(expected)
  })
})

describe('canAccessLocation', () => {
  it.each([
    ['guest', false],
    ['viewer', false],
    ['editor', true],
    ['admin', true],
    [null, false],
  ])('returns location access for %s', (role, expected) => {
    expect(canAccessLocation(role)).toBe(expected)
  })
})
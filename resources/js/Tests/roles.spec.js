import { describe, expect, it } from 'vitest'
import { ROLE_LABELS } from '@/constants/roles'

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

export const ROLE_LABELS = Object.freeze({
  guest: '訪客',
  viewer: '使用者',
  editor: '田調人員',
  admin: '管理者',
})

export const canAccessAudio = (role) => role === 'editor' || role === 'admin'

export const canAccessLocation = (role) => role === 'editor' || role === 'admin'

// 帳號已開通：viewer 以上；guest 或未登入（無 role）都還不算
export const isActivated = (role) => ['viewer', 'editor', 'admin'].includes(role)

export const ROLE_LABELS = Object.freeze({
  guest: '訪客',
  viewer: '使用者',
  editor: '田調人員',
  admin: '管理者',
})

export const canAccessAudio = (role) => role === 'editor' || role === 'admin'

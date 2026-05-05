export function hasEditorAccess(user) {
  return ['editor', 'admin'].includes(user?.role)
}

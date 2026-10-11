import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import MergeFish from '@/Pages/MergeFish.vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'
vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }))
vi.mock('@inertiajs/vue3', () => ({ Head: { template: '<div />' }, router: { visit: vi.fn() } }))
vi.mock('@/utils/fishListCache', () => ({ markFishesDeleted: vi.fn(), markFishStale: vi.fn() }))
let page
beforeEach(() => {
  vi.clearAllMocks()
  vi.useFakeTimers()
  vi.spyOn(window, 'confirm').mockReturnValue(true)
  vi.spyOn(window, 'alert').mockImplementation(() => {})
  axios.get.mockResolvedValue({ data: { data: [{ id: 8, name: '來源魚' }] } })
  axios.post.mockResolvedValueOnce({ data: { data: { notes_count: 0, audios_count: 0, capture_records_count: 0, conflicts: [] } } })
})
afterEach(() => { page?.unmount(); vi.useRealTimers(); vi.restoreAllMocks() })
async function merge() {
  page = mount(MergeFish, { props: { fish: { id: 4, name: '目標魚' } }, global: { stubs: { FishAppLayout: { template: '<div><slot /></div>' }, LazyImage: true } } })
  await page.get('input[type="text"]').setValue('來源')
  await vi.advanceTimersByTimeAsync(300)
  await page.get('input[type="checkbox"]').trigger('click')
  await page.findAll('button').find(b => b.text().includes('預覽')).trigger('click')
  await flushPromises()
  expect(page.text()).toContain('0')
  await page.findAll('button').find(b => b.text().includes('確認合併')).trigger('click')
  await flushPromises()
}
it('合併成功即使零筆捕獲仍回目標魚捕獲 tab', async () => {
  axios.post.mockResolvedValueOnce({ data: { success: true } })
  await merge()
  expect(router.visit).toHaveBeenCalledWith('/fish/4?tab=capture', expect.any(Object))
  router.visit.mock.calls[0][1].onSuccess()
  expect(window.alert).toHaveBeenCalledWith('合併成功！')
})
it('合併失敗留頁並顯示錯誤', async () => {
  axios.post.mockRejectedValueOnce({ response: { data: { message: '合併失敗測試' } } })
  await merge()
  expect(page.text()).toContain('合併失敗測試')
  expect(router.visit).not.toHaveBeenCalled()
  expect(window.alert).not.toHaveBeenCalled()
})

import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CaptureRecords from '@/Pages/CaptureRecords.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
  router: { reload: vi.fn() },
}))

const mountPage = (captureRecords) =>
  mount(CaptureRecords, {
    props: { fish: { id: 1, name: '苦花', captureRecords }, tribes: [] },
    global: {
      stubs: {
        FishAppLayout: {
          props: ['pageTitle', 'mobileBackUrl', 'mobileBackText'],
          template:
            '<div :data-title="pageTitle" :data-back-url="mobileBackUrl" :data-back-text="mobileBackText"><slot /></div>',
        },
        CaptureRecordCard: { template: '<div data-testid="capture-record-card" />' },
      },
    },
  })

describe('CaptureRecords 管理頁', () => {
  it('標題為「{魚名}・管理捕獲紀錄」，返回「回魚類資料」並回到捕獲分頁', () => {
    const wrapper = mountPage([{ id: 5 }])
    const layout = wrapper.get('[data-title]')
    expect(layout.attributes('data-title')).toBe('苦花・管理捕獲紀錄')
    expect(wrapper.get('h1').text()).toBe('苦花・管理捕獲紀錄')
    expect(layout.attributes('data-back-url')).toBe('/fish/1?tab=capture')
    expect(layout.attributes('data-back-text')).toBe('回魚類資料')
  })

  it('最上方有「＋ 新增捕獲紀錄」連到新增頁，在卡片之前，且沒有浮動鈕', () => {
    const wrapper = mountPage([{ id: 5 }, { id: 6 }])
    const add = wrapper.get('a[href="/fish/1/capture-records/batch-create"]')
    expect(add.text()).toBe('＋ 新增捕獲紀錄')
    expect(wrapper.find('a[href="/fish/1/capture-records/create"]').exists()).toBe(false)
    const html = wrapper.html()
    expect(html.indexOf('＋ 新增捕獲紀錄')).toBeLessThan(html.indexOf('capture-record-card'))
    expect(wrapper.text()).not.toContain('批次新增捕獲紀錄')
    expect(wrapper.html()).not.toMatch(/fixed/)
    expect(wrapper.findAll('[data-testid="capture-record-card"]')).toHaveLength(2)
  })

  it('沒有紀錄時顯示空狀態，並仍有上方新增入口', () => {
    const wrapper = mountPage([])
    expect(wrapper.text()).toContain('還沒有捕獲紀錄')
    expect(wrapper.find('[data-testid="capture-record-card"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/fish/1/capture-records/batch-create"]').exists()).toBe(true)
  })

  it('套用博物誌：沒有白卡、陰影與 Tailwind 預設色', () => {
    expect(mountPage([]).html()).not.toMatch(
      /(?:bg|text|border)-(?:blue|gray|green|teal|slate)-\d|shadow|bg-white/
    )
  })
})

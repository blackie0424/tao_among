import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import FishDetailTop from '@/Components/FishDetail/FishDetailTop.vue'

const mockUsePage = vi.hoisted(() => vi.fn(() => ({ props: { auth: { user: null } } })))

vi.mock('@inertiajs/vue3', () => ({
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
  usePage: () => mockUsePage(),
}))

vi.mock('@/Components/UI/Volume.vue', () => ({ default: { props: ['audioUrl', 'audioId'], template: '<div data-testid="volume" :data-url="audioUrl" />' } }))
vi.mock('@/Components/UI/LazyImage.vue', () => ({ default: { template: '<img />' } }))

describe('FishDetailTop', () => {
  it('魚名使用長者頁標題字級', () => {
    const wrapper = mount(FishDetailTop, { props: { fish: { id: 1, name: '飛魚' } } })
    expect(wrapper.get('h1').classes()).toContain('text-elder-title')
  })
})

describe('FishDetailTop 博物誌樣式（#132 AC 5）', () => {
  afterEach(() => mockUsePage.mockReturnValue({ props: { auth: { user: null } } }))

  const mountTop = (fish = { id: 1, name: '飛魚', image_url: '/a.jpg' }) =>
    mount(FishDetailTop, { props: { fish } })

  it('魚名為宋體 28px、墨黑字，下方一條細線', () => {
    const wrapper = mountTop()
    const h1 = wrapper.get('h1')

    expect(h1.classes()).toEqual(
      expect.arrayContaining(['font-serif', 'text-elder-title', 'text-elder-text'])
    )
    expect(h1.text()).toBe('飛魚')
    expect(h1.element.parentElement.parentElement.classList).toContain('border-b')
    expect(h1.element.parentElement.parentElement.classList).toContain('border-rule')
  })

  it('照片區為細框，沒有白卡與陰影', () => {
    const html = mountTop().html()

    expect(html).toContain('border-rule-strong')
    expect(html).not.toMatch(/shadow|bg-white|bg-gray-|border-gray-/)
  })

  it('長魚名（無空白長字串）可換行：魚名容器可收縮、h1 可斷行，發音鈕不被擠出', () => {
    mockUsePage.mockReturnValue({ props: { auth: { user: { role: 'editor' } } } })
    const wrapper = mountTop({
      id: 1,
      name: 'Pseudocheilinus_hexataenia_extraordinarily_long_name',
      audio_url: '/a.mp3',
    })
    const h1 = wrapper.get('h1')

    expect(h1.classes()).toEqual(expect.arrayContaining(['min-w-0', 'break-words']))
    expect(h1.element.parentElement.classList).toContain('min-w-0')
    expect(h1.text()).toBe('Pseudocheilinus_hexataenia_extraordinarily_long_name')
  })

  it('麥克風的純圖示連結名稱為「新增發音」、帶 from=fish，圖示標為裝飾（#176 AC 4）', () => {
    mockUsePage.mockReturnValue({ props: { auth: { user: { role: 'editor' } } } })
    const wrapper = mountTop()
    const link = wrapper.get('a[href="/fish/1/audio/create?from=fish"]')

    expect(link.attributes('aria-label')).toBe('新增發音')
    expect(link.attributes('title')).toBe('新增發音')
    expect(link.get('svg').attributes('aria-hidden')).toBe('true')
  })

  it.each([
    ['魚名為空', { id: 1, name: '' }, 'editor'],
    ['魚名只有分隔符與空白', { id: 1, name: '、 、' }, 'admin'],
    ['沒有播放權限（viewer）', { id: 1, name: '飛魚' }, 'viewer'],
    ['未登入', { id: 1, name: '飛魚' }, null],
  ])('麥克風不顯示：%s', (_label, fish, role) => {
    mockUsePage.mockReturnValue({ props: { auth: { user: role ? { role } : null } } })

    expect(mountTop(fish).find('a[href^="/fish/1/audio/create"]').exists()).toBe(false)
  })

  it('沒有照片網址時仍渲染照片區，魚名照常顯示', () => {
    const wrapper = mountTop({ id: 1, name: '飛魚' })

    expect(wrapper.find('[data-testid="fish-detail-photo"]').exists()).toBe(true)
    expect(wrapper.get('h1').text()).toBe('飛魚')
  })
})

describe('FishDetailTop 依拼字播放（#159 AC 26）', () => {
  const editor = () => mockUsePage.mockReturnValue({ props: { auth: { user: { role: 'editor' } } } })
  afterEach(() => mockUsePage.mockReturnValue({ props: { auth: { user: null } } }))
  const rec = (id, spoken_name) => ({ id, name: `f${id}.m4a`, url: `/f${id}.m4a`, spoken_name })
  const mountTop = (fish) => mount(FishDetailTop, { props: { fish: { id: 1, image_url: '/a.jpg', audio_url: '/main.m4a', audio_filename: null, ...fish } } })

  // 魚名那一行依畫面順序列出拼字與播放鈕：['Ivey', '▶/f3.m4a', ...]
  const nameRowSequence = (wrapper) =>
    wrapper.get('h1').findAll('[data-testid="spelling"], [data-testid="volume"]').map((el) =>
      el.attributes('data-testid') === 'volume' ? `▶${el.attributes('data-url')}` : el.text())

  it('多個拼字：魚名那一行是「拼字 ▶ 拼字 ▶」，沒有錄音的拼字只顯示文字，魚名不另外重複一行', () => {
    editor()
    const wrapper = mountTop({ name: 'Ivey、Ivay、Ivoy', audios: [rec(3, 'Ivey'), rec(5, 'Ivay')] })

    expect(nameRowSequence(wrapper)).toEqual(['Ivey', '▶/f3.m4a', 'Ivay', '▶/f5.m4a', 'Ivoy'])
    expect(wrapper.findAll('[data-testid="volume"]')).toHaveLength(2)
    expect(wrapper.text().match(/Ivoy/g)).toHaveLength(1)
    expect(wrapper.get('h1').text()).toBe('Ivey、Ivay、Ivoy')
  })

  it('多個拼字：新增錄音按鈕與拼字在同一行，排在最右邊', () => {
    editor()
    const wrapper = mountTop({ name: 'Ivey、Ivay', audios: [rec(3, 'Ivey'), rec(5, 'Ivay')] })

    const row = wrapper.get('[data-testid="fish-name-row"]')
    expect(row.find('h1').exists()).toBe(true)
    const micBox = row.element.lastElementChild
    expect(micBox.querySelector('a[href="/fish/1/audio/create?from=fish"]')).not.toBeNull()
    expect(micBox.classList).toContain('ml-auto')
  })

  it('每個拼字播它的主要發音（is_primary），沒有指定就播最早的一段', () => {
    editor()
    const wrapper = mountTop({ name: 'Ivey、Ivay', audios: [rec(3, 'Ivey'), { ...rec(7, 'Ivey'), is_primary: true }, rec(5, 'Ivay')] })

    const urls = wrapper.get('h1').findAll('[data-testid="volume"]').map((v) => v.attributes('data-url'))
    expect(urls).toEqual(['/f7.m4a', '/f5.m4a'])
  })

  it('所有拼字都對不到錄音（舊資料尚未對應）時，沿用魚名旁的單一播放鈕', () => {
    editor()
    const wrapper = mountTop({ name: 'Ivey、Ivay', audios: [{ ...rec(1, null) }] })

    expect(wrapper.find('[data-testid="spelling"]').exists()).toBe(false)
    const volumes = wrapper.findAll('[data-testid="volume"]')
    expect(volumes).toHaveLength(1)
    expect(volumes[0].attributes('data-url')).toBe('/main.m4a')
  })

  it('只有一個名稱：魚名旁一個播放鈕，播該拼字的主要發音（不是魚的目前發音檔）', () => {
    editor()
    const wrapper = mountTop({ name: 'Ivey', audio_url: '/pointer.m4a', audios: [rec(3, 'Ivey'), { ...rec(7, 'Ivey'), is_primary: true }] })

    expect(wrapper.find('[data-testid="spelling"]').exists()).toBe(false)
    const volumes = wrapper.findAll('[data-testid="volume"]')
    expect(volumes).toHaveLength(1)
    expect(volumes[0].attributes('data-url')).toBe('/f7.m4a')
  })

  it('只有一個名稱而沒有指定：播最早的一段', () => {
    editor()
    const wrapper = mountTop({ name: 'Ivey', audio_url: '/pointer.m4a', audios: [rec(8, 'Ivey'), rec(3, 'Ivey')] })

    expect(wrapper.get('[data-testid="volume"]').attributes('data-url')).toBe('/f3.m4a')
  })

  it('只有一個名稱而錄音都沒有對應拼字（舊資料）：回退到魚的目前發音檔', () => {
    editor()
    const wrapper = mountTop({ name: 'Ivey', audio_url: '/pointer.m4a', audios: [rec(3, null)] })

    expect(wrapper.get('[data-testid="volume"]').attributes('data-url')).toBe('/pointer.m4a')
  })

  it('沒有播放權限的角色看不到任何播放鈕', () => {
    const wrapper = mountTop({ name: 'Ivey、Ivay', audios: [rec(3, 'Ivey')] })

    expect(wrapper.find('[data-testid="spelling"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="volume"]').exists()).toBe(false)
  })
})

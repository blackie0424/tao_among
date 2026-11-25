import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import FishAudioSection from '@/Components/FishDetail/FishAudioSection.vue'

vi.mock('@/Components/UI/Volume.vue', () => ({
  default: { props: ['audioUrl', 'audioId'], template: '<div data-testid="volume" :data-url="audioUrl" />' },
}))

const rec = (id, spoken_name, extra = {}) => ({ id, name: `f${id}.m4a`, url: `/f${id}.m4a`, spoken_name, ...extra })
const mountSection = (fish) =>
  mount(FishAudioSection, { props: { fish: { id: 1, audio_url: null, audios: [], ...fish } } })

// 每個拼字一列：['Ivey ▶/f3.m4a', 'Ivay 還沒有錄音']
const rows = (wrapper) =>
  wrapper.findAll('[data-testid="audio-spelling-row"]').map((row) => {
    const volume = row.find('[data-testid="volume"]')
    const state = volume.exists() ? `▶${volume.attributes('data-url')}` : row.get('[data-testid="no-recording"]').text()
    return `${row.get('[data-testid="spelling"]').text()} ${state}`
  })

describe('發音分頁依拼字列出（#176 AC 1）', () => {
  it('多個拼字：依魚名順序一個拼字一列，有錄音的有播放鈕，沒有的顯示「還沒有錄音」', () => {
    const wrapper = mountSection({ name: 'Ivey、Ivay、Ivoy', audios: [rec(5, 'Ivay'), rec(3, 'Ivey')] })

    expect(rows(wrapper)).toEqual(['Ivey ▶/f3.m4a', 'Ivay ▶/f5.m4a', 'Ivoy 還沒有錄音'])
  })

  it('每個拼字播它的主要發音（is_primary），沒有指定就播最早（編號最小）的一段', () => {
    const wrapper = mountSection({
      name: 'Ivey、Ivay',
      audios: [rec(3, 'Ivey'), rec(7, 'Ivey', { is_primary: true }), rec(9, 'Ivay'), rec(5, 'Ivay')],
    })

    expect(rows(wrapper)).toEqual(['Ivey ▶/f7.m4a', 'Ivay ▶/f5.m4a'])
  })

  it('單一拼字也是一列', () => {
    const wrapper = mountSection({ name: 'Ivey', audios: [rec(3, 'Ivey')] })

    expect(rows(wrapper)).toEqual(['Ivey ▶/f3.m4a'])
  })

  it('同一個拼字有多段錄音時只有一個播放鈕', () => {
    const wrapper = mountSection({ name: 'Ivey', audios: [rec(3, 'Ivey'), rec(4, 'Ivey'), rec(8, 'Ivey')] })

    expect(wrapper.findAll('[data-testid="volume"]')).toHaveLength(1)
  })

  it('錄音沒有播放網址（例如已刪除）不算有錄音', () => {
    const wrapper = mountSection({ name: 'Ivey、Ivay', audios: [rec(3, 'Ivey', { url: null }), rec(5, 'Ivay')] })

    expect(rows(wrapper)).toEqual(['Ivey 還沒有錄音', 'Ivay ▶/f5.m4a'])
  })

  it('沒有目前發音檔時，拼字都沒錄音就每個拼字列「還沒有錄音」，不顯示「尚無發音資料」', () => {
    const wrapper = mountSection({ name: 'Ivey、Ivay', audios: [] })

    expect(rows(wrapper)).toEqual(['Ivey 還沒有錄音', 'Ivay 還沒有錄音'])
    expect(wrapper.text()).not.toContain('尚無發音資料')
  })
})

describe('發音分頁舊資料回退（#176 AC 2）', () => {
  it('所有拼字都對不到錄音而魚有目前發音檔：只顯示一列「目前發音檔」，不列拼字', () => {
    const wrapper = mountSection({ name: 'Ivey、Ivay', audio_url: '/main.m4a', audios: [rec(1, null), rec(2, '已改名的字')] })

    expect(wrapper.find('[data-testid="spelling"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('目前發音檔')
    const volumes = wrapper.findAll('[data-testid="volume"]')
    expect(volumes).toHaveLength(1)
    expect(volumes[0].attributes('data-url')).toBe('/main.m4a')
  })

  it('至少一個拼字有錄音時不顯示「目前發音檔」', () => {
    const wrapper = mountSection({ name: 'Ivey、Ivay', audio_url: '/main.m4a', audios: [rec(3, 'Ivey')] })

    expect(wrapper.text()).not.toContain('目前發音檔')
    expect(rows(wrapper)).toEqual(['Ivey ▶/f3.m4a', 'Ivay 還沒有錄音'])
  })

  it('魚名為空且沒有發音檔：以輔助文字色顯示「尚無發音資料」', () => {
    const wrapper = mountSection({ name: '', audio_url: null })

    const empty = wrapper.get('p')
    expect(empty.text()).toBe('尚無發音資料')
    expect(empty.classes()).toContain('text-elder-subtext')
    expect(wrapper.find('[data-testid="volume"]').exists()).toBe(false)
  })

  it('魚名為空但有發音檔：顯示一列「目前發音檔」', () => {
    const wrapper = mountSection({ name: '、 、', audio_url: '/main.m4a' })

    expect(wrapper.text()).toContain('目前發音檔')
    expect(wrapper.get('[data-testid="volume"]').attributes('data-url')).toBe('/main.m4a')
    expect(wrapper.text()).not.toContain('尚無發音資料')
  })
})

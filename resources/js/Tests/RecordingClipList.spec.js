import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@/Components/FishResponses/RecordingPlayButton.vue', async () => {
  const { h } = await import('vue')
  return {
    default: {
      name: 'RecordingPlayButton',
      props: ['src', 'knownSeconds'],
      render() { return h('span', { 'data-url': this.src, 'data-known-seconds': this.knownSeconds, role: 'button' }) },
    },
  }
})

import RecordingClipList from '@/Components/FishResponses/RecordingClipList.vue'

const clipsOf = (count) => Array.from({ length: count }, (_, i) => ({ recording_url: `https://s3.test/${i + 1}.m4a`, recording_seconds: i + 4 }))
const mountList = (count) => mount(RecordingClipList, { props: { clips: clipsOf(count) } })
const rows = (wrapper) => wrapper.findAll('[data-testid="clip-row"]')
const more = (wrapper) => wrapper.find('[data-testid="clips-more"]')
const less = (wrapper) => wrapper.find('[data-testid="clips-less"]')

describe('RecordingClipList 有人只用說的', () => {
  it('沒有錄音時什麼都不顯示', () => {
    const wrapper = mountList(0)
    expect(wrapper.text()).toBe('')
    expect(wrapper.find('[data-testid="clip-list"]').exists()).toBe(false)
  })

  it.each([1, 2, 3])('%i 段：全部條列顯示，不出現收合按鈕', (count) => {
    const wrapper = mountList(count)
    expect(wrapper.text()).toContain('有人只用說的')
    expect(rows(wrapper)).toHaveLength(count)
    expect(more(wrapper).exists()).toBe(false)
    expect(less(wrapper).exists()).toBe(false)
  })

  it('每段一列：「第 N 段」、播放鈕拿到網址與秒數', () => {
    const wrapper = mountList(2)
    expect(rows(wrapper).map((row) => row.text())).toEqual(['第 1 段', '第 2 段'])
    expect(rows(wrapper).map((row) => row.find('[data-url]').attributes('data-url'))).toEqual(['https://s3.test/1.m4a', 'https://s3.test/2.m4a'])
    expect(rows(wrapper).map((row) => row.find('[data-url]').attributes('data-known-seconds'))).toEqual(['4', '5'])
  })

  it('舊資料沒有秒數：播放鈕拿到空值', () => {
    const wrapper = mount(RecordingClipList, { props: { clips: [{ recording_url: 'https://s3.test/old.m4a', recording_seconds: null }] } })
    expect(rows(wrapper)[0].find('[data-url]').attributes('data-known-seconds')).toBeUndefined()
  })

  it('5 段：先顯示 3 段，另有「還有 2 段，全部展開」；展開後 5 段並可收起', async () => {
    const wrapper = mountList(5)
    expect(rows(wrapper)).toHaveLength(3)
    expect(more(wrapper).text()).toBe('還有 2 段，全部展開 ▾')
    expect(more(wrapper).attributes('aria-expanded')).toBe('false')
    await more(wrapper).trigger('click')
    expect(rows(wrapper)).toHaveLength(5)
    expect(rows(wrapper).at(4).text()).toBe('第 5 段')
    expect(more(wrapper).exists()).toBe(false)
    expect(less(wrapper).text()).toBe('收起 ▴')
    expect(less(wrapper).attributes('aria-expanded')).toBe('true')
    await less(wrapper).trigger('click')
    expect(rows(wrapper)).toHaveLength(3)
    expect(more(wrapper).exists()).toBe(true)
  })

  it('剛好超過一段（4 段）：「還有 1 段」', () => {
    expect(more(mountList(4)).text()).toBe('還有 1 段，全部展開 ▾')
  })

  it('預設只露 3 段，順序照傳入的先後（最早的在前）', () => {
    const wrapper = mountList(8)
    expect(rows(wrapper).map((row) => row.find('[data-url]').attributes('data-url'))).toEqual(['https://s3.test/1.m4a', 'https://s3.test/2.m4a', 'https://s3.test/3.m4a'])
  })
})

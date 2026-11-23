import { mount, flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import RecordingClipList from '@/Components/FishResponses/RecordingClipList.vue'

// 這裡不模擬播放鈕：用假的 Audio，確認展開清單後的每一段「一次只播一個」
let instances
class FakeAudio {
  constructor(src) {
    this.src = src
    this.currentTime = 0
    this.duration = NaN
    this.listeners = {}
    this.paused = true
    instances.push(this)
  }
  addEventListener(name, fn) { (this.listeners[name] ??= []).push(fn) }
  removeEventListener(name, fn) { this.listeners[name] = (this.listeners[name] ?? []).filter((f) => f !== fn) }
  emit(name) { (this.listeners[name] ?? []).forEach((fn) => fn()) }
  play() { this.paused = false; return Promise.resolve() }
  pause() { this.paused = true }
}

const clips = Array.from({ length: 5 }, (_, i) => ({ recording_url: `https://s3.test/c${i + 1}.m4a`, recording_seconds: i + 3 }))
const players = (wrapper) => wrapper.findAll('[data-testid="clip-row"] button')
const stateOf = (button) => button.attributes('data-state')

beforeEach(() => {
  instances = []
  vi.stubGlobal('Audio', FakeAudio)
})
afterEach(() => vi.unstubAllGlobals())

describe('展開清單後的播放：一次只播一段', () => {
  it('展開後播第 4 段，再播第 1 段：第 4 段自動停止，只有第 1 段在播', async () => {
    const wrapper = mount(RecordingClipList, { props: { clips } })
    expect(players(wrapper)).toHaveLength(3)
    await wrapper.find('[data-testid="clips-more"]').trigger('click')
    expect(players(wrapper)).toHaveLength(5)

    await players(wrapper)[3].trigger('click')
    instances[0].emit('playing')
    await flushPromises()
    expect(stateOf(players(wrapper)[3])).toBe('playing')
    expect(instances[0].src).toBe('https://s3.test/c4.m4a')

    await players(wrapper)[0].trigger('click')
    await flushPromises()
    expect(stateOf(players(wrapper)[3])).toBe('idle')
    expect(instances[0].paused).toBe(true)
    expect(stateOf(players(wrapper)[0])).toBe('loading')
    expect(instances[1].src).toBe('https://s3.test/c1.m4a')
    expect(instances.filter((audio) => !audio.paused)).toHaveLength(1)
  })

  it('先播第 1 段再展開、播第 5 段：第 1 段停止；收合時被藏起來的第 5 段也停止，之後再播仍只有一段在播', async () => {
    const wrapper = mount(RecordingClipList, { props: { clips } })
    await players(wrapper)[0].trigger('click')
    instances[0].emit('playing')
    await wrapper.find('[data-testid="clips-more"]').trigger('click')
    await players(wrapper)[4].trigger('click')
    await flushPromises()
    expect(instances[0].paused).toBe(true)
    expect(instances.filter((audio) => !audio.paused)).toHaveLength(1)

    await wrapper.find('[data-testid="clips-less"]').trigger('click')
    expect(instances.filter((audio) => !audio.paused)).toHaveLength(0) // 收合後看不見的那一段不會繼續播
    await players(wrapper)[1].trigger('click')
    expect(instances.filter((audio) => !audio.paused)).toHaveLength(1)
  })
})

import { mount, flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import RecordingPlayButton from '@/Components/FishResponses/RecordingPlayButton.vue'

let instances
class FakeAudio {
  constructor(src) {
    this.src = src
    this.currentTime = 0
    this.duration = NaN
    this.listeners = {}
    this.paused = true
    this.playResult = 'ok'
    instances.push(this)
  }
  addEventListener(name, fn) { (this.listeners[name] ??= []).push(fn) }
  removeEventListener(name, fn) { this.listeners[name] = (this.listeners[name] ?? []).filter((f) => f !== fn) }
  emit(name) { (this.listeners[name] ?? []).forEach((fn) => fn()) }
  play() {
    this.paused = false
    return this.playResult === 'reject' ? Promise.reject(new Error('blocked')) : Promise.resolve()
  }
  pause() { this.paused = true }
}

const originalPlay = FakeAudio.prototype.play

const mountBar = (props = {}) => mount(RecordingPlayButton, { props: { src: 'blob:a', variant: 'bar', ...props }, attrs: { 'data-testid': 'play' } })
const mountCircle = (props = {}) => mount(RecordingPlayButton, { props: { src: 'https://s3.test/a.m4a', ...props }, attrs: { 'data-testid': 'play' } })
const state = (wrapper) => wrapper.find('[data-testid="play"]').attributes('data-state')
const press = (wrapper) => wrapper.find('[data-testid="play"]').trigger('click')

beforeEach(() => {
  FakeAudio.prototype.play = originalPlay
  instances = []
  vi.stubGlobal('Audio', FakeAudio)
})
afterEach(() => vi.unstubAllGlobals())

describe('RecordingPlayButton 狀態', () => {
  it('平常是「▶ 聽聽看」，不建立播放器', () => {
    const wrapper = mountBar()
    expect(state(wrapper)).toBe('idle')
    expect(wrapper.text()).toContain('聽聽看')
    expect(instances).toHaveLength(0)
  })

  it('剛按下去馬上變成「準備播放…」（聲音還沒出來），並出現載入中的圖示', async () => {
    const wrapper = mountBar()
    await press(wrapper)
    expect(state(wrapper)).toBe('loading')
    expect(wrapper.text()).toContain('準備播放')
    expect(wrapper.find('[data-testid="play-spinner"]').exists()).toBe(true)
    expect(instances[0].src).toBe('blob:a')
    expect(instances[0].paused).toBe(false)
  })

  it('聲音開始後顯示「播放中 已播／總秒數」，總秒數優先用傳入的錄音秒數', async () => {
    const wrapper = mountBar({ knownSeconds: 12 })
    await press(wrapper)
    instances[0].duration = Infinity // 錄音檔長度在部分瀏覽器讀不到
    instances[0].emit('playing')
    instances[0].currentTime = 3.4
    instances[0].emit('timeupdate')
    await flushPromises()
    expect(state(wrapper)).toBe('playing')
    expect(wrapper.text()).toContain('播放中 0:03／0:12')
    expect(wrapper.find('[data-testid="play-progress"]').attributes('style')).toContain('width: 28')
  })

  it('沒有傳入秒數時用瀏覽器讀到的長度；讀不到就只顯示已播秒數', async () => {
    const wrapper = mountCircle()
    await press(wrapper)
    instances[0].duration = 75
    instances[0].emit('playing')
    instances[0].currentTime = 65
    instances[0].emit('timeupdate')
    await flushPromises()
    expect(wrapper.find('[data-testid="play-time"]').text()).toBe('1:05／1:15')
    instances[0].duration = Infinity
    instances[0].emit('timeupdate')
    await flushPromises()
    expect(wrapper.find('[data-testid="play-time"]').text()).toBe('1:05')
  })

  it('播放中再按一下：停止並回到開頭', async () => {
    const wrapper = mountBar({ knownSeconds: 12 })
    await press(wrapper)
    instances[0].emit('playing')
    instances[0].currentTime = 5
    await press(wrapper)
    expect(state(wrapper)).toBe('idle')
    expect(instances[0].paused).toBe(true)
    expect(instances[0].currentTime).toBe(0)
  })

  it('載入中再按一下也能取消', async () => {
    const wrapper = mountBar()
    await press(wrapper)
    await press(wrapper)
    expect(state(wrapper)).toBe('idle')
    expect(instances[0].paused).toBe(true)
  })

  it('播完：試聽按鈕變成「▶ 再聽一次」，再按會從頭播', async () => {
    const wrapper = mountBar({ knownSeconds: 12 })
    await press(wrapper)
    instances[0].emit('playing')
    instances[0].currentTime = 12
    instances[0].emit('ended')
    await flushPromises()
    expect(state(wrapper)).toBe('ended')
    expect(wrapper.text()).toContain('再聽一次')
    await press(wrapper)
    expect(state(wrapper)).toBe('loading')
    expect(instances[0].currentTime).toBe(0)
  })

  it('播放失敗（被瀏覽器擋或檔案壞了）：顯示失敗文字，再按一次可重試', async () => {
    const wrapper = mountBar({ errorLabel: '無法播放，請重錄' })
    instances.length = 0
    await press(wrapper)
    instances[0].emit('error')
    await flushPromises()
    expect(state(wrapper)).toBe('error')
    expect(wrapper.text()).toContain('無法播放，請重錄')
    await press(wrapper)
    expect(state(wrapper)).toBe('loading')
  })

  it('play() 被拒絕也算失敗，不會停在載入中', async () => {
    const wrapper = mountBar()
    const original = FakeAudio.prototype.play
    FakeAudio.prototype.play = () => Promise.reject(new Error('blocked'))
    await press(wrapper)
    await flushPromises()
    FakeAudio.prototype.play = original
    expect(state(wrapper)).toBe('error')
  })

  it('圓形圖示版：播放中有進度外圈與秒數，失敗時的說明文字給輔助技術', async () => {
    const wrapper = mountCircle({ knownSeconds: 10 })
    expect(wrapper.find('[data-testid="play-time"]').exists()).toBe(false)
    await press(wrapper)
    instances[0].emit('playing')
    instances[0].currentTime = 5
    instances[0].emit('timeupdate')
    await flushPromises()
    expect(wrapper.find('[data-testid="play-time"]').text()).toBe('0:05／0:10')
    expect(wrapper.find('[data-testid="play-ring"]').attributes('data-percent')).toBe('50')
    instances[0].emit('error')
    await flushPromises()
    expect(wrapper.find('[data-testid="play"]').attributes('aria-label')).toContain('無法播放')
  })

  it('同一時間只播一段：開始播另一段，前一段自動停止', async () => {
    const first = mountCircle({ src: 'https://s3.test/1.m4a' })
    const second = mountCircle({ src: 'https://s3.test/2.m4a' })
    await press(first)
    instances[0].emit('playing')
    await press(second)
    expect(state(first)).toBe('idle')
    expect(instances[0].paused).toBe(true)
    expect(state(second)).toBe('loading')
  })

  it('元件移除時停止播放', async () => {
    const wrapper = mountCircle()
    await press(wrapper)
    instances[0].emit('playing')
    wrapper.unmount()
    expect(instances[0].paused).toBe(true)
  })

  it('錄音網址換了：回到平常並停止舊的播放', async () => {
    const wrapper = mountBar({ src: 'blob:old' })
    await press(wrapper)
    instances[0].emit('playing')
    await wrapper.setProps({ src: 'blob:new' })
    expect(state(wrapper)).toBe('idle')
    expect(instances[0].paused).toBe(true)
  })

  it('載入中停止後，play() 才延遲拒絕：按鈕維持平常，不會變成失敗', async () => {
    const wrapper = mountBar()
    let rejectPlay
    FakeAudio.prototype.play = function () { return new Promise((_, reject) => { rejectPlay = reject }) }
    await press(wrapper) // 載入中
    await press(wrapper) // 停止
    rejectPlay(new Error('late'))
    await flushPromises()
    expect(state(wrapper)).toBe('idle')
  })

  it('舊的 play() 延遲拒絕，不會影響已重新開始的播放，也不會讓兩段同時播放', async () => {
    const first = mountCircle({ src: 'https://s3.test/1.m4a' })
    const second = mountCircle({ src: 'https://s3.test/2.m4a' })
    const rejects = []
    FakeAudio.prototype.play = function () { return new Promise((_, reject) => { rejects.push(reject) }) }
    await press(first) // 第一次播放（之後會晚到拒絕）
    await press(first) // 停止
    await press(first) // 立刻重播
    instances[0].emit('playing')
    rejects[0](new Error('late')) // 舊的那次現在才拒絕
    await flushPromises()
    expect(state(first)).toBe('playing')
    await press(second) // 再播另一段：第一段必須停下，不會兩段同時播
    expect(state(first)).toBe('idle')
    expect(instances[0].paused).toBe(true)
    expect(state(second)).toBe('loading')
  })

  it('停止後才晚到的「播放中」或錯誤事件，不會把按鈕改回播放中或失敗', async () => {
    const wrapper = mountBar()
    await press(wrapper)
    await press(wrapper) // 停止
    instances[0].emit('playing')
    instances[0].emit('error')
    await flushPromises()
    expect(state(wrapper)).toBe('idle')
  })

  it('圓形版失敗時看得到「無法播放」文字（不只是驚嘆號），再按一次可重試', async () => {
    const wrapper = mountCircle()
    await press(wrapper)
    instances[0].emit('error')
    await flushPromises()
    expect(wrapper.find('[data-testid="play-error"]').text()).toContain('無法播放')
    await press(wrapper)
    expect(state(wrapper)).toBe('loading')
    expect(wrapper.find('[data-testid="play-error"]').exists()).toBe(false)
  })

  it('短錄音（1 秒）且瀏覽器讀不到長度：仍顯示「已播／1 秒」與進度', async () => {
    const wrapper = mountBar({ knownSeconds: 1 })
    await press(wrapper)
    instances[0].duration = Infinity
    instances[0].emit('playing')
    instances[0].currentTime = 0.5
    instances[0].emit('timeupdate')
    await flushPromises()
    expect(wrapper.text()).toContain('播放中 0:00／0:01')
    expect(wrapper.find('[data-testid="play-progress"]').attributes('style')).toContain('width: 50%')
    expect(wrapper.find('[data-testid="play-progress"]').attributes('data-indeterminate')).toBeUndefined()
  })

  it('不知道總長度（說法卡讀不到）：只顯示已播秒數，進度改成閃動而不是停在 0%', async () => {
    const wrapper = mountCircle()
    await press(wrapper)
    instances[0].duration = NaN
    instances[0].emit('playing')
    instances[0].currentTime = 2
    instances[0].emit('timeupdate')
    await flushPromises()
    expect(wrapper.find('[data-testid="play-time"]').text()).toBe('0:02')
    expect(wrapper.find('[data-testid="play-ring"]').attributes('data-indeterminate')).toBe('true')
  })
})

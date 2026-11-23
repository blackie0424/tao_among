import { mount, flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', async () => {
  const { reactive } = await import('vue')
  return {
    useForm: (init) => {
      const form = reactive({ ...init, errors: {}, processing: false })
      form.setError = () => {}
      form.clearErrors = () => {}
      form.reset = () => {}
      form.transform = () => form
      form.post = () => {}
      form.delete = () => {}
      return form
    },
  }
})

import FishResponses from '@/Components/FishResponses/FishResponses.vue'

// 這裡不模擬播放鈕：用假的 Audio，確認「大家的說法」裡各處的播放（單顆播放鈕與展開的清單）一次只播一個
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

const clip = (id) => ({ recording_url: `https://s3.test/${id}.m4a`, recording_seconds: 5 })
const options = {
  tribes: ['ivalino', 'yayo'],
  food_categories: ['oyod', 'rahet', '不分類', '不食用', 'kakanen no rarakeh'],
  name_options: ['mamalan'],
  name_max_length: 60,
  recording_max_seconds: 30,
}
const summary = [
  { tribe: 'ivalino', has_responses: false, names: [], recordings_only: [], has_variants: false, categories: [] },
  {
    tribe: 'yayo', has_responses: true, has_variants: true, recordings_only: [{ ...clip('only') }], categories: [],
    names: [{ name: 'dalakan', count: 1, recordings: [clip('single')] }, { name: 'rakan', count: 4, recordings: [clip('m1'), clip('m2'), clip('m3'), clip('m4')] }],
  },
]
const playing = () => instances.filter((audio) => !audio.paused).map((audio) => audio.src)

beforeEach(() => {
  instances = []
  vi.stubGlobal('Audio', FakeAudio)
})
afterEach(() => vi.unstubAllGlobals())

describe('大家的說法：一次只播一個', () => {
  const open = async () => {
    const wrapper = mount(FishResponses, { props: { fishId: 5, options, summary, mine: [], responders: 4 } })
    await wrapper.findAll('button').find((b) => b.text().includes('看大家怎麼說')).trigger('click')
    return wrapper
  }

  it('播單顆播放鈕，再播展開清單的某一段：前一個自動停止；再播「有人只用說的」也一樣', async () => {
    const wrapper = await open()
    await wrapper.find('[data-testid="name-clips-toggle"]').trigger('click')
    await wrapper.find('[data-testid="name-recording"]').trigger('click')
    expect(playing()).toEqual(['https://s3.test/single.m4a'])

    await wrapper.findAll('[data-testid="name-clips"] [data-testid="clip-row"] button')[1].trigger('click')
    expect(playing()).toEqual(['https://s3.test/m2.m4a'])

    await wrapper.find('[data-testid="clip-list"]:not([data-testid="name-clips"] *) [data-testid="clip-row"] button').trigger('click')
    expect(playing()).toEqual(['https://s3.test/only.m4a'])
  })

  it('展開的清單裡正在播的那一段，收合叫法組時停止，不會在看不見時繼續播', async () => {
    const wrapper = await open()
    await wrapper.find('[data-testid="name-clips-toggle"]').trigger('click')
    await wrapper.findAll('[data-testid="name-clips"] [data-testid="clip-row"] button')[0].trigger('click')
    await flushPromises()
    expect(playing()).toEqual(['https://s3.test/m1.m4a'])
    await wrapper.find('[data-testid="name-clips-toggle"]').trigger('click')
    expect(wrapper.find('[data-testid="name-clips"]').exists()).toBe(false)
    expect(playing()).toEqual([])
  })
})

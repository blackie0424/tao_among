import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'

const post = vi.fn()
const del = vi.fn()
vi.mock('@/Components/FishResponses/RecordingPlayButton.vue', async () => {
  const { h } = await import('vue')
  // 播放狀態本身另在 RecordingPlayButton.spec.js 測；這裡只確認各處放了播放鈕、播的是哪個網址
  return {
    default: {
      name: 'RecordingPlayButton',
      props: ['src', 'variant', 'knownSeconds', 'idleLabel', 'endedLabel', 'errorLabel'],
      render() { return h('span', { 'data-url': this.src, 'data-variant': this.variant ?? 'circle', 'data-known-seconds': this.knownSeconds, role: 'button' }) },
    },
  }
})
vi.mock('@inertiajs/vue3', async () => {
  const { reactive } = await import('vue')
  return {
    useForm: (init) => {
      const form = reactive({ ...init, errors: {}, processing: false })
      form.setError = (field, message) => { form.errors[field] = message }
      form.clearErrors = () => { form.errors = {} }
      form.reset = () => Object.assign(form, init)
      form.transform = (fn) => { form._transform = fn; return form }
      form.post = (url, options) => {
        const result = post(url, form._transform({ tribe: form.tribe, name: form.name, food_category: form.food_category }), options)
        if (result?.errors) {
          Object.assign(form.errors, result.errors)
          options?.onError?.(result.errors)
        } else options?.onSuccess?.()
      }
      form.delete = (url, options) => {
        const result = del(url, { tribe: form.tribe }, options)
        if (result?.errors) Object.assign(form.errors, result.errors)
        else options?.onSuccess?.()
      }
      return form
    },
  }
})

import FishResponses from '@/Components/FishResponses/FishResponses.vue'

const ASTRAL = '\u{20BB7}' // 一個 Unicode 字元，UTF-16 佔 2 個單位
const options = {
  tribes: ['ivalino', 'yayo'],
  food_categories: ['oyod', 'rahet', '不分類', '不食用', 'kakanen no rarakeh'],
  name_options: ['mamalan'],
  name_max_length: 60,
  recording_max_seconds: 30,
}
const none = [
  { tribe: 'ivalino', has_responses: false, names: [], has_variants: false, categories: [] },
  { tribe: 'yayo', has_responses: false, names: [], has_variants: false, categories: [] },
]
const responded = [
  none[0],
  { tribe: 'yayo', has_responses: true, names: [{ name: 'dalakan', count: 2 }, { name: 'B', count: 1 }], has_variants: true, categories: [{ value: 'oyod', count: 4 }, { value: 'rahet', count: 1 }, { value: '不分類', count: 0 }] },
  { tribe: 'iratay', has_responses: true, names: [{ name: 'mamalan', count: 1 }], has_variants: false, categories: [{ value: 'oyod', count: 0 }] },
]

const mountFlow = (props = {}) => mount(FishResponses, { props: { fishId: 5, options, summary: none, responders: 0, ...props } })
const click = (wrapper, text) => wrapper.findAll('button').find((b) => b.text().includes(text)).trigger('click')
const yes = (wrapper) => wrapper.find('button[aria-label="是"]').trigger('click')
const no = async (wrapper) => {
  await wrapper.find('button[aria-label="不是"]').trigger('click')
  await wrapper.find('[data-testid="method-type"]').trigger('click')
}
const typeMethod = (wrapper) => wrapper.find('[data-testid="method-type"]').trigger('click')
const type = (wrapper, text) => wrapper.find('input[aria-label="叫法"]').setValue(text)

beforeEach(() => {
  post.mockReset()
  del.mockReset()
})

describe('FishResponses 問答順序', () => {
  it('第一個畫面直接問「你們也叫它 mamalan 嗎？」，不顯示 0 或空表', () => {
    const wrapper = mountFlow()
    expect(wrapper.find('[data-testid="step-confirm"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('你們也叫它 mamalan 嗎？')
    expect(wrapper.text()).not.toContain('還沒有人')
  })

  it('問題的文字：選部落問「你要提供哪個部落的說法？」、叫法問「你們會怎麼稱呼呢？」', async () => {
    const wrapper = mountFlow()
    await no(wrapper)
    expect(wrapper.text()).toContain('你們會怎麼稱呼呢？')
    await type(wrapper, 'dalakan')
    await click(wrapper, 'OK')
    expect(wrapper.text()).toContain('你要提供哪個部落的說法？')
  })

  it('✔ → 選部落，才把目前的魚名存成這個部落的叫法，接著問分類', async () => {
    const wrapper = mountFlow()
    await yes(wrapper)
    expect(post).not.toHaveBeenCalled()
    await click(wrapper, 'yayo')
    expect(post).toHaveBeenCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'mamalan', food_category: '' }, expect.anything())
    expect(wrapper.find('[data-testid="step-category"]').exists()).toBe(true)
  })

  it('選了分類會連同叫法一起再送出，然後直接看大家怎麼說（不再多一個謝謝畫面）', async () => {
    const wrapper = mountFlow()
    await yes(wrapper)
    await click(wrapper, 'yayo')
    await click(wrapper, 'oyod')
    expect(post).toHaveBeenLastCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'mamalan', food_category: 'oyod' }, expect.anything())
    expect(wrapper.find('[data-testid="step-others"]').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('謝謝')
  })

  it('略過分類不會再送出', async () => {
    const wrapper = mountFlow()
    await yes(wrapper)
    await click(wrapper, 'yayo')
    await click(wrapper, '略過')
    expect(post).toHaveBeenCalledTimes(1)
    expect(wrapper.find('[data-testid="step-others"]').exists()).toBe(true)
  })

  it('✖ 後保留文字輸入，選好部落後送出的是去空白後的叫法', async () => {
    const wrapper = mountFlow()
    await no(wrapper)
    await type(wrapper, '  dalakan  ')
    await click(wrapper, 'OK')
    await click(wrapper, 'yayo')
    expect(post).toHaveBeenCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'dalakan', food_category: '' }, expect.anything())
  })

  it('魚名是「我不知道」時（沒有名字選項），第一題直接問這條魚叫什麼', async () => {
    const wrapper = mountFlow({ options: { ...options, name_options: [] } })
    expect(wrapper.find('[data-testid="step-confirm"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('這條魚，你們會怎麼稱呼呢？')
    await typeMethod(wrapper)
    await type(wrapper, 'dalakan')
    await click(wrapper, 'OK')
    await click(wrapper, 'yayo')
    expect(post).toHaveBeenCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'dalakan', food_category: '' }, expect.anything())
  })
})

describe('FishResponses 做到一半離開，下次繼續', () => {
  it('已有叫法但還沒分類的部落，第一個畫面提示繼續，按下去直接問分類', async () => {
    const wrapper = mountFlow({ mine: [{ tribe: 'yayo', name: 'dalakan', food_category: null }] })
    expect(wrapper.find('[data-testid="step-resume"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('yayo')
    await click(wrapper, '繼續')
    expect(wrapper.find('[data-testid="step-category"]').exists()).toBe(true)
    await click(wrapper, 'oyod')
    expect(post).toHaveBeenCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'dalakan', food_category: 'oyod' }, expect.anything())
  })

  it('回答都完整時不提示繼續', () => {
    const wrapper = mountFlow({ mine: [{ tribe: 'yayo', name: 'dalakan', food_category: 'rahet' }] })
    expect(wrapper.find('[data-testid="step-resume"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="step-others"]').exists()).toBe(true)
  })

  it('還沒答過時第一個畫面是第一題，不是繼續', () => {
    expect(mountFlow().find('[data-testid="step-resume"]').exists()).toBe(false)
  })
})

describe('FishResponses 回應人數與直接查看', () => {
  it('每個畫面下方顯示這條魚的回應人數，0 人也顯示', () => {
    expect(mountFlow({ responders: 0 }).find('[data-testid="responders"]').text()).toBe('0 人回應')
    expect(mountFlow({ responders: 3 }).find('[data-testid="responders"]').text()).toBe('3 人回應')
  })

  it('別人已經有回答時，沒回答過的人在第一個畫面就有入口，可以直接看', async () => {
    const wrapper = mountFlow({ summary: responded, responders: 3 })
    await click(wrapper, '看大家怎麼說')
    expect(wrapper.findAll('[data-testid="response-tribe"]')).toHaveLength(2)
    expect(wrapper.text()).not.toContain('ivalino')
    expect(wrapper.find('[data-testid="responders"]').text()).toBe('3 人回應')
    expect(post).not.toHaveBeenCalled()
  })

  it('每個部落一張卡：每個叫法一列，只列名稱（不含人數）', async () => {
    const wrapper = mountFlow({ summary: responded })
    await click(wrapper, '看大家怎麼說')
    const [yayo, iratay] = wrapper.findAll('[data-testid="response-tribe"]')
    expect(yayo.findAll('[data-testid="name-row"]').map((row) => row.text())).toEqual(['dalakan', 'B'])
    expect(iratay.findAll('[data-testid="name-row"]').map((row) => row.text())).toEqual(['mamalan'])
    expect(yayo.text()).toContain('大家的說法')
  })

  it('分類逐項顯示人數、不列零人選項', async () => {
    const wrapper = mountFlow({ summary: responded })
    await click(wrapper, '看大家怎麼說')
    const categories = wrapper.findAll('[data-testid="response-tribe"]')[0].find('[data-testid="tribe-categories"]')
    expect(categories.text()).toContain('oyod 4 人')
    expect(categories.text()).toContain('rahet 1 人')
    expect(categories.text()).not.toContain('不分類')
  })

  it('沒有人選分類時顯示破折號', async () => {
    const wrapper = mountFlow({ summary: responded })
    await click(wrapper, '看大家怎麼說')
    const iratay = wrapper.findAll('[data-testid="response-tribe"]')[1]
    expect(iratay.find('[data-testid="tribe-categories"]').text()).toBe('分類—')
  })

  it('每一步最上方都有標題「大家怎麼說」', async () => {
    const wrapper = mountFlow({ summary: responded })
    expect(wrapper.find('h3').text()).toBe('大家怎麼說')
    await click(wrapper, '看大家怎麼說')
    expect(wrapper.find('h3').text()).toBe('大家怎麼說')
  })

  it('還沒有人回答時沒有看大家怎麼說的入口', () => {
    expect(mountFlow().findAll('button').some((b) => b.text().includes('看大家怎麼說'))).toBe(false)
  })
})

describe('FishResponses 答完直接看結果，自己的選擇特別標出', () => {
  const mine = [{ tribe: 'yayo', name: 'Dalakan', food_category: 'oyod' }]

  it('答過的人進來直接看大家怎麼說，自己的部落、叫法、分類都有標記', () => {
    const wrapper = mountFlow({ summary: responded, mine, responders: 3 })
    expect(wrapper.find('[data-testid="step-others"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="intro"]').text()).toBe('以下是不同部落的說法，提供參考。')
    expect(wrapper.text()).not.toContain('謝謝')
    const [yayo, iratay] = wrapper.findAll('[data-testid="response-tribe"]')
    expect(yayo.attributes('data-mine')).toBe('true')
    expect(iratay.attributes('data-mine')).toBeUndefined()
    const marked = yayo.findAll('[data-testid="tribe-names"] [data-mine]')
    expect(marked).toHaveLength(1)
    expect(marked[0].text()).toContain('dalakan')
    const categories = yayo.find('[data-testid="tribe-categories"]')
    expect(categories.findAll('[data-mine]')).toHaveLength(1)
    expect(categories.find('[data-mine]').text()).toContain('oyod ✔ 4 人')
    expect(categories.text()).toContain('rahet 1 人')
  })

  it('下方有「繼續提供說法」，沒有先出現謝謝畫面再多按一次', async () => {
    const wrapper = mountFlow({ summary: responded, mine })
    await click(wrapper, '繼續提供說法')
    expect(wrapper.find('[data-testid="step-confirm"]').exists()).toBe(true)
  })

  it('完成最後一步後直接顯示結果，說明句與剛填完、之後再進來時一樣，繼續提供說法的按鈕在下方', async () => {
    const wrapper = mountFlow({ summary: responded, mine })
    await click(wrapper, '繼續提供說法')
    await yes(wrapper)
    await click(wrapper, 'ivalino')
    await click(wrapper, 'oyod')
    expect(wrapper.find('[data-testid="step-others"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="intro"]').text()).toBe('以下是不同部落的說法，提供參考。')
    expect(wrapper.text()).not.toContain('謝謝')
    expect(wrapper.findAll('button').some((b) => b.text().includes('繼續提供說法'))).toBe(true)
  })

  it('還沒答過、從問題畫面點進來看的人有返回，沒有「繼續提供說法」', async () => {
    const wrapper = mountFlow({ summary: responded })
    await click(wrapper, '看大家怎麼說')
    expect(wrapper.find('button[aria-label="返回"]').exists()).toBe(true)
    expect(wrapper.findAll('button').some((b) => b.text().includes('繼續提供說法'))).toBe(false)
    await wrapper.find('button[aria-label="返回"]').trigger('click')
    expect(wrapper.find('[data-testid="step-confirm"]').exists()).toBe(true)
  })
})

describe('FishResponses 自己的叫法標記與後端分組一致', () => {
  const summaryWith = (names) => [
    { tribe: 'yayo', has_responses: true, names: names.map((name) => ({ name, count: 1 })), has_variants: names.length > 1, categories: [] },
  ]
  const marked = (wrapper) => wrapper.findAll('[data-testid="tribe-names"] [data-mine]').map((el) => el.element.textContent.replace(/ ✔$/, ''))
  const mineNamed = (name) => [{ tribe: 'yayo', name, food_category: 'oyod' }]

  it('ASCII 大小寫與前後空白視為同組：標出自己的叫法', () => {
    const wrapper = mountFlow({ summary: summaryWith(['dalakan', 'B']), mine: mineNamed(' DALAKAN\t') })
    expect(marked(wrapper)).toEqual(['dalakan'])
  })

  it('後端分成不同組的叫法（開頭多一個 U+FEFF）不會同時標成自己的', () => {
    const wrapper = mountFlow({ summary: summaryWith(['\uFEFFDalakan', 'Dalakan']), mine: mineNamed('Dalakan') })
    expect(marked(wrapper)).toEqual(['Dalakan'])
  })

  it('不換行空格 U+00A0 後端不去掉，所以和一般寫法是不同組，不會誤標', () => {
    const wrapper = mountFlow({ summary: summaryWith(['\u00A0Dalakan', 'Dalakan']), mine: mineNamed('\u00A0Dalakan') })
    expect(marked(wrapper)).toEqual(['\u00A0Dalakan'])
  })

  it('非英文字母不折大小寫：希臘文 Α 與 α 是不同組', () => {
    const wrapper = mountFlow({ summary: summaryWith(['\u0391', '\u03B1']), mine: mineNamed('\u03B1') })
    expect(marked(wrapper)).toEqual(['\u03B1'])
  })
})

describe('FishResponses 分類選項', () => {
  it('分類步驟列出 oyod、rahet、不分類、不食用與 kakanen no rarakeh，選了就送出', async () => {
    const wrapper = mountFlow()
    await yes(wrapper)
    await click(wrapper, 'yayo')
    const labels = wrapper.findAll('[data-testid="step-category"] button').map((b) => b.text())
    expect(labels).toEqual(expect.arrayContaining(['oyod', 'rahet', '不分類', '不食用', 'kakanen no rarakeh']))
    await click(wrapper, 'kakanen no rarakeh')
    expect(post).toHaveBeenLastCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'mamalan', food_category: 'kakanen no rarakeh' }, expect.anything())
  })

  it('長條圖也會畫出 kakanen no rarakeh', async () => {
    const summary = [{ tribe: 'yayo', has_responses: true, names: [{ name: 'a', count: 1 }], has_variants: false, categories: [{ value: 'kakanen no rarakeh', count: 2 }] }]
    const wrapper = mountFlow({ summary })
    await click(wrapper, '看大家怎麼說')
    expect(wrapper.find('[data-testid="tribe-categories"]').text()).toContain('kakanen no rarakeh')
  })
})

describe('FishResponses 顯示細節', () => {
  const summaryWith = (names) => [
    { tribe: 'yayo', has_responses: true, names: names.map((name) => ({ name, count: 1 })), has_variants: names.length > 1, categories: [] },
  ]

  it('叫法含 HTML 或特殊字元時當純文字顯示，不會被執行', async () => {
    const wrapper = mountFlow({ summary: summaryWith(['<img src=x onerror=alert(1)>', '<b>粗體</b>']) })
    await click(wrapper, '看大家怎麼說')
    const names = wrapper.find('[data-testid="tribe-names"]')
    expect(names.text()).toContain('<img src=x onerror=alert(1)>')
    expect(names.text()).toContain('<b>粗體</b>')
    expect(names.find('img').exists()).toBe(false)
    expect(names.find('b').exists()).toBe(false)
  })

  it('同一部落有兩種以上叫法時標「≠」（有不同說法），只有一種時不標', async () => {
    const many = mountFlow({ summary: summaryWith(['a', 'b']) })
    await click(many, '看大家怎麼說')
    expect(many.find('[aria-label="有不同說法"]').text()).toBe('≠ 有不同說法')
    const one = mountFlow({ summary: summaryWith(['a']) })
    await click(one, '看大家怎麼說')
    expect(one.find('[aria-label="有不同說法"]').exists()).toBe(false)
  })

  it('自己回答過的部落有 ✔ 標記，別的部落沒有', () => {
    const summary = [
      { tribe: 'ivalino', has_responses: true, names: [{ name: 'a', count: 1 }], has_variants: false, categories: [] },
      { tribe: 'yayo', has_responses: true, names: [{ name: 'b', count: 1 }], has_variants: false, categories: [] },
    ]
    const wrapper = mountFlow({ summary, mine: [{ tribe: 'yayo', name: 'b', food_category: 'oyod' }] })
    const [ivalino, yayo] = wrapper.findAll('[data-testid="response-tribe"]')
    expect(yayo.find('[aria-label="我選的"]').exists()).toBe(true)
    expect(ivalino.find('[aria-label="我選的"]').exists()).toBe(false)
  })
})

describe('FishResponses 返回鍵', () => {
  it('返回鍵是圖示按鈕（svg），不是文字符號 ‹', async () => {
    const wrapper = mountFlow()
    await yes(wrapper)
    const back = wrapper.find('button[aria-label="返回"]')
    expect(back.find('svg').exists()).toBe(true)
    expect(back.text()).toBe('')
    await back.trigger('click')
    expect(wrapper.find('[data-testid="step-confirm"]').exists()).toBe(true)
  })
})

describe('FishResponses 只填一項與修改', () => {
  const previous = [{ tribe: 'yayo', name: 'dalakan', food_category: 'rahet' }]

  it('可以只填分類：略過叫法、選部落、選分類，送出的叫法是空的', async () => {
    const wrapper = mountFlow()
    await no(wrapper)
    await click(wrapper, '略過')
    await click(wrapper, 'yayo')
    expect(post).not.toHaveBeenCalled()
    await click(wrapper, 'oyod')
    expect(post).toHaveBeenCalledTimes(1)
    expect(post).toHaveBeenCalledWith('/fish/5/responses', { tribe: 'yayo', name: '', food_category: 'oyod' }, expect.anything())
  })

  it('叫法與分類都略過時不送出，並提示至少填一項', async () => {
    const wrapper = mountFlow()
    await no(wrapper)
    await click(wrapper, '略過')
    await click(wrapper, 'yayo')
    await click(wrapper, '略過')
    expect(post).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('至少要填一項')
  })

  it('修改已有分類的回答時，送出叫法先沿用原本的分類，中途離開不會遺失', async () => {
    const wrapper = mountFlow({ mine: previous })
    await click(wrapper, '繼續提供說法')
    await yes(wrapper)
    await click(wrapper, 'yayo')
    expect(post).toHaveBeenCalledTimes(1)
    expect(post).toHaveBeenLastCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'mamalan', food_category: 'rahet' }, expect.anything())
    expect(wrapper.find('[data-testid="previous-category"]').text()).toContain('rahet')
  })

  it('修改已有分類的回答時，按略過分類才會清掉分類', async () => {
    const wrapper = mountFlow({ mine: previous })
    await click(wrapper, '繼續提供說法')
    await yes(wrapper)
    await click(wrapper, 'yayo')
    await click(wrapper, '略過')
    expect(post).toHaveBeenCalledTimes(2)
    expect(post).toHaveBeenLastCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'mamalan', food_category: '' }, expect.anything())
    expect(wrapper.find('[data-testid="step-others"]').exists()).toBe(true)
  })

  it('修改時可以清空叫法、保留分類：略過叫法再重選原本的分類', async () => {
    const wrapper = mountFlow({ mine: previous })
    await click(wrapper, '繼續提供說法')
    await no(wrapper)
    await click(wrapper, '略過')
    await click(wrapper, 'yayo')
    await click(wrapper, 'rahet')
    expect(post).toHaveBeenLastCalledWith('/fish/5/responses', { tribe: 'yayo', name: '', food_category: 'rahet' }, expect.anything())
  })
})

describe('FishResponses 叫法字數', () => {
  async function typeName(name) {
    const wrapper = mountFlow()
    await no(wrapper)
    await type(wrapper, name)
    await click(wrapper, 'OK')
    return wrapper
  }

  it('60 個非 BMP 字元加前後空白可以通過，送出的是去空白後的叫法', async () => {
    const wrapper = await typeName(`  ${ASTRAL.repeat(60)}　`)
    await click(wrapper, 'yayo')
    expect(post).toHaveBeenCalledWith('/fish/5/responses', expect.objectContaining({ name: ASTRAL.repeat(60) }), expect.anything())
  })

  it('61 個非 BMP 字元不往下走、不送出並顯示錯誤', async () => {
    const wrapper = await typeName(ASTRAL.repeat(61))
    expect(wrapper.find('[data-testid="step-tribe"]').exists()).toBe(false)
    expect(post).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('叫法最多 60 字')
  })
})


// ─── 叫法加入錄音（#157） ───────────────────────────────────

const byId = (wrapper, id) => wrapper.find(`[data-testid="${id}"]`)
const click_ = (wrapper, id) => byId(wrapper, id).trigger('click')
const SIGN = { ok: true, json: async () => ({ url: 'https://s3.test/put', filename: 'pending-1.m4a' }) }

function stubRecording({ mic = 'ok', mimeSupported = true, lateStop = false } = {}) {
  vi.stubGlobal('fetch', vi.fn())
  if (mic === 'unsupported') {
    vi.stubGlobal('MediaRecorder', undefined)
  } else {
    vi.stubGlobal('MediaRecorder', class {
      static isTypeSupported() { return mimeSupported }
      start() {}
      stop() {
        const finish = () => { this.ondataavailable({ data: new Blob(['audio']) }); this.onstop() }
        if (lateStop) Promise.resolve().then(finish)
        else finish()
      }
    })
  }
  Object.defineProperty(navigator, 'mediaDevices', {
    configurable: true,
    value: { getUserMedia: mic === 'denied' ? () => Promise.reject(new Error('denied')) : async () => ({ getTracks: () => [{ stop() {} }] }) },
  })
  URL.createObjectURL = vi.fn(() => 'blob:audio')
  HTMLMediaElement.prototype.play = vi.fn()
}

async function recordOnce(wrapper) {
  await click_(wrapper, 'method-speak')
  await click_(wrapper, 'record-start')
  await flushPromises()
  await click_(wrapper, 'record-stop')
  await flushPromises()
}

const goSpeak = async (props = {}) => {
  const wrapper = mountFlow(props)
  await no_(wrapper)
  return wrapper
}
const no_ = (wrapper) => wrapper.find('button[aria-label="不是"]').trigger('click')

afterEach(() => {
  vi.useRealTimers()
  vi.unstubAllGlobals()
})

describe('叫法步驟：先選用說的或用打的', () => {
  it('按 ✖ 後出現兩個一樣大的選項（圖示加文字）與略過；用打的才出現輸入框', async () => {
    const wrapper = await goSpeak()
    const speak = byId(wrapper, 'method-speak')
    const type_ = byId(wrapper, 'method-type')
    expect(speak.text()).toContain('用說的')
    expect(type_.text()).toContain('用打的')
    expect(speak.find('svg').exists() && type_.find('svg').exists()).toBe(true)
    expect(speak.classes()).toEqual(type_.classes().filter((c) => c !== 'mt-3'))
    expect(wrapper.text()).toContain('略過')
    expect(wrapper.find('input[aria-label="叫法"]').exists()).toBe(false)
    await click_(wrapper, 'method-type')
    expect(wrapper.find('input[aria-label="叫法"]').exists()).toBe(true)
    await wrapper.find('button[aria-label="返回"]').trigger('click')
    expect(byId(wrapper, 'step-method').exists()).toBe(true)
  })

  it('魚名是「我不知道」時第一個畫面就是這兩個選項，沒有返回鍵', () => {
    const wrapper = mountFlow({ options: { ...options, name_options: [] } })
    expect(byId(wrapper, 'step-method').exists()).toBe(true)
    expect(wrapper.find('button[aria-label="返回"]').exists()).toBe(false)
  })

  it('選部落之前不顯示既有的錄音管理', () => {
    const mine = [{ tribe: 'yayo', name: 'dalakan', food_category: 'oyod', recording_url: 'https://s3.test/a.m4a' }]
    const wrapper = mountFlow({ mine, summary: responded })
    expect(byId(wrapper, 'my-recording').exists()).toBe(true) // 在結果裡自己的那一列
    wrapper.unmount()
    const fresh = mountFlow({ summary: responded })
    expect(byId(fresh, 'my-recording').exists()).toBe(false)
  })
})

describe('錄音流程', () => {
  beforeEach(() => stubRecording())

  it('第一次錄音前先說明會用麥克風；開始後顯示已錄秒數與最長 30 秒；錄好了進入試聽', async () => {
    const wrapper = await goSpeak()
    await click_(wrapper, 'method-speak')
    expect(byId(wrapper, 'mic-notice').text()).toContain('麥克風')
    expect(wrapper.text()).toContain('最長 30 秒')
    await click_(wrapper, 'record-start')
    await flushPromises()
    expect(byId(wrapper, 'recording-time').text()).toBe('0:00')
    expect(wrapper.text()).toContain('還剩 30 秒')
    await click_(wrapper, 'record-stop')
    await flushPromises()
    expect(byId(wrapper, 'step-review').exists()).toBe(true)
    expect(byId(wrapper, 'preview-play').attributes('data-variant')).toBe('bar')
  })

  it('滿 30 秒自動停止並提示', async () => {
    vi.useFakeTimers()
    const wrapper = await goSpeak()
    await click_(wrapper, 'method-speak')
    await click_(wrapper, 'record-start')
    await flushPromises()
    vi.advanceTimersByTime(30000)
    await flushPromises()
    expect(byId(wrapper, 'step-review').exists()).toBe(true)
    expect(byId(wrapper, 'auto-stopped').text()).toContain('30 秒')
  })

  it('試聽可以播放；重錄回到錄音畫面；返回不送出任何東西', async () => {
    const wrapper = await goSpeak()
    await recordOnce(wrapper)
    const preview = byId(wrapper, 'preview-play')
    expect(preview.attributes('data-url')).toBe('blob:audio')
    expect(preview.attributes('data-variant')).toBe('bar')
    expect(preview.attributes('data-known-seconds')).toBe('1') // 假的計時沒有走秒（不足一秒），仍記 1 秒傳入
    await click_(wrapper, 're-record')
    expect(byId(wrapper, 'step-record').exists()).toBe(true)
    await click_(wrapper, 'record-start')
    await flushPromises()
    await click_(wrapper, 'record-stop')
    await flushPromises()
    await wrapper.find('button[aria-label="返回"]').trigger('click')
    expect(byId(wrapper, 'step-method').exists()).toBe(true)
    expect(post).not.toHaveBeenCalled()
    expect(fetch).not.toHaveBeenCalled()
  })

  it('OK 先取得上傳網址並直傳，再選部落；選部落才送出帶暫存檔名的回答，之後的分類不再帶錄音', async () => {
    fetch.mockResolvedValueOnce(SIGN).mockResolvedValueOnce({ ok: true })
    const wrapper = await goSpeak()
    await recordOnce(wrapper)
    await click_(wrapper, 'review-ok')
    await flushPromises()
    expect(fetch.mock.calls[0][0]).toBe('/fish/5/responses/recording-url')
    expect(fetch.mock.calls[0][1].method).toBe('POST')
    expect(JSON.parse(fetch.mock.calls[0][1].body)).toEqual({ ext: 'm4a' })
    expect(fetch.mock.calls[1][0]).toBe('https://s3.test/put')
    expect(fetch.mock.calls[1][1].method).toBe('PUT')
    expect(byId(wrapper, 'step-tribe').exists()).toBe(true)
    expect(post).not.toHaveBeenCalled()
    await click(wrapper, 'yayo')
    expect(post).toHaveBeenCalledWith('/fish/5/responses', { tribe: 'yayo', name: '', food_category: '', recording: 'pending-1.m4a', recording_seconds: 1 }, expect.anything())
    await click(wrapper, 'oyod')
    expect(post).toHaveBeenLastCalledWith('/fish/5/responses', { tribe: 'yayo', name: '', food_category: 'oyod' }, expect.anything())
  })

  it('只錄音：選好部落後可以略過分類，不會提示至少填一項', async () => {
    fetch.mockResolvedValueOnce(SIGN).mockResolvedValueOnce({ ok: true })
    const wrapper = await goSpeak()
    await recordOnce(wrapper)
    await click_(wrapper, 'review-ok')
    await flushPromises()
    await click(wrapper, 'yayo')
    await click(wrapper, '略過')
    expect(byId(wrapper, 'step-others').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('至少要填一項')
  })

  it('「用打的補上」：錄音與文字一起送出', async () => {
    fetch.mockResolvedValueOnce(SIGN).mockResolvedValueOnce({ ok: true })
    const wrapper = await goSpeak()
    await recordOnce(wrapper)
    await click_(wrapper, 'add-typed')
    await type(wrapper, 'dalakan')
    await click(wrapper, 'OK')
    await flushPromises()
    await click(wrapper, 'yayo')
    expect(post).toHaveBeenCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'dalakan', food_category: '', recording: 'pending-1.m4a', recording_seconds: 1 }, expect.anything())
  })

  it('上傳失敗：留在試聽畫面顯示明確文字，可再按 OK，也可改用打的', async () => {
    fetch.mockResolvedValueOnce({ ok: false, json: async () => ({}) })
    const wrapper = await goSpeak()
    await recordOnce(wrapper)
    await click_(wrapper, 'review-ok')
    await flushPromises()
    expect(byId(wrapper, 'upload-error').text()).toContain('錄音上傳失敗')
    expect(byId(wrapper, 'step-review').exists()).toBe(true)
    await click_(wrapper, 'switch-to-typing')
    expect(byId(wrapper, 'step-name').exists()).toBe(true)
    expect(post).not.toHaveBeenCalled()
  })

  it('直傳 S3 失敗也一樣不卡死', async () => {
    fetch.mockResolvedValueOnce(SIGN).mockResolvedValueOnce({ ok: false })
    const wrapper = await goSpeak()
    await recordOnce(wrapper)
    await click_(wrapper, 'review-ok')
    await flushPromises()
    expect(byId(wrapper, 'upload-error').exists()).toBe(true)
    expect(byId(wrapper, 'review-ok').attributes('disabled')).toBeUndefined()
  })

  it('送出時伺服器說錄音有問題：顯示訊息與重錄，重錄回到錄音畫面', async () => {
    fetch.mockResolvedValueOnce(SIGN).mockResolvedValueOnce({ ok: true })
    post.mockReturnValueOnce({ errors: { recording: '錄音檔不存在或太大，請重錄' } })
    const wrapper = await goSpeak()
    await recordOnce(wrapper)
    await click_(wrapper, 'review-ok')
    await flushPromises()
    await click(wrapper, 'yayo')
    expect(byId(wrapper, 'recording-error').text()).toContain('請重錄')
    await click_(wrapper, 're-record-failed')
    expect(byId(wrapper, 'step-record').exists()).toBe(true)
  })
})

describe('錄音失敗時有退路', () => {
  it.each([
    ['沒有麥克風權限', { mic: 'denied' }, '無法取得麥克風權限'],
    ['瀏覽器不支援錄音', { mic: 'unsupported' }, '瀏覽器不支援錄音'],
    ['瀏覽器不支援格式', { mimeSupported: false }, '不支援錄音格式'],
  ])('%s：顯示明確文字，「改用打的」進入打字畫面', async (_name, setup, message) => {
    stubRecording(setup)
    const wrapper = await goSpeak()
    await click_(wrapper, 'method-speak')
    await click_(wrapper, 'record-start')
    await flushPromises()
    expect(byId(wrapper, 'record-error').text()).toContain(message)
    await click_(wrapper, 'switch-to-typing')
    expect(byId(wrapper, 'step-name').exists()).toBe(true)
    expect(wrapper.find('input[aria-label="叫法"]').exists()).toBe(true)
  })
})

describe('說法卡的錄音', () => {
  const withRecordings = [
    none[0],
    {
      tribe: 'yayo', has_responses: true, has_variants: false,
      names: [{ name: 'dalakan', count: 2, recording_url: 'https://s3.test/early.m4a' }, { name: 'rakan', count: 1 }],
      recordings_only: [{ recording_url: 'https://s3.test/o1.m4a' }, { recording_url: 'https://s3.test/o2.m4a' }],
      categories: [],
    },
    { tribe: 'iratay', has_responses: true, has_variants: false, names: [], recordings_only: [{ recording_url: 'https://s3.test/o3.m4a' }], categories: [] },
  ]
  const mineRows = [{ tribe: 'yayo', name: 'dalakan', food_category: 'oyod', recording_url: 'https://s3.test/mine.m4a' }]
  const viewOthers = async (wrapper) => { await click(wrapper, '看大家怎麼說'); return wrapper }

  it('有其他人錄音的寫法，那一列右側有播放圖示、沒錄音的沒有；「有人只用說的」條列每一段', async () => {
    const wrapper = await viewOthers(mountFlow({ summary: withRecordings, mine: [] }))
    const yayo = wrapper.findAll('[data-testid="response-tribe"]')[0]
    const rows = yayo.findAll('[data-testid="name-row"]')
    expect(rows.map((row) => row.text())).toEqual(['dalakan', 'rakan'])
    expect(rows[0].find('[data-testid="name-recording"]').attributes('data-url')).toBe('https://s3.test/early.m4a')
    expect(rows[1].find('[data-testid="name-recording"]').exists()).toBe(false)
    expect(yayo.findAll('[data-testid="clip-row"]').map((row) => row.find('[data-url]').attributes('data-url'))).toEqual(['https://s3.test/o1.m4a', 'https://s3.test/o2.m4a'])
    expect(yayo.text()).toContain('有人只用說的')
  })

  it('只有其他人錄音的部落：列出「有人只用說的」，不顯示「目前沒有人提供叫法」；沒有任何叫法與錄音才顯示', async () => {
    const wrapper = await viewOthers(mountFlow({ summary: withRecordings, mine: [] }))
    const iratay = wrapper.findAll('[data-testid="response-tribe"]')[1]
    expect(iratay.findAll('[data-testid="clip-row"]')).toHaveLength(1)
    expect(iratay.find('[data-testid="no-names"]').exists()).toBe(false)
    const empty = [none[0], { tribe: 'yayo', has_responses: true, has_variants: false, names: [], recordings_only: [], categories: [{ value: 'oyod', count: 1 }] }]
    const plain = await viewOthers(mountFlow({ summary: empty, mine: [] }))
    expect(plain.find('[data-testid="no-names"]').text()).toBe('目前沒有人提供叫法')
    expect(plain.find('[data-testid="tribe-names"]').text()).not.toContain('—')
  })

  it('自己的錄音：「我的回答」區平常只有一顆播放鈕與一個「修改我的錄音」，沒有並排的重錄、刪除', () => {
    const wrapper = mountFlow({ summary: withRecordings, mine: mineRows })
    const answer = byId(wrapper, 'my-answer')
    expect(answer.text()).toContain('我的回答')
    expect(answer.find('[data-testid="my-answer-text"]').text()).toContain('dalakan')
    expect(answer.findAll('[data-testid="my-play"]')).toHaveLength(1)
    expect(byId(wrapper, 'my-edit').text()).toContain('修改我的錄音')
    expect(byId(wrapper, 'my-edit').find('svg').exists()).toBe(true) // 鉛筆圖示
    expect(byId(wrapper, 'my-edit-panel').exists()).toBe(false)
    expect(byId(wrapper, 'my-re-record').exists()).toBe(false)
    expect(byId(wrapper, 'my-delete').exists()).toBe(false)
  })

  it('自己的錄音只出現在「我的回答」：大家的說法裡沒有重複的播放鈕', () => {
    const summaryOfOthers = [none[0], { tribe: 'yayo', has_responses: true, has_variants: false, names: [{ name: 'dalakan', count: 1 }], recordings_only: [], categories: [] }]
    const wrapper = mountFlow({ summary: summaryOfOthers, mine: mineRows })
    expect(wrapper.findAll('[data-testid="name-recording"]')).toHaveLength(0)
    expect(wrapper.findAll('[data-testid="only-recording"]')).toHaveLength(0)
    expect(byId(wrapper, 'my-play').attributes('data-url')).toBe('https://s3.test/mine.m4a')
  })

  it('按「修改我的錄音」展開重錄、刪除錄音、收起；再按或按收起就收回', async () => {
    const wrapper = mountFlow({ summary: withRecordings, mine: mineRows })
    await click_(wrapper, 'my-edit')
    expect(byId(wrapper, 'my-edit').attributes('aria-expanded')).toBe('true')
    expect(byId(wrapper, 'my-re-record').text()).toBe('重錄')
    expect(byId(wrapper, 'my-delete').text()).toBe('刪除錄音')
    await click_(wrapper, 'my-collapse')
    expect(byId(wrapper, 'my-edit-panel').exists()).toBe(false)
    await click_(wrapper, 'my-edit')
    await click_(wrapper, 'my-edit')
    expect(byId(wrapper, 'my-edit-panel').exists()).toBe(false)
  })

  it('沒有自己的錄音：「我的回答」只列叫法與分類，不顯示錄音列與修改按鈕；沒打字寫（沒有打字）', () => {
    const rowsWithoutRecording = [{ tribe: 'yayo', name: null, food_category: 'oyod', recording_url: null, recording_seconds: null }]
    const wrapper = mountFlow({ summary: withRecordings, mine: rowsWithoutRecording })
    expect(byId(wrapper, 'my-answer-text').text()).toContain('（沒有打字）')
    expect(byId(wrapper, 'my-answer-text').text()).toContain('oyod')
    expect(byId(wrapper, 'my-recording').exists()).toBe(false)
    expect(byId(wrapper, 'my-edit').exists()).toBe(false)
  })

  it('沒回答過的部落沒有「我的回答」區', async () => {
    const wrapper = await viewOthers(mountFlow({ summary: withRecordings, mine: [] }))
    expect(byId(wrapper, 'my-answer').exists()).toBe(false)
  })

  it('面板一次只開一個部落', async () => {
    const mine = [...mineRows, { tribe: 'iratay', name: null, food_category: 'rahet', recording_url: 'https://s3.test/mine2.m4a' }]
    const wrapper = mountFlow({ summary: withRecordings, mine })
    const edits = () => wrapper.findAll('[data-testid="my-edit"]')
    await edits()[0].trigger('click')
    await edits()[1].trigger('click')
    expect(wrapper.findAll('[data-testid="my-edit-panel"]')).toHaveLength(1)
  })



  it('刪除要二次確認；還有叫法或分類時說明「叫法與分類會保留」，確認後只刪該部落', async () => {
    const mine = [...mineRows, { tribe: 'iratay', name: null, food_category: 'rahet', recording_url: 'https://s3.test/mine2.m4a' }]
    const wrapper = mountFlow({ summary: withRecordings, mine })
    await wrapper.findAll('[data-testid="my-edit"]')[0].trigger('click')
    await click_(wrapper, 'my-delete')
    expect(del).not.toHaveBeenCalled()
    expect(byId(wrapper, 'delete-message').text()).toBe('確定刪除這段錄音？叫法與分類會保留。')
    expect(byId(wrapper, 'delete-confirm').text()).toBe('刪除')
    await click_(wrapper, 'delete-cancel')
    expect(del).not.toHaveBeenCalled()
    await click_(wrapper, 'my-delete')
    await click_(wrapper, 'delete-confirm')
    expect(del).toHaveBeenCalledTimes(1)
    expect(del.mock.calls[0][0]).toBe('/fish/5/responses/recording')
    expect(del.mock.calls[0][1]).toEqual({ tribe: 'yayo' })
    expect(byId(wrapper, 'my-edit-panel').exists()).toBe(false) // 刪除成功後收起面板
  })

  it('只有分類沒有叫法時刪除錄音，同樣說明叫法與分類會保留', async () => {
    const wrapper = mountFlow({ summary: withRecordings, mine: [{ tribe: 'iratay', name: null, food_category: 'rahet', recording_url: 'https://s3.test/mine2.m4a' }] })
    await click_(wrapper, 'my-edit')
    await click_(wrapper, 'my-delete')
    expect(byId(wrapper, 'delete-message').text()).toContain('叫法與分類會保留')
  })

  it('錄音是唯一內容時，確認文字明講整筆說法也會刪除，確認鈕寫「刪除說法」', async () => {
    const wrapper = mountFlow({ summary: withRecordings, mine: [{ tribe: 'yayo', name: null, food_category: null, recording_url: 'https://s3.test/only.m4a' }] })
    await click(wrapper, '看大家怎麼說') // 只有錄音、還沒分類：先是「繼續」提示
    await click_(wrapper, 'my-edit')
    await click_(wrapper, 'my-delete')
    expect(byId(wrapper, 'delete-message').text()).toBe('這個部落你只有這段錄音，刪除後這筆說法也會一起刪除。確定嗎？')
    expect(byId(wrapper, 'delete-confirm').text()).toBe('刪除說法')
    await click_(wrapper, 'delete-confirm')
    expect(del).toHaveBeenCalledWith('/fish/5/responses/recording', { tribe: 'yayo' }, expect.anything())
  })

  it('刪除失敗顯示訊息並保留確認列，可以再試', async () => {
    del.mockReturnValueOnce({ errors: { recording: '刪除失敗，請重試' } })
    const wrapper = mountFlow({ summary: withRecordings, mine: mineRows })
    await click_(wrapper, 'my-edit')
    await click_(wrapper, 'my-delete')
    await click_(wrapper, 'delete-confirm')
    expect(byId(wrapper, 'delete-error').text()).toContain('刪除失敗')
    await click_(wrapper, 'delete-confirm')
    expect(del).toHaveBeenCalledTimes(2)
    expect(byId(wrapper, 'delete-error').exists()).toBe(false)
  })

  it('重錄：部落已固定，OK 後直接送出該部落那一筆並沿用原本的叫法與分類，不再選部落', async () => {
    stubRecording()
    fetch.mockResolvedValueOnce(SIGN).mockResolvedValueOnce({ ok: true })
    const mine = [{ tribe: 'yayo', name: 'dalakan', food_category: 'oyod', recording_url: 'https://s3.test/mine.m4a' }]
    const wrapper = mountFlow({ summary: withRecordings, mine })
    await click_(wrapper, 'my-edit')
    await click_(wrapper, 'my-re-record')
    expect(byId(wrapper, 'step-record').exists()).toBe(true)
    await click_(wrapper, 'record-start')
    await flushPromises()
    await click_(wrapper, 'record-stop')
    await flushPromises()
    expect(byId(wrapper, 'add-typed').exists()).toBe(false)
    await click_(wrapper, 'review-ok')
    await flushPromises()
    expect(post).toHaveBeenCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'dalakan', food_category: 'oyod', recording: 'pending-1.m4a', recording_seconds: 1 }, expect.anything())
    expect(byId(wrapper, 'step-others').exists()).toBe(true)
  })

  it('重錄中途返回：保留原本的錄音，不送出', async () => {
    stubRecording()
    const wrapper = mountFlow({ summary: withRecordings, mine: mineRows })
    await click_(wrapper, 'my-edit')
    await click_(wrapper, 'my-re-record')
    await wrapper.find('button[aria-label="返回"]').trigger('click')
    expect(byId(wrapper, 'step-others').exists()).toBe(true)
    expect(byId(wrapper, 'my-recording').exists()).toBe(true)
    expect(post).not.toHaveBeenCalled()
    expect(del).not.toHaveBeenCalled()
  })

  it('做到一半（只錄音、還沒分類）進來會提示繼續，按下去問分類且可略過', async () => {
    const mine = [{ tribe: 'yayo', name: null, food_category: null, recording_url: 'https://s3.test/mine.m4a' }]
    const wrapper = mountFlow({ summary: withRecordings, mine })
    expect(byId(wrapper, 'step-resume').exists()).toBe(true)
    await click(wrapper, '繼續 ›')
    await click(wrapper, '略過')
    expect(byId(wrapper, 'step-others').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('至少要填一項')
  })
})

describe('取消、失敗與退路（審查意見）', () => {
  const rows = [
    { tribe: 'yayo', name: 'dalakan', food_category: 'oyod', recording_url: 'https://s3.test/a.m4a' },
    { tribe: 'iratay', name: 'mamalan', food_category: 'rahet', recording_url: 'https://s3.test/b.m4a' },
  ]
  const summaryRows = [
    none[0],
    { tribe: 'yayo', has_responses: true, names: [{ name: 'dalakan', count: 1 }], recordings_only: [], has_variants: false, categories: [] },
    { tribe: 'iratay', has_responses: true, names: [{ name: 'mamalan', count: 1 }], recordings_only: [], has_variants: false, categories: [] },
  ]
  const signOf = (filename) => ({ ok: true, json: async () => ({ url: `https://s3.test/put/${filename}`, filename }) })
  const deferred = () => {
    let release
    const promise = new Promise((resolve) => { release = resolve })
    return { promise, release }
  }
  /** 依序回應取得上傳網址的請求；直傳（PUT）一律成功 */
  const routeFetch = (signs) => {
    fetch.mockImplementation((url) => (String(url).endsWith('/recording-url') ? Promise.resolve(signs.shift()) : Promise.resolve({ ok: true })))
  }

  beforeEach(() => stubRecording())

  it('上傳中返回：舊的上傳完成後不會跳到選部落，也不會把錄音帶進之後的回答', async () => {
    const slow = deferred()
    routeFetch([slow.promise])
    const wrapper = await goSpeak()
    await recordOnce(wrapper)
    await click_(wrapper, 'review-ok')
    await wrapper.find('button[aria-label="返回"]').trigger('click')
    slow.release(signOf('pending-old.m4a'))
    await flushPromises()
    expect(byId(wrapper, 'step-method').exists()).toBe(true)
    await click_(wrapper, 'method-type')
    await type(wrapper, 'dalakan')
    await click(wrapper, 'OK')
    await flushPromises()
    await click(wrapper, 'yayo')
    expect(post).toHaveBeenCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'dalakan', food_category: '' }, expect.anything())
  })

  it('重錄上傳中返回、改重錄另一個部落：舊錄音不會送到新部落', async () => {
    const slow = deferred()
    routeFetch([slow.promise, signOf('pending-new.m4a')])
    const wrapper = mountFlow({ summary: summaryRows, mine: rows })
    const reRecord = async (index) => {
      await wrapper.findAll('[data-testid="my-edit"]')[index].trigger('click')
      await wrapper.find('[data-testid="my-re-record"]').trigger('click')
    }
    await reRecord(0) // yayo
    await click_(wrapper, 'record-start')
    await flushPromises()
    await click_(wrapper, 'record-stop')
    await flushPromises()
    await click_(wrapper, 'review-ok') // 上傳中
    await wrapper.find('button[aria-label="返回"]').trigger('click')
    await reRecord(1) // iratay
    await click_(wrapper, 'record-start')
    await flushPromises()
    await click_(wrapper, 'record-stop')
    await flushPromises()
    await click_(wrapper, 'review-ok')
    await flushPromises()
    slow.release(signOf('pending-old.m4a')) // 第一個部落的舊上傳這時才回來
    await flushPromises()
    expect(post).toHaveBeenCalledTimes(1)
    expect(post).toHaveBeenCalledWith('/fish/5/responses', { tribe: 'iratay', name: 'mamalan', food_category: 'rahet', recording: 'pending-new.m4a', recording_seconds: 1 }, expect.anything())
  })

  it('錄音中返回，錄音之後才結束：已取消的錄音不會被上傳', async () => {
    stubRecording({ lateStop: true })
    const wrapper = await goSpeak()
    await click_(wrapper, 'method-speak')
    await click_(wrapper, 'record-start')
    await flushPromises()
    await wrapper.find('button[aria-label="返回"]').trigger('click')
    await flushPromises()
    expect(byId(wrapper, 'step-method').exists()).toBe(true)
    await click_(wrapper, 'method-type')
    await type(wrapper, 'dalakan')
    await click(wrapper, 'OK')
    await flushPromises()
    await click(wrapper, 'yayo')
    expect(fetch).not.toHaveBeenCalled()
    expect(post).toHaveBeenCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'dalakan', food_category: '' }, expect.anything())
  })

  it('「用打的補上」後上傳失敗：可以只送文字，已輸入的叫法照常送出，不再重傳錄音', async () => {
    fetch.mockResolvedValueOnce({ ok: false, json: async () => ({}) })
    const wrapper = await goSpeak()
    await recordOnce(wrapper)
    await click_(wrapper, 'add-typed')
    await type(wrapper, 'dalakan')
    await click(wrapper, 'OK')
    await flushPromises()
    expect(byId(wrapper, 'upload-error').text()).toContain('錄音上傳失敗')
    expect(wrapper.find('input[aria-label="叫法"]').element.value).toBe('dalakan')
    await click_(wrapper, 'send-text-only')
    expect(byId(wrapper, 'step-tribe').exists()).toBe(true)
    await click(wrapper, 'yayo')
    expect(post).toHaveBeenCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'dalakan', food_category: '' }, expect.anything())
    expect(fetch).toHaveBeenCalledTimes(1)
  })

  it('重錄送出被伺服器拒絕：試聽頁顯示訊息，再按 OK 會重新上傳新的暫存檔，也可以重錄', async () => {
    routeFetch([signOf('pending-1.m4a'), signOf('pending-2.m4a')])
    post.mockReturnValueOnce({ errors: { recording: '錄音儲存失敗，請重試' } })
    const wrapper = mountFlow({ summary: summaryRows, mine: rows })
    await wrapper.find('[data-testid="my-edit"]').trigger('click')
    await wrapper.find('[data-testid="my-re-record"]').trigger('click')
    await click_(wrapper, 'record-start')
    await flushPromises()
    await click_(wrapper, 'record-stop')
    await flushPromises()
    await click_(wrapper, 'review-ok')
    await flushPromises()
    expect(byId(wrapper, 'step-review').exists()).toBe(true)
    expect(byId(wrapper, 'recording-error').text()).toContain('錄音儲存失敗')
    await click_(wrapper, 'review-ok')
    await flushPromises()
    expect(post).toHaveBeenCalledTimes(2)
    expect(post.mock.calls[0][1].recording).toBe('pending-1.m4a')
    expect(post.mock.calls[1][1].recording).toBe('pending-2.m4a')
    expect(byId(wrapper, 'step-others').exists()).toBe(true)
  })

  it('重錄送出失敗後按重錄：回到錄音畫面並清掉錯誤', async () => {
    routeFetch([signOf('pending-1.m4a')])
    post.mockReturnValueOnce({ errors: { recording: '錄音檔不存在或太大，請重錄' } })
    const wrapper = mountFlow({ summary: summaryRows, mine: rows })
    await wrapper.find('[data-testid="my-edit"]').trigger('click')
    await wrapper.find('[data-testid="my-re-record"]').trigger('click')
    await click_(wrapper, 'record-start')
    await flushPromises()
    await click_(wrapper, 'record-stop')
    await flushPromises()
    await click_(wrapper, 'review-ok')
    await flushPromises()
    await click_(wrapper, 're-record')
    expect(byId(wrapper, 'step-record').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('請重錄')
  })
})

describe('錄音秒數（規格修訂 4）', () => {
  beforeEach(() => stubRecording())

  it('最長秒數取自後端傳來的設定，不是寫死 30', async () => {
    const wrapper = await goSpeak({ options: { ...options, recording_max_seconds: 10 } })
    await click_(wrapper, 'method-speak')
    expect(wrapper.text()).toContain('最長 10 秒')
    await click_(wrapper, 'record-start')
    await flushPromises()
    expect(wrapper.text()).toContain('還剩 10 秒')
  })

  it('送出時帶上錄音時記下的秒數：錄 7 秒就送 7', async () => {
    vi.useFakeTimers()
    fetch.mockResolvedValueOnce(SIGN).mockResolvedValueOnce({ ok: true })
    const wrapper = await goSpeak()
    await click_(wrapper, 'method-speak')
    await click_(wrapper, 'record-start')
    await flushPromises()
    vi.advanceTimersByTime(7000)
    await click_(wrapper, 'record-stop')
    await flushPromises()
    expect(byId(wrapper, 'preview-play').attributes('data-known-seconds')).toBe('7')
    await click_(wrapper, 'review-ok')
    await flushPromises()
    await click(wrapper, 'yayo')
    expect(post).toHaveBeenCalledWith('/fish/5/responses', { tribe: 'yayo', name: '', food_category: '', recording: 'pending-1.m4a', recording_seconds: 7 }, expect.anything())
    await click(wrapper, 'oyod')
    expect(post.mock.calls.at(-1)[1]).not.toHaveProperty('recording_seconds') // 之後的分類不再帶錄音與秒數
  })

  it('說法卡、只有錄音、自己的面板的播放鈕都拿到資料裡的秒數；舊資料沒有秒數就是空', async () => {
    const summary = [
      none[0],
      {
        tribe: 'yayo', has_responses: true, has_variants: false,
        names: [{ name: 'dalakan', count: 1, recording_url: 'https://s3.test/a.m4a', recording_seconds: 12 }, { name: 'rakan', count: 1, recording_url: 'https://s3.test/b.m4a', recording_seconds: null }],
        recordings_only: [{ recording_url: 'https://s3.test/o.m4a', recording_seconds: 4 }],
        categories: [],
      },
    ]
    const mine = [{ tribe: 'yayo', name: 'dalakan', food_category: 'oyod', recording_url: 'https://s3.test/a.m4a', recording_seconds: 12 }]
    const wrapper = mountFlow({ summary, mine })
    const seconds = (selector) => wrapper.findAll(selector).map((n) => n.attributes('data-known-seconds'))
    expect(seconds('[data-testid="name-recording"]')).toEqual(['12', undefined])
    expect(seconds('[data-testid="clip-row"] [data-url]')).toEqual(['4'])
    expect(byId(wrapper, 'my-play').attributes('data-known-seconds')).toBe('12')
  })

  it('重錄送出時也帶新錄音的秒數', async () => {
    vi.useFakeTimers()
    fetch.mockResolvedValueOnce(SIGN).mockResolvedValueOnce({ ok: true })
    const mine = [{ tribe: 'yayo', name: 'dalakan', food_category: 'oyod', recording_url: 'https://s3.test/a.m4a', recording_seconds: 12 }]
    const summary = [none[0], { tribe: 'yayo', has_responses: true, has_variants: false, names: [{ name: 'dalakan', count: 1 }], recordings_only: [], categories: [] }]
    const wrapper = mountFlow({ summary, mine })
    await click_(wrapper, 'my-edit')
    await click_(wrapper, 'my-re-record')
    await click_(wrapper, 'record-start')
    await flushPromises()
    vi.advanceTimersByTime(5000)
    await click_(wrapper, 'record-stop')
    await flushPromises()
    await click_(wrapper, 'review-ok')
    await flushPromises()
    expect(post).toHaveBeenCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'dalakan', food_category: 'oyod', recording: 'pending-1.m4a', recording_seconds: 5 }, expect.anything())
  })
})

describe('有人只用說的：先顯示 3 段再展開（規格修訂 5）', () => {
  const clips = (count) => Array.from({ length: count }, (_, i) => ({ recording_url: `https://s3.test/c${i + 1}.m4a`, recording_seconds: i + 3 }))
  const summaryOf = (count) => [none[0], { tribe: 'yayo', has_responses: true, has_variants: false, names: [], recordings_only: clips(count), categories: [] }]
  const view = async (count) => {
    const wrapper = mountFlow({ summary: summaryOf(count) })
    await click(wrapper, '看大家怎麼說')
    return wrapper
  }

  it('5 段：卡片上先看到 3 段，按「還有 2 段，全部展開」才看到全部，再按收起', async () => {
    const wrapper = await view(5)
    expect(wrapper.findAll('[data-testid="clip-row"]')).toHaveLength(3)
    await click_(wrapper, 'clips-more')
    expect(wrapper.findAll('[data-testid="clip-row"]')).toHaveLength(5)
    await click_(wrapper, 'clips-less')
    expect(wrapper.findAll('[data-testid="clip-row"]')).toHaveLength(3)
  })

  it('3 段以內：全部顯示，沒有收合按鈕', async () => {
    const wrapper = await view(3)
    expect(wrapper.findAll('[data-testid="clip-row"]')).toHaveLength(3)
    expect(wrapper.find('[data-testid="clips-more"]').exists()).toBe(false)
  })
})

describe('錄音時間提示：進度條與最後 10 秒警示（#165）', () => {
  beforeEach(() => stubRecording())
  const startRecording = async (props = {}) => {
    vi.useFakeTimers()
    const wrapper = await goSpeak(props)
    await click_(wrapper, 'method-speak')
    await click_(wrapper, 'record-start')
    await flushPromises()
    return wrapper
  }
  const tick = async (wrapper, seconds) => {
    vi.advanceTimersByTime(seconds * 1000)
    await flushPromises()
  }
  const bar = (wrapper) => byId(wrapper, 'record-progress')
  const width = (wrapper) => bar(wrapper).find('div').attributes('style')

  it('選項頁：「用說的」下面有小字「最長 30 秒」，數字取自後端設定；「用打的」沒有', async () => {
    const wrapper = await goSpeak()
    expect(byId(wrapper, 'method-speak').text()).toContain('最長 30 秒')
    expect(byId(wrapper, 'method-type').text()).not.toContain('最長')
    const short = await goSpeak({ options: { ...options, recording_max_seconds: 15 } })
    expect(byId(short, 'method-speak').text()).toContain('最長 15 秒')
  })

  it('進度條：開始是空的，錄到一半是 50%，並提供輔助技術可讀的進度', async () => {
    const wrapper = await startRecording()
    expect(bar(wrapper).attributes('role')).toBe('progressbar')
    expect(bar(wrapper).attributes('aria-valuemin')).toBe('0')
    expect(bar(wrapper).attributes('aria-valuemax')).toBe('30')
    expect(bar(wrapper).attributes('aria-valuenow')).toBe('0')
    expect(width(wrapper)).toContain('width: 0%')
    await tick(wrapper, 15)
    expect(bar(wrapper).attributes('aria-valuenow')).toBe('15')
    expect(width(wrapper)).toContain('width: 50%')
    expect(byId(wrapper, 'recording-time').text()).toBe('0:15')
    expect(wrapper.text()).toContain('還剩 15 秒')
  })

  it('剩 11 秒沒有警示；剛好剩 10 秒才出現「快到了，請說完」，進度條與時間變紅', async () => {
    const wrapper = await startRecording()
    await tick(wrapper, 19)
    expect(wrapper.text()).toContain('還剩 11 秒')
    expect(byId(wrapper, 'record-warning').exists()).toBe(false)
    expect(bar(wrapper).attributes('data-warning')).toBeUndefined()
    await tick(wrapper, 1)
    expect(wrapper.text()).toContain('還剩 10 秒')
    expect(byId(wrapper, 'record-warning').text()).toBe('快到了，請說完')
    expect(bar(wrapper).attributes('data-warning')).toBe('true')
    expect(bar(wrapper).find('div').classes()).toContain('bg-red-700')
    expect(byId(wrapper, 'recording-time').classes()).toContain('text-red-700')
  })

  it('警示區是朗讀用的 aria-live，閃動在「減少動態效果」時停用，也沒有聲音或震動', async () => {
    const wrapper = await startRecording()
    await tick(wrapper, 20)
    expect(byId(wrapper, 'record-warning-region').attributes('aria-live')).toBe('polite')
    expect(byId(wrapper, 'record-warning').classes()).toContain('motion-safe:animate-pulse')
    expect(byId(wrapper, 'record-warning').classes().join(' ')).not.toContain('animate-pulse motion-reduce')
    const vibrate = vi.fn()
    Object.defineProperty(navigator, 'vibrate', { configurable: true, value: vibrate })
    await tick(wrapper, 5)
    expect(vibrate).not.toHaveBeenCalled()
  })

  it('最長秒數不超過 10 秒：全程沒有警示（否則一開始就紅）', async () => {
    const wrapper = await startRecording({ options: { ...options, recording_max_seconds: 10 } })
    expect(byId(wrapper, 'record-warning').exists()).toBe(false)
    await tick(wrapper, 5)
    expect(byId(wrapper, 'record-warning').exists()).toBe(false)
    expect(bar(wrapper).attributes('data-warning')).toBeUndefined()
    expect(width(wrapper)).toContain('width: 50%')
  })

  it('滿 30 秒自動停止：進入試聽並提示，進度條與警示消失', async () => {
    const wrapper = await startRecording()
    await tick(wrapper, 30)
    expect(byId(wrapper, 'step-review').exists()).toBe(true)
    expect(byId(wrapper, 'auto-stopped').text()).toContain('30 秒')
    expect(byId(wrapper, 'record-progress').exists()).toBe(false)
    expect(byId(wrapper, 'record-warning').exists()).toBe(false)
  })

  it('重錄後再開始：進度條回到 0，警示消失', async () => {
    const wrapper = await startRecording()
    await tick(wrapper, 22)
    await click_(wrapper, 'record-stop')
    await flushPromises()
    await click_(wrapper, 're-record')
    await click_(wrapper, 'record-start')
    await flushPromises()
    expect(width(wrapper)).toContain('width: 0%')
    expect(byId(wrapper, 'record-warning').exists()).toBe(false)
  })

  it('沒有麥克風權限：沒有進度條與警示，維持錯誤畫面', async () => {
    stubRecording({ mic: 'denied' })
    const wrapper = await goSpeak()
    await click_(wrapper, 'method-speak')
    await click_(wrapper, 'record-start')
    await flushPromises()
    expect(byId(wrapper, 'record-error').exists()).toBe(true)
    expect(byId(wrapper, 'record-progress').exists()).toBe(false)
    expect(byId(wrapper, 'record-warning').exists()).toBe(false)
  })
})


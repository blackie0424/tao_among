import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const posted = []
let lastPostOptions
const routerHandlers = {}

vi.mock('@inertiajs/vue3', async () => {
  const { reactive } = await import('vue')
  return {
    Head: { render: () => null },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router: {
      on: (name, handler) => {
        routerHandlers[name] = handler
        return () => {}
      },
    },
    useForm: (init) => {
      const form = reactive({ ...init, errors: {}, processing: false })
      form.clearErrors = () => {
        form.errors = {}
      }
      form.post = (url, options) => {
        posted.push({ url, question: form.question, value: form.value })
        lastPostOptions = options
      }
      return form
    },
  }
})

import Fish from '@/Pages/Island/Fish.vue'

const responder = (name, tribe = 'yayo', isGuest = false) => ({ name, tribe, is_guest: isGuest })

const result = (overrides = {}) => ({
  total: 0,
  own_tribe_count: 0,
  majority: null,
  choices: [],
  ...overrides,
})

const question = (key, overrides = {}) => ({
  key,
  options: [],
  my_answer: null,
  result: result(),
  ...overrides,
})

const defaultQuestions = () => [
  question('name', {
    options: ['dalakan', 'arayo'],
    result: result({
      total: 1,
      choices: [
        { value: 'arayo', count: 1, responders: [responder('阿浪')] },
        { value: 'dalakan', count: 0, responders: [] },
      ],
    }),
  }),
  question('food_category', { options: ['oyod', 'rahet'] }),
  question('processing_method', { options: ['去魚鱗', '我不知道'] }),
]

const mountPage = (props = {}) =>
  mount(Fish, {
    attachTo: document.body,
    props: {
      character: { name: '阿海', tribe: 'yayo' },
      tribe: 'iratay',
      fish: { id: 5, name: 'dalakan', image_url: 'https://example.test/5.webp' },
      questions: defaultQuestions(),
      name_max_length: 60,
      next_fish_id: 6,
      ...props,
    },
  })

const optionLabels = (wrapper) =>
  wrapper.findAll('[data-testid="option"]').map((o) => o.text().replace(/[›✎]/g, '').trim())
const option = (wrapper, label) =>
  wrapper
    .findAll('[data-testid="option"]')
    .find((o) => o.text().replace(/[›✎]/g, '').trim() === label)
const choiceRows = (wrapper) => wrapper.findAll('[data-testid="choice"]')
const button = (wrapper, text) => wrapper.findAll('button').find((b) => b.text() === text)
const tab = (wrapper, label) =>
  wrapper.findAll('[role="tab"]').find((t) => t.text().replace('✓', '').trim() === label)
const visibleLimitMessage = (wrapper) => wrapper.find('[data-testid="rate-limit-message"]')
const showsResult = (wrapper) => wrapper.find('[data-testid="result"]').exists()

async function startAnswer(wrapper) {
  await button(wrapper, '我來回答').trigger('click')
}

async function chooseSupplement(wrapper) {
  await option(wrapper, '都不對，我來補充').trigger('click')
}

beforeEach(() => {
  vi.useFakeTimers()
  posted.length = 0
  lastPostOptions = undefined
  document.body.innerHTML = ''
})

afterEach(() => {
  vi.useRealTimers()
})

describe('魚島作答頁：照片', () => {
  it('照片完整顯示不裁切，魚名疊在照片上', () => {
    const wrapper = mountPage()

    const img = wrapper.find('[data-testid="fish-photo"] img')
    expect(img.classes()).toContain('object-contain')
    expect(img.classes()).not.toContain('object-cover')
    expect(wrapper.find('[data-testid="fish-photo"] h1').text()).toBe('dalakan')
  })

  it('沒有正式名字的魚顯示「還沒有名字」', () => {
    expect(mountPage({ fish: { id: 5, name: null, image_url: '' } }).text()).toContain('還沒有名字')
  })

  it('點「放大」全螢幕顯示整張照片，按「關閉」回來', async () => {
    const wrapper = mountPage()
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)

    await wrapper.find('[data-testid="photo-zoom"]').trigger('click')

    const dialog = wrapper.find('[role="dialog"]')
    expect(dialog.attributes('aria-modal')).toBe('true')
    expect(dialog.find('img').attributes('src')).toBe('https://example.test/5.webp')
    expect(dialog.find('img').classes()).toContain('object-contain')

    await button(wrapper, '關閉').trigger('click')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
  })

  it('全螢幕時按 Esc 也會關閉', async () => {
    const wrapper = mountPage()
    await wrapper.find('[data-testid="photo-zoom"]').trigger('click')

    await wrapper.find('[role="dialog"]').trigger('keydown', { key: 'Escape' })

    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
  })
})

describe('魚島作答頁：分頁與先看結果', () => {
  it('三個分頁「叫法／分類／處理」，一進來在「叫法」並先顯示結果與題目', () => {
    const wrapper = mountPage()

    expect(wrapper.findAll('[role="tab"]').map((t) => t.text())).toEqual(['叫法', '分類', '處理'])
    expect(tab(wrapper, '叫法').attributes('aria-selected')).toBe('true')
    expect(wrapper.find('h2').text()).toBe('iratay（漁人）怎麼叫這條魚？')
    expect(showsResult(wrapper)).toBe(true)
    expect(optionLabels(wrapper)).toEqual([])
  })

  it('可以直接切到任何一題，不必依序', async () => {
    const wrapper = mountPage()

    await tab(wrapper, '處理').trigger('click')

    expect(tab(wrapper, '處理').attributes('aria-selected')).toBe('true')
    expect(tab(wrapper, '叫法').attributes('aria-selected')).toBe('false')
    expect(wrapper.find('h2').text()).toBe('iratay（漁人）怎麼處理這條魚？')
    expect(showsResult(wrapper)).toBe(true)
  })

  it('答過的分頁標 ✓', () => {
    const questions = defaultQuestions()
    questions[1].my_answer = 'oyod'

    expect(
      mountPage({ questions })
        .findAll('[role="tab"]')
        .map((t) => t.text())
    ).toEqual(['叫法', '分類 ✓', '處理'])
  })

  it('沒答過的題目有「我來回答」，答過的有「改我的回答」；沒有「跳過」', async () => {
    const questions = defaultQuestions()
    questions[1].my_answer = 'oyod'
    const wrapper = mountPage({ questions })

    expect(button(wrapper, '我來回答')).toBeDefined()
    expect(wrapper.text()).not.toContain('跳過')
    await tab(wrapper, '分類').trigger('click')
    expect(button(wrapper, '改我的回答')).toBeDefined()
    expect(button(wrapper, '我來回答')).toBeUndefined()
  })

  it('「下一條魚 ›」與「回到 {部落}」一進來就在最下方', () => {
    const links = mountPage()
      .findAll('a')
      .map((a) => [a.text(), a.attributes('href')])

    expect(links).toContainEqual(['下一條魚 ›', '/island/tribes/iratay/fish/6'])
    expect(links).toContainEqual(['回到 iratay（漁人）', '/island/tribes/iratay'])
  })

  it('沒有下一條魚時不顯示「下一條魚 ›」', () => {
    const wrapper = mountPage({ next_fish_id: null })

    expect(wrapper.text()).not.toContain('下一條魚')
    expect(wrapper.text()).toContain('回到 iratay（漁人）')
  })
})

describe('魚島作答頁：回答', () => {
  it('按「我來回答」出現選項：叫法＝後端給的選項＋「都不對，我來補充」', async () => {
    const wrapper = mountPage()

    await startAnswer(wrapper)

    expect(optionLabels(wrapper)).toEqual(['dalakan', 'arayo', '都不對，我來補充'])
    expect(showsResult(wrapper)).toBe(false)
  })

  it('點一下選項就送出這一題，沒有另外的「送出」鈕', async () => {
    const wrapper = mountPage()
    await startAnswer(wrapper)
    expect(button(wrapper, '送出')).toBeUndefined()

    await option(wrapper, 'arayo').trigger('click')

    expect(posted).toEqual([
      { url: '/island/tribes/iratay/fish/5/answers', question: 'name', value: 'arayo' },
    ])
  })

  it('送出成功後回到這一題的結果', async () => {
    const wrapper = mountPage()
    await startAnswer(wrapper)
    await option(wrapper, 'arayo').trigger('click')

    lastPostOptions.onSuccess()
    await wrapper.vm.$nextTick()

    expect(showsResult(wrapper)).toBe(true)
    expect(optionLabels(wrapper)).toEqual([])
  })

  it('「先不答，看結果」回到結果，不送出', async () => {
    const wrapper = mountPage()
    await startAnswer(wrapper)

    await button(wrapper, '先不答，看結果').trigger('click')

    expect(showsResult(wrapper)).toBe(true)
    expect(posted).toEqual([])
  })

  it('答到一半換分頁：新的分頁先顯示結果', async () => {
    const wrapper = mountPage()
    await startAnswer(wrapper)

    await tab(wrapper, '分類').trigger('click')

    expect(showsResult(wrapper)).toBe(true)
  })

  it('「改我的回答」出現選項並標出原本選的', async () => {
    const questions = defaultQuestions()
    questions[0].my_answer = 'arayo'
    const wrapper = mountPage({ questions })
    expect(choiceRows(wrapper)[0].text()).toContain('你選的')

    await button(wrapper, '改我的回答').trigger('click')

    expect(option(wrapper, 'arayo').attributes('aria-pressed')).toBe('true')
    expect(option(wrapper, 'dalakan').attributes('aria-pressed')).toBe('false')
  })

  it('既有寫法剛好是「__supplement__」也能直接點、送出這個寫法，不會進入補充', async () => {
    const questions = defaultQuestions()
    questions[0].options = ['dalakan', '__supplement__']
    const wrapper = mountPage({ questions })
    await startAnswer(wrapper)

    await option(wrapper, '__supplement__').trigger('click')

    expect(wrapper.find('#island-supplement').exists()).toBe(false)
    expect(posted).toEqual([
      { url: '/island/tribes/iratay/fish/5/answers', question: 'name', value: '__supplement__' },
    ])
  })

  it('點「都不對，我來補充」出現輸入框，送出打的字', async () => {
    const wrapper = mountPage()
    await startAnswer(wrapper)

    await chooseSupplement(wrapper)
    expect(posted).toEqual([])
    await wrapper.find('#island-supplement').setValue('ararow')
    await wrapper.find('form').trigger('submit')

    expect(wrapper.find('#island-supplement').attributes('maxlength')).toBe('60')
    expect(posted).toEqual([
      { url: '/island/tribes/iratay/fish/5/answers', question: 'name', value: 'ararow' },
    ])
  })

  it('補充時按「取消」回到選項，不送出', async () => {
    const wrapper = mountPage()
    await startAnswer(wrapper)
    await chooseSupplement(wrapper)

    await button(wrapper, '取消').trigger('click')

    expect(optionLabels(wrapper)).toEqual(['dalakan', 'arayo', '都不對，我來補充'])
    expect(posted).toEqual([])
  })

  it('送出被拒時在補充欄位下顯示錯誤，欄位標成有錯，留在補充', async () => {
    const wrapper = mountPage()
    await startAnswer(wrapper)
    await chooseSupplement(wrapper)
    await wrapper.find('form').trigger('submit')
    wrapper.vm.form.errors = { value: '請填叫法，或按取消' }
    await wrapper.vm.$nextTick()

    expect(wrapper.find('#island-answer-error').text()).toBe('請填叫法，或按取消')
    expect(wrapper.find('#island-supplement').attributes('aria-invalid')).toBe('true')
    expect(showsResult(wrapper)).toBe(false)
  })

  it('送出途中選項停用', async () => {
    const wrapper = mountPage()
    await startAnswer(wrapper)
    await option(wrapper, 'arayo').trigger('click')
    wrapper.vm.form.processing = true
    await wrapper.vm.$nextTick()

    expect(option(wrapper, 'dalakan').attributes('disabled')).toBeDefined()
  })

  it('換分頁後才收到前一題的成功回應：現在的分頁不受影響', async () => {
    const wrapper = mountPage()
    await tab(wrapper, '分類').trigger('click')
    await startAnswer(wrapper)
    await option(wrapper, 'oyod').trigger('click')
    const categorySuccess = lastPostOptions.onSuccess
    await tab(wrapper, '處理').trigger('click')
    await startAnswer(wrapper)

    categorySuccess()
    await wrapper.vm.$nextTick()

    expect(tab(wrapper, '處理').attributes('aria-selected')).toBe('true')
    expect(optionLabels(wrapper)).toEqual(['去魚鱗', '我不知道'])
  })
})

describe('魚島作答頁：結果', () => {
  const showResult = async (resultOverrides) => {
    const questions = defaultQuestions()
    questions[1].result = result(resultOverrides)
    const wrapper = mountPage({ questions })
    await tab(wrapper, '分類').trigger('click')
    return wrapper
  }

  it('每個選項顯示人數（含 0 人），第一名標「多數」', async () => {
    const wrapper = await showResult({
      total: 3,
      majority: 'oyod',
      choices: [
        { value: 'oyod', count: 2, responders: [responder('甲'), responder('乙')] },
        { value: 'rahet', count: 1, responders: [responder('丙')] },
        { value: '不分類', count: 0, responders: [] },
      ],
    })

    const rows = choiceRows(wrapper)
    expect(rows.map((r) => r.find('[data-testid="choice-count"]').text())).toEqual([
      '2 人',
      '1 人',
      '0 人',
    ])
    expect(rows[0].text()).toContain('多數')
    expect(rows[1].text()).not.toContain('多數')
  })

  it('叫法的正式魚名沒人選時顯示 0 人', async () => {
    const wrapper = mountPage()

    const dalakan = choiceRows(wrapper).find((r) => r.text().includes('dalakan'))
    expect(dalakan.find('[data-testid="choice-count"]').text()).toBe('0 人')
  })

  it('沒有多數（同票）時不標「多數」，只列人數', async () => {
    const wrapper = await showResult({
      total: 4,
      majority: null,
      choices: [
        { value: 'oyod', count: 2, responders: [] },
        { value: 'rahet', count: 2, responders: [] },
      ],
    })

    expect(wrapper.find('[data-testid="result"]').text()).not.toContain('多數')
  })

  it('回答人數不到 3：顯示「人數還不夠」與目前人數', async () => {
    const wrapper = await showResult({
      total: 2,
      choices: [{ value: 'oyod', count: 2, responders: [] }],
    })

    expect(wrapper.find('[data-testid="result-status"]').text()).toBe('人數還不夠，目前 2 人回答')
  })

  it('0 人時顯示「還沒有人回答」', async () => {
    const wrapper = await showResult({ total: 0 })

    expect(wrapper.find('[data-testid="result-status"]').text()).toBe('還沒有人回答')
  })

  it('人數夠時顯示回答人數', async () => {
    const wrapper = await showResult({
      total: 5,
      majority: 'oyod',
      choices: [{ value: 'oyod', count: 5, responders: [] }],
    })

    expect(wrapper.find('[data-testid="result-status"]').text()).toBe('5 人回答')
  })

  it('顯示「其中本部落 N 人」，0 也顯示', async () => {
    expect((await showResult({ total: 2, own_tribe_count: 1 })).text()).toContain('其中本部落 1 人')
    expect((await showResult({ total: 2, own_tribe_count: 0 })).text()).toContain('其中本部落 0 人')
  })

  it('「看是誰答的」預設收合，點開後每個選項底下列出角色名＋部落，訪客標「訪客」', async () => {
    const wrapper = await showResult({
      total: 3,
      choices: [
        {
          value: 'oyod',
          count: 3,
          responders: [
            responder('阿海', 'yayo'),
            responder('訪客甲', 'imowrod', true),
            responder('阿浪', 'iratay'),
          ],
        },
      ],
    })
    expect(wrapper.find('[data-testid="responders"]').exists()).toBe(false)

    await button(wrapper, '看是誰答的').trigger('click')

    const names = wrapper
      .findAll('[data-testid="responder"]')
      .map((r) => r.text().replace(/\s+/g, ' '))
    expect(names).toEqual([
      '阿海 yayo（椰油）',
      '訪客甲 訪客 imowrod（紅頭）',
      '阿浪 iratay（漁人）',
    ])
    expect(button(wrapper, '收起').attributes('aria-expanded')).toBe('true')
  })

  it('沒有魚島角色的回答者只顯示「部落未填」；有角色但沒記部落的顯示角色名＋「部落未填」', async () => {
    const wrapper = await showResult({
      total: 2,
      choices: [
        { value: 'oyod', count: 2, responders: [responder(null, null), responder('後來的', null)] },
      ],
    })

    await button(wrapper, '看是誰答的').trigger('click')

    const names = wrapper
      .findAll('[data-testid="responder"]')
      .map((r) => r.text().replace(/\s+/g, ' '))
    expect(names).toEqual(['部落未填', '後來的 部落未填'])
  })
})

describe('魚島作答頁：被限流', () => {
  const invalidResponse = (status, headers = {}) => {
    const event = { detail: { response: { status, headers } }, preventDefault: vi.fn() }
    routerHandlers.invalid(event)
    return event
  }

  it('429 時顯示還要等幾秒並倒數，倒數期間選項停用、點了也不送出', async () => {
    const wrapper = mountPage()
    await startAnswer(wrapper)

    const event = invalidResponse(429, { 'retry-after': '45' })
    await wrapper.vm.$nextTick()

    expect(event.preventDefault).toHaveBeenCalled()
    expect(visibleLimitMessage(wrapper).text()).toBe('送出太多次了，請等 45 秒再試')
    expect(option(wrapper, 'arayo').attributes('disabled')).toBeDefined()
    expect(button(wrapper, '45 秒後可以再試').attributes('disabled')).toBeDefined()
    wrapper.vm.send('arayo')
    expect(posted).toEqual([])

    await vi.advanceTimersByTimeAsync(3000)
    expect(visibleLimitMessage(wrapper).text()).toBe('送出太多次了，請等 42 秒再試')
    expect(button(wrapper, '42 秒後可以再試')).toBeDefined()
  })

  it('倒數結束後「幾秒後可以再試」消失、選項恢復可點', async () => {
    const wrapper = mountPage()
    await startAnswer(wrapper)
    invalidResponse(429, { 'retry-after': '2' })
    await wrapper.vm.$nextTick()

    await vi.advanceTimersByTimeAsync(2000)

    expect(wrapper.text()).not.toContain('秒後可以再試')
    expect(option(wrapper, 'arayo').attributes('disabled')).toBeUndefined()
  })

  it('補充時被限流：送出鈕停用並顯示幾秒後可以再試', async () => {
    const wrapper = mountPage()
    await startAnswer(wrapper)
    await chooseSupplement(wrapper)

    invalidResponse(429, { 'retry-after': '45' })
    await wrapper.vm.$nextTick()

    expect(button(wrapper, '45 秒後可以再試').attributes('disabled')).toBeDefined()
  })

  it('沒有 Retry-After 時從 60 秒開始', async () => {
    const wrapper = mountPage()

    invalidResponse(429)
    await wrapper.vm.$nextTick()

    expect(visibleLimitMessage(wrapper).text()).toBe('送出太多次了，請等 60 秒再試')
  })
})

import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

// #232：Inertia 的 partial reload、送出換成假的，由測試決定何時回來
const inertia = vi.hoisted(() => ({ reload: vi.fn(), post: vi.fn(), formPosts: [], handlers: [] }))
vi.mock('@inertiajs/vue3', async () => {
  const { reactive } = await import('vue')
  return {
    router: {
      reload: inertia.reload,
      post: inertia.post,
      // 限流倒數（useRateLimitCountdown）聽 invalid 事件：記下來，由測試觸發
      on: (name, handler) => {
        inertia.handlers.push({ name, handler })
        return () => {}
      },
    },
    useForm: (init) => {
      const form = reactive({ ...init, errors: {}, processing: false })
      form.clearErrors = () => {
        form.errors = {}
      }
      // 送出後 processing 為 true，直到測試呼叫 onSuccess（同 Inertia 的 useForm）
      form.post = (url, options) => {
        form.processing = true
        const settle = {
          ...options,
          onSuccess: () => ((form.processing = false), options.onSuccess?.()),
        }
        inertia.formPosts.push({ url, question: form.question, value: form.value, options: settle })
      }
      return form
    },
    Head: { render: () => null },
    Link: {
      props: ['href'],
      template: '<a :href="href"><slot /></a>',
    },
  }
})

// 3D 繪圖模組換成假的：測試環境（jsdom）畫不出 3D，畫面由實機驗證
const scene = vi.hoisted(() => ({ createIslandScene: vi.fn(), dispose: vi.fn() }))
vi.mock('@/island3d/scene', () => ({ createIslandScene: scene.createIslandScene }))

import Home from '@/Pages/Island/Home.vue'

const SPOT_NAMES = [
  ['main-house', 'vahay', 'kaminan'],
  ['backrest-stone', 'pananadengan', 'akay'],
  ['drying-yard', 'zazawan', 'maran'],
  ['pavilion', 'tagakal', 'kaka'],
]
/** 一個部落的 12 個地點（3 戶 × 4） */
const tribeSpots = () =>
  [1, 2, 3].flatMap((household) =>
    SPOT_NAMES.map(([code, name, npc]) => ({ household, code, name, npc, greeting: `${npc} kong` }))
  )

// #216：手建的魚與每個部落 12 個地點列的魚（第 hex 戶 × 4 ＋ 第 slot 件）
const ISLAND_FISH = [
  {
    id: 1,
    name: '魚K',
    image_url: '/k.jpg',
    results: { food_category: {}, processing_method: {} },
  },
  {
    id: 2,
    name: '魚A',
    image_url: '/a.jpg',
    results: { food_category: { iratay: [{ value: 'oyod', count: 2 }] }, processing_method: {} },
  },
  {
    id: 14,
    name: null,
    image_url: '/b.jpg',
    results: { food_category: {}, processing_method: {} },
  },
]
const SPEECH_A_IRATAY = '這是 魚A。我們 iratay 多數人說它是 oyod；怎麼處理還沒有答案。'
const SPEECH_UNNAMED_IRATAY =
  '這是 這條還沒有名字的魚。我們 iratay 還沒有分類的答案；怎麼處理還沒有答案。'
const SPEECH_A_IVALINO = '這是 魚A。我們 ivalino 還沒有分類的答案；怎麼處理還沒有答案。'
// #232：家人說的話依部落、魚編號給（fish_speech）；#216 的對話改成問玩家自己的魚（手上＋圖鑑）
const NOWHERE_LINE = '這條魚，全島還沒有人說過它是哪一類、怎麼處理。'
const FISH_SPEECH = {
  iratay: {
    1: {
      speech: '這是 魚K。我們 iratay 還沒有分類的答案；怎麼處理還沒有答案。',
      has_detail: false,
    },
    2: { speech: SPEECH_A_IRATAY, has_detail: true },
    14: { speech: SPEECH_UNNAMED_IRATAY, has_detail: false },
  },
  ivalino: {
    1: {
      speech: '這是 魚K。我們 ivalino 還沒有分類的答案；怎麼處理還沒有答案。',
      has_detail: false,
    },
    2: { speech: SPEECH_A_IVALINO, has_detail: false },
    14: {
      speech: '這是 這條還沒有名字的魚。我們 ivalino 還沒有分類的答案；怎麼處理還沒有答案。',
      has_detail: false,
    },
  },
}
/** 圖鑑裡有魚A（編號 2）、沒有名字的魚（編號 14） */
const COLLECTION_A_B = [
  { fish_id: 2, number: 1, my: {} },
  { fish_id: 14, number: 2, my: {} },
]

// 每個測試結束時拆掉頁面，免得前一個頁面的鍵盤監聽留在 window 上
const mounted = []

const mountPage = async (overrides = {}) => {
  const wrapper = mount(Home, {
    attachTo: document.body,
    props: {
      character: { name: '阿海', tribe: 'yayo' },
      tribes: ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'],
      current_tribe: 'iratay',
      spots: {
        iratay: tribeSpots(),
        ivalino: tribeSpots(),
      },
      island_fish: ISLAND_FISH,
      result_options: {
        food_category: ['oyod', 'rahet', 'kakanen no rarakeh'],
        processing_method: ['去魚鱗', '不去魚鱗', '剝皮', '不食用', '我不知道'],
      },
      unknown_processing_method: '我不知道',
      character_id: 7,
      fish_speech: {},
      collection: [],
      record_questions: null,
      name_max_length: 50,
      ...overrides,
    },
  })
  mounted.push(wrapper)
  await flushPromises()
  return wrapper
}

const mountBasePage = mountPage

const valueOf = (wrapper, label) =>
  wrapper
    .findAll('dt')
    .find((dt) => dt.text() === label)
    .element.nextElementSibling.textContent.trim()

const sceneOptions = () => scene.createIslandScene.mock.calls[0][1]

/** 假的寫入點：每次呼叫回傳一個由測試決定何時成功或失敗的請求 */
const stubLocationRequests = () => {
  const requests = []
  const post = vi.fn(
    () =>
      new Promise((resolve, reject) => {
        requests.push({ resolve: () => resolve({ status: 204 }), reject })
      })
  )
  vi.stubGlobal('axios', { post })
  return { post, requests }
}

/** 角色走進某個部落的格子（3D 畫面回報） */
const walkInto = async (tribe) => {
  sceneOptions().onTribeChange(tribe)
  await flushPromises()
}

describe('Island/Home 地圖頁', () => {
  beforeEach(() => {
    // 對話框的字一個一個跑（#216 規格修訂 3）用 setInterval；換成假的，由測試決定何時跑完
    vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] })
    scene.createIslandScene.mockReset().mockReturnValue({ dispose: scene.dispose })
    scene.dispose.mockReset()
    inertia.reload.mockReset()
    inertia.post.mockReset()
    inertia.formPosts.length = 0
    inertia.handlers.length = 0
    window.localStorage.clear()
    window.history.replaceState({}, '', '/island')
  })

  afterEach(() => {
    vi.useRealTimers()
    mounted.splice(0).forEach((wrapper) => wrapper.unmount())
    vi.unstubAllGlobals()
  })

  it('「你現在在」是目前所在部落、「你的部落」是角色自己的部落，兩者分開', async () => {
    const wrapper = await mountPage()

    expect(valueOf(wrapper, '你現在在')).toBe('iratay（漁人）')
    expect(valueOf(wrapper, '你的部落')).toBe('yayo（椰油）')
  })

  it('3D 格子上標示的是目前所在部落，不是角色自己的部落', async () => {
    const wrapper = await mountPage()

    expect(wrapper.get('[data-testid="world-tribe"]').text()).toBe('iratay')
  })

  it('3D 畫在頁面的畫布區塊裡，畫得出來時不顯示「無法顯示」', async () => {
    const wrapper = await mountPage()

    expect(scene.createIslandScene).toHaveBeenCalledTimes(1)
    expect(scene.createIslandScene.mock.calls[0][0]).toBe(
      wrapper.get('[data-testid="world-stage"]').element
    )
    expect(wrapper.find('[data-testid="world-unsupported"]').exists()).toBe(false)
  })

  it('瀏覽器無法建立 3D 畫面時，顯示「這台裝置無法顯示魚島」', async () => {
    scene.createIslandScene.mockImplementation(() => {
      throw new Error('Error creating WebGL context.')
    })

    const wrapper = await mountPage()

    expect(wrapper.get('[data-testid="world-unsupported"]').text()).toBe('這台裝置無法顯示魚島')
  })

  it('系統設定「減少動態效果」會傳給 3D 畫面', async () => {
    vi.stubGlobal('matchMedia', (query) => ({
      matches: query === '(prefers-reduced-motion: reduce)',
    }))

    await mountPage()

    expect(sceneOptions().reducedMotion).toBe(true)
  })

  it('網址帶 ?debug=1 時，角落顯示目前畫質級別與每秒張數', async () => {
    window.history.replaceState({}, '', '/island?debug=1')
    const wrapper = await mountPage()

    sceneOptions().onStats({ level: 'medium', framesPerSecond: 42 })
    await flushPromises()

    expect(wrapper.get('[data-testid="world-debug"]').text()).toBe('畫質：中　每秒 42 張')
  })

  it('網址沒有 ?debug=1 時，不顯示畫質與每秒張數', async () => {
    const wrapper = await mountPage()

    sceneOptions().onStats({ level: 'low', framesPerSecond: 12 })
    await flushPromises()

    expect(wrapper.find('[data-testid="world-debug"]').exists()).toBe(false)
  })

  it('離開頁面時停掉 3D 畫面', async () => {
    const wrapper = await mountPage()

    wrapper.unmount()

    expect(scene.dispose).toHaveBeenCalledTimes(1)
  })

  it('不再有平面地圖的「看這裡的魚」入口', async () => {
    const wrapper = await mountPage()

    expect(wrapper.findAll('a').some((a) => a.text() === '看這裡的魚')).toBe(false)
    expect(wrapper.text()).not.toContain('點部落，角色就會走過去')
  })

  it('#207 #223 3D 畫面下方標示地圖與地形資料的來源（授權要求）', async () => {
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="map-credit"]').text()).toBe(
      '地圖資料 © OpenStreetMap contributors；地形資料：U.S. Geological Survey（SRTM、GMTED2010）、NOAA（ETOPO1）'
    )
  })

  it('#200 角色的起點是目前所在部落', async () => {
    await mountPage()

    expect(sceneOptions().startTribe).toBe('iratay')
  })

  it('#200 走進另一個部落：左上角標示與「你現在在」立刻換成那個部落，「你的部落」不變', async () => {
    stubLocationRequests()
    const wrapper = await mountPage()

    await walkInto('ivalino')

    expect(wrapper.get('[data-testid="world-tribe"]').text()).toBe('ivalino')
    expect(valueOf(wrapper, '你現在在')).toBe('ivalino（野銀）')
    expect(valueOf(wrapper, '你的部落')).toBe('yayo（椰油）')
  })

  it('#200 走進另一個部落：把那個部落記到伺服器，不換頁', async () => {
    const { post } = stubLocationRequests()
    await mountPage()

    await walkInto('ivalino')

    expect(post).toHaveBeenCalledTimes(1)
    expect(post).toHaveBeenCalledWith('/island/location', { tribe: 'ivalino' })
    expect(scene.createIslandScene).toHaveBeenCalledTimes(1)
  })

  it('#200 記到伺服器失敗：標示照常換、不顯示錯誤', async () => {
    vi.spyOn(console, 'warn').mockImplementation(() => {})
    const { requests } = stubLocationRequests()
    const wrapper = await mountPage()

    await walkInto('ivalino')
    requests[0].reject(new Error('Network Error'))
    await flushPromises()

    expect(valueOf(wrapper, '你現在在')).toBe('ivalino（野銀）')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('#200 前一筆還沒回來時又走進兩個部落：只留最新的，前一筆回來後才送，中間那個不送', async () => {
    const { post, requests } = stubLocationRequests()
    const wrapper = await mountPage()

    await walkInto('ivalino')
    await walkInto('iranmeilek')
    await walkInto('iraraley')

    expect(post).toHaveBeenCalledTimes(1)
    expect(valueOf(wrapper, '你現在在')).toBe('iraraley（朗島）')

    requests[0].resolve()
    await flushPromises()

    expect(post.mock.calls.map(([, body]) => body.tribe)).toEqual(['ivalino', 'iraraley'])
  })

  it('#200 前一筆失敗：不重送那一筆，排隊中的最新部落照樣送', async () => {
    vi.spyOn(console, 'warn').mockImplementation(() => {})
    const { post, requests } = stubLocationRequests()
    await mountPage()

    await walkInto('ivalino')
    await walkInto('iraraley')
    requests[0].reject(new Error('Network Error'))
    await flushPromises()

    expect(post.mock.calls.map(([, body]) => body.tribe)).toEqual(['ivalino', 'iraraley'])
  })

  it('#200 前一筆失敗、沒有排隊中的部落：不自動重送，等下一次走進別的部落再記', async () => {
    vi.spyOn(console, 'warn').mockImplementation(() => {})
    const { post, requests } = stubLocationRequests()
    await mountPage()

    await walkInto('ivalino')
    requests[0].reject(new Error('Network Error'))
    await flushPromises()
    expect(post).toHaveBeenCalledTimes(1)

    await walkInto('iranmeilek')

    expect(post.mock.calls.map(([, body]) => body.tribe)).toEqual(['ivalino', 'iranmeilek'])
  })

  it('#200 一筆一筆送：每一筆回來後再走進別的部落，照順序各送一次', async () => {
    const { post, requests } = stubLocationRequests()
    await mountPage()

    await walkInto('ivalino')
    requests[0].resolve()
    await flushPromises()
    await walkInto('imowrod')
    requests[1].resolve()
    await flushPromises()

    expect(post.mock.calls.map(([, body]) => body.tribe)).toEqual(['ivalino', 'imowrod'])
  })

  it('#200 瀏覽器無法建立 3D 畫面時不呼叫寫入點', async () => {
    const { post } = stubLocationRequests()
    scene.createIslandScene.mockImplementation(() => {
      throw new Error('Error creating WebGL context.')
    })

    await mountPage()

    expect(post).not.toHaveBeenCalled()
  })

  it('#200 操作說明提到走路、轉鏡頭與拉遠拉近', async () => {
    const wrapper = await mountPage()

    expect(wrapper.text()).toContain('用方向鍵走路，或點地面走過去')
    expect(wrapper.text()).toContain('拖曳畫面轉鏡頭')
    expect(wrapper.text()).toContain('滾輪或雙指拉遠拉近')
  })

  /** 角色走進或離開某一件的範圍（3D 畫面回報）；slot 0～3 依序是 vahay、pananadengan、zazawan、tagakal */
  const reachSpot = async (spot) => {
    sceneOptions().onSpotChange(spot)
    await flushPromises()
  }
  /** 對話框裡正在跑的字全部跑完（#216 規格修訂 3） */
  const finishTyping = async () => {
    vi.advanceTimersByTime(60_000)
    await flushPromises()
  }
  /** 只按一次空白鍵（不等字跑完）；回傳事件，看有沒有擋掉瀏覽器原本的行為（捲動網頁） */
  const pressSpaceOnce = async (target = window) => {
    const event = new KeyboardEvent('keydown', { key: ' ', cancelable: true, bubbles: true })
    target.dispatchEvent(event)
    await flushPromises()
    return event
  }
  /** 等這一句的字跑完再按空白鍵（預設送到 window），按完也等新的一句跑完 */
  const pressSpace = async (target = window) => {
    await finishTyping()
    const event = await pressSpaceOnce(target)
    await finishTyping()
    return event
  }
  /** 點了目前範圍那一件的家人（3D 畫面回報） */
  const tapMember = async () => {
    sceneOptions().onMemberTap()
    await flushPromises()
  }
  const prompt = (wrapper) => wrapper.find('[data-testid="spot-prompt"]')
  const dialog = (wrapper) => wrapper.find('[data-testid="spot-dialog"]')
  /** 等這一句的字跑完再點對話框空白的地方，點完也等新的一句跑完 */
  const clickDialog = async (wrapper) => {
    await finishTyping()
    await dialog(wrapper).trigger('click')
    await finishTyping()
  }
  const mockTouch = (coarse) =>
    vi.stubGlobal('matchMedia', (query) => ({ matches: coarse && query === '(pointer: coarse)' }))

  describe('#201 走近的提示與問好', () => {
    it('後端給的地點清單傳給 3D 畫面（四件上方的招牌用後端的名稱）', async () => {
      const wrapper = await mountPage()

      expect(sceneOptions().spots).toEqual(wrapper.props('spots'))
    })

    it('左上角的所在部落只顯示族語', async () => {
      const wrapper = await mountPage()

      expect(wrapper.get('[data-testid="world-tribe"]').text()).toBe('iratay')
    })

    it('走進第 2 戶 pananadengan 的範圍：下緣只顯示稱呼「akay」與「按空白鍵」，不顯示地點名稱與筆數（規格修訂 2）', async () => {
      const wrapper = await mountPage()

      await reachSpot({ tribe: 'iratay', hex: 1, slot: 1 })

      expect(prompt(wrapper).text()).toContain('akay')
      expect(prompt(wrapper).text()).toContain('按空白鍵')
      expect(prompt(wrapper).text()).not.toContain('pananadengan')
      expect(prompt(wrapper).text()).not.toContain('有結果')
      expect(prompt(wrapper).text()).not.toContain('Enter')
    })

    it('觸控裝置寫「點一下對方」', async () => {
      mockTouch(true)
      const wrapper = await mountPage()

      await reachSpot({ tribe: 'iratay', hex: 1, slot: 1 })

      expect(prompt(wrapper).text()).toContain('點一下對方')
      expect(prompt(wrapper).text()).not.toContain('按空白鍵')
    })

    it('離開範圍：提示消失', async () => {
      const wrapper = await mountPage()
      await reachSpot({ tribe: 'iratay', hex: 1, slot: 1 })

      await reachSpot(null)

      expect(prompt(wrapper).exists()).toBe(false)
    })

    it('按空白鍵：對話框出現，左上角是玩家的角色名，內容是「akay kong」，網頁不捲動；一句一句按到最後再按就關掉（#216 規格修訂 1）', async () => {
      const wrapper = await mountPage()
      await reachSpot({ tribe: 'iratay', hex: 1, slot: 1 })

      const event = await pressSpace()
      expect(event.defaultPrevented).toBe(true)
      expect(dialog(wrapper).get('[data-testid="spot-dialog-speaker"]').text()).toBe('阿海')
      expect(dialog(wrapper).text()).toContain('akay kong')
      expect(prompt(wrapper).exists()).toBe(false)

      // 這一件（iratay 第 2 戶 pananadengan）沒有魚：② 你好。這裡還沒有魚。→ 關掉（有魚時見 #216 的測試）
      await pressSpace()
      expect(dialog(wrapper).exists()).toBe(true)
      await pressSpace()
      expect(dialog(wrapper).exists()).toBe(false)
      expect(prompt(wrapper).exists()).toBe(true)
    })

    it('Enter 不再開對話框', async () => {
      const wrapper = await mountPage()
      await reachSpot({ tribe: 'iratay', hex: 1, slot: 1 })

      window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }))
      await flushPromises()

      expect(dialog(wrapper).exists()).toBe(false)
    })

    it('點那位家人：打開對話框；再點一次關掉', async () => {
      const wrapper = await mountPage()
      await reachSpot({ tribe: 'iratay', hex: 2, slot: 3 })

      await tapMember()
      await finishTyping()
      expect(dialog(wrapper).text()).toContain('kaka kong')

      await tapMember()
      expect(dialog(wrapper).exists()).toBe(false)
    })

    it('點提示橫條打開對話框，點對話框一句一句往下，到最後一句再點就關掉（#216 規格修訂 1）', async () => {
      const wrapper = await mountPage()
      await reachSpot({ tribe: 'iratay', hex: 2, slot: 3 })

      await prompt(wrapper).trigger('click')
      await finishTyping()
      expect(dialog(wrapper).text()).toContain('kaka kong')

      // 這一件（iratay 第 3 戶 tagakal）沒有魚：① 問好 → ② 你好。這裡還沒有魚。→ 關掉
      await clickDialog(wrapper)
      expect(dialog(wrapper).exists()).toBe(true)
      await clickDialog(wrapper)
      expect(dialog(wrapper).exists()).toBe(false)
    })

    it('對話框開著時離開範圍：對話框一起關掉', async () => {
      const wrapper = await mountPage()
      await reachSpot({ tribe: 'iratay', hex: 0, slot: 0 })
      await pressSpace()

      await reachSpot(null)

      expect(dialog(wrapper).exists()).toBe(false)
      expect(prompt(wrapper).exists()).toBe(false)
    })

    it('沒有提示時按空白鍵：沒有反應，也不擋瀏覽器原本的行為', async () => {
      const wrapper = await mountPage()

      const event = await pressSpace()

      expect(event.defaultPrevented).toBe(false)
      expect(dialog(wrapper).exists()).toBe(false)
      expect(prompt(wrapper).exists()).toBe(false)
    })
  })

  describe('#216 問好後家人回應、選魚的照片、家人講那條魚', () => {
    // #232：圖鑑裡有兩條魚，家人說的話照 fish_speech
    const mountPage = (overrides = {}) =>
      mountBasePage({ collection: COLLECTION_A_B, fish_speech: FISH_SPEECH, ...overrides })
    const photos = (wrapper) => dialog(wrapper).findAll('[data-testid="fish-photo"]')
    const npcLine = (wrapper) => dialog(wrapper).get('[data-testid="npc-line"]').text()
    const playerLine = (wrapper) => dialog(wrapper).find('[data-testid="player-line"]')
    const whoSaid = (wrapper) => dialog(wrapper).find('[data-testid="who-said"]')
    const detail = (wrapper) => wrapper.find('[role="dialog"]')
    /** 走到第 1 戶的 pananadengan（iratay 列魚A、沒有名字的魚）並打開對話框（停在 ① 玩家問好） */
    const openAt = async (spot = { tribe: 'iratay', hex: 0, slot: 1 }) => {
      const wrapper = await mountPage()
      await reachSpot(spot)
      await pressSpace()
      return wrapper
    }
    /** 打開對話框並按到 ③「你帶了什麼魚來？」＋照片 */
    const openToPhotos = async (spot) => {
      const wrapper = await openAt(spot)
      await pressSpace()
      await pressSpace()
      return wrapper
    }
    /** 點照片（④ ikongo o ya?）再按一下到 ⑤ 家人講那條魚 */
    const pick = async (wrapper, index) => {
      await photos(wrapper)[index].trigger('click')
      await pressSpace()
    }

    it('一句一句來：① 只有玩家的問好語 → ② 只有家人「阿海，你好」→ ③ 家人「你帶了什麼魚來？」才出現照片；句子右下角提示「按空白鍵」', async () => {
      const wrapper = await openAt()
      expect(playerLine(wrapper).text()).toBe('akay kong')
      expect(dialog(wrapper).find('[data-testid="npc-line"]').exists()).toBe(false)
      expect(photos(wrapper)).toHaveLength(0)
      expect(dialog(wrapper).get('[data-testid="next-hint"]').text()).toBe('按空白鍵')

      await pressSpace()
      expect(npcLine(wrapper)).toBe('阿海，你好')
      expect(playerLine(wrapper).exists()).toBe(false)
      expect(photos(wrapper)).toHaveLength(0)
      expect(dialog(wrapper).text()).not.toContain('akay kong')

      await pressSpace()
      expect(npcLine(wrapper)).toBe('你帶了什麼魚來？')
      expect(dialog(wrapper).text()).not.toContain('阿海，你好')
      expect(photos(wrapper)).toHaveLength(2)
    })

    it('每一步句子右下角都有提示：有魚的 ③、⑤ 與沒有魚的 ② 也有（電腦「按空白鍵」）', async () => {
      const hint = (wrapper) => dialog(wrapper).get('[data-testid="next-hint"]').text()
      const wrapper = await openToPhotos()
      expect(hint(wrapper)).toBe('按空白鍵')

      await pick(wrapper, 0)
      expect(npcLine(wrapper)).toBe(SPEECH_A_IRATAY)
      expect(hint(wrapper)).toBe('按空白鍵')

      await wrapper.setProps({ collection: [] })
      await reachSpot({ tribe: 'iratay', hex: 0, slot: 2 })
      await pressSpace()
      await pressSpace()
      expect(npcLine(wrapper)).toBe('阿海，你好。你手上沒有魚，先去海邊找一條再來問我。')
      expect(hint(wrapper)).toBe('按空白鍵')
    })

    it('觸控裝置：③、⑤ 與沒有魚的 ② 的提示寫「點一下」', async () => {
      mockTouch(true)
      const hint = (wrapper) => dialog(wrapper).get('[data-testid="next-hint"]').text()
      const wrapper = await openToPhotos()
      expect(hint(wrapper)).toBe('點一下')

      await pick(wrapper, 0)
      expect(hint(wrapper)).toBe('點一下')

      await wrapper.setProps({ collection: [] })
      await reachSpot({ tribe: 'iratay', hex: 0, slot: 2 })
      await pressSpace()
      await pressSpace()
      expect(hint(wrapper)).toBe('點一下')
    })

    it('觸控裝置：提示寫「點一下」，點對話框就是下一句', async () => {
      mockTouch(true)
      const wrapper = await openAt()
      expect(dialog(wrapper).get('[data-testid="next-hint"]').text()).toBe('點一下')

      await clickDialog(wrapper)
      expect(npcLine(wrapper)).toBe('阿海，你好')
      await clickDialog(wrapper)
      expect(npcLine(wrapper)).toBe('你帶了什麼魚來？')
    })

    it('點照片：④ 玩家「ikongo o ya?」＋剛才選的那條魚的照片（規格修訂 2），再按一下 ⑤ 家人才講那條魚', async () => {
      const wrapper = await openToPhotos()

      await photos(wrapper)[1].trigger('click')
      await finishTyping()
      expect(playerLine(wrapper).text()).toBe('ikongo o ya?')
      expect(dialog(wrapper).get('[data-testid="asked-photo"]').attributes('src')).toBe('/b.jpg')
      expect(dialog(wrapper).find('[data-testid="npc-line"]').exists()).toBe(false)
      expect(photos(wrapper)).toHaveLength(0)

      await clickDialog(wrapper)
      expect(npcLine(wrapper)).toBe(NOWHERE_LINE)
      expect(dialog(wrapper).get('[data-testid="asked-photo"]').attributes('src')).toBe('/b.jpg')
      expect(playerLine(wrapper).exists()).toBe(false)
    })

    it('③ 照片那一步、⑤ 講魚那一步：空白鍵關掉對話框', async () => {
      const wrapper = await openToPhotos()
      await pressSpace()
      expect(dialog(wrapper).exists()).toBe(false)

      await pressSpace()
      await pressSpace()
      await pressSpace()
      await pick(wrapper, 0)
      expect(npcLine(wrapper)).toBe(SPEECH_A_IRATAY)
      await pressSpace()
      expect(dialog(wrapper).exists()).toBe(false)
    })

    describe('文字慢慢跳出（規格修訂 3）', () => {
      const visibleLine = (wrapper, id) => dialog(wrapper).get(`[data-testid="${id}"]`).text()

      it('一個字一個字出現，每個字約 0.05 秒；螢幕報讀器一次讀整句', async () => {
        const wrapper = await mountPage()
        await reachSpot({ tribe: 'iratay', hex: 0, slot: 1 })
        await pressSpaceOnce()

        expect(visibleLine(wrapper, 'player-line')).toBe('')
        vi.advanceTimersByTime(300)
        await flushPromises()
        expect(visibleLine(wrapper, 'player-line')).toBe('akay k')
        expect(dialog(wrapper).get('[data-testid="player-line"]').attributes('aria-hidden')).toBe(
          'true'
        )
        expect(wrapper.get('[data-testid="line-for-reader"]').text()).toBe('akay kong')
      })

      it('報讀區域在打開對話框之前就在（空的），打開後同一個區域換成第一句，報讀器才會讀到第一句', async () => {
        const wrapper = await mountPage()
        const reader = wrapper.get('[data-testid="line-for-reader"]')
        expect(reader.attributes('aria-live')).toBe('polite')
        expect(reader.text()).toBe('')

        await reachSpot({ tribe: 'iratay', hex: 0, slot: 1 })
        await pressSpaceOnce()

        expect(wrapper.get('[data-testid="line-for-reader"]').element).toBe(reader.element)
        expect(reader.text()).toBe('akay kong')
        expect(dialog(wrapper).element.contains(reader.element)).toBe(false)

        await reachSpot(null)
        expect(wrapper.get('[data-testid="line-for-reader"]').element).toBe(reader.element)
        expect(reader.text()).toBe('')
      })

      it('角色名有擴充漢字或 emoji 時，一次跑一個完整的字，不出現半個字', async () => {
        const wrapper = await mountPage({ character: { name: '𠀋阿👨‍👩‍👧', tribe: 'yayo' } })
        await reachSpot({ tribe: 'iratay', hex: 0, slot: 1 })
        await pressSpaceOnce()
        await finishTyping()
        await pressSpaceOnce()

        const shown = []
        for (let i = 0; i < 3; i += 1) {
          vi.advanceTimersByTime(50)
          await flushPromises()
          shown.push(visibleLine(wrapper, 'npc-line'))
        }
        expect(shown).toEqual(['𠀋', '𠀋阿', '𠀋阿👨‍👩‍👧'])
      })

      it('瀏覽器不支援 Intl.Segmenter（不能確定怎樣算一個完整的字）時不跑字，整句直接出現，不會出現半個字', async () => {
        vi.stubGlobal('Intl', { ...Intl, Segmenter: undefined })
        // 分解的韓文字（兩個碼位一個字）、組合 emoji、e 加結合重音符號
        const name = '\u1100\u1161👨‍👩‍👧e\u0301'
        const wrapper = await mountPage({ character: { name, tribe: 'yayo' } })
        await reachSpot({ tribe: 'iratay', hex: 0, slot: 1 })
        await pressSpaceOnce()
        expect(visibleLine(wrapper, 'player-line')).toBe('akay kong')

        await pressSpaceOnce()
        expect(visibleLine(wrapper, 'npc-line')).toBe(`${name}，你好`)
      })

      it('字還在跑時按空白鍵：先整句顯示，不跳下一句；再按才往下', async () => {
        const wrapper = await mountPage()
        await reachSpot({ tribe: 'iratay', hex: 0, slot: 1 })
        await pressSpaceOnce()
        vi.advanceTimersByTime(100)

        await pressSpaceOnce()
        expect(visibleLine(wrapper, 'player-line')).toBe('akay kong')

        await pressSpaceOnce()
        expect(dialog(wrapper).find('[data-testid="player-line"]').exists()).toBe(false)
        expect(wrapper.get('[data-testid="line-for-reader"]').text()).toBe('阿海，你好')
      })

      it('字還在跑時點對話框：先整句顯示，不關掉', async () => {
        const wrapper = await openToPhotos()
        await photos(wrapper)[0].trigger('click')
        await finishTyping()
        await pressSpaceOnce()
        vi.advanceTimersByTime(100)

        await dialog(wrapper).trigger('click')
        expect(visibleLine(wrapper, 'npc-line')).toBe(SPEECH_A_IRATAY)
        expect(dialog(wrapper).exists()).toBe(true)
      })

      it('③ 的照片、⑤ 的按鈕在句子跑完後才出現', async () => {
        const wrapper = await openAt()
        await pressSpace()
        await pressSpaceOnce()
        expect(photos(wrapper)).toHaveLength(0)
        await finishTyping()
        expect(photos(wrapper)).toHaveLength(2)

        await photos(wrapper)[0].trigger('click')
        await finishTyping()
        await pressSpaceOnce()
        expect(dialog(wrapper).find('[data-testid="ask-another"]').exists()).toBe(false)
        expect(whoSaid(wrapper).exists()).toBe(false)
        await finishTyping()
        expect(dialog(wrapper).find('[data-testid="ask-another"]').exists()).toBe(true)
        expect(whoSaid(wrapper).exists()).toBe(true)
      })

      it('「減少動態效果」時不跑字，整句直接出現', async () => {
        vi.stubGlobal('matchMedia', (query) => ({
          matches: query === '(prefers-reduced-motion: reduce)',
        }))
        const wrapper = await mountPage()
        await reachSpot({ tribe: 'iratay', hex: 0, slot: 1 })
        await pressSpaceOnce()

        expect(visibleLine(wrapper, 'player-line')).toBe('akay kong')
      })
    })

    it('照片上不寫魚名，無障礙名稱是「第 n 條魚」，依圖鑑的順序', async () => {
      const wrapper = await openToPhotos()

      expect(photos(wrapper).map((photo) => photo.attributes('aria-label'))).toEqual([
        '第 1 條魚',
        '第 2 條魚',
      ])
      expect(photos(wrapper).map((photo) => photo.get('img').attributes('src'))).toEqual([
        '/a.jpg',
        '/b.jpg',
      ])
      expect(dialog(wrapper).text()).not.toContain('魚A')
    })

    it('vahay 用 kaminan 的問好語；不論哪一件，列的都是玩家自己的魚（#232）', async () => {
      const wrapper = await openAt({ tribe: 'iratay', hex: 0, slot: 0 })
      expect(playerLine(wrapper).text()).toBe('kaminan kong')

      await pressSpace()
      await pressSpace()
      expect(photos(wrapper).map((photo) => photo.get('img').attributes('src'))).toEqual([
        '/a.jpg',
        '/b.jpg',
      ])
    })

    it('手上沒魚、圖鑑也是空的：② 家人提醒先去海邊找魚，不列照片；再按就關掉（#232）', async () => {
      const wrapper = await mountPage({ collection: [] })
      await reachSpot({ tribe: 'iratay', hex: 0, slot: 2 })
      await pressSpace()

      await pressSpace()
      expect(npcLine(wrapper)).toBe('阿海，你好。你手上沒有魚，先去海邊找一條再來問我。')
      expect(photos(wrapper)).toHaveLength(0)

      await pressSpace()
      expect(dialog(wrapper).exists()).toBe(false)
    })

    it('家人講那條魚、旁邊放那條魚的照片；有可以看的說法才出現「誰說的？」；沒有「我來說」', async () => {
      const wrapper = await openToPhotos()

      await pick(wrapper, 0)

      expect(npcLine(wrapper)).toBe(SPEECH_A_IRATAY)
      expect(dialog(wrapper).get('[data-testid="asked-photo"]').attributes('src')).toBe('/a.jpg')
      expect(whoSaid(wrapper).exists()).toBe(true)
      expect(photos(wrapper)).toHaveLength(0)
      expect(wrapper.text()).not.toContain('我來說')
      expect(wrapper.find('a[href*="/fish/"]').exists()).toBe(false)
    })

    it('沒有可以看的說法的魚：不出現「誰說的？」', async () => {
      const wrapper = await openToPhotos()

      await pick(wrapper, 1)

      expect(npcLine(wrapper)).toBe(NOWHERE_LINE)
      expect(whoSaid(wrapper).exists()).toBe(false)
    })

    it('「誰說的？」打開細節視窗（所在部落是這一戶的部落），沒有「我也來說」；Escape 只關細節視窗，焦點回到「誰說的？」', async () => {
      const wrapper = await openToPhotos()
      await pick(wrapper, 0)

      await whoSaid(wrapper).trigger('click')
      await flushPromises()
      expect(detail(wrapper).exists()).toBe(true)
      expect(detail(wrapper).find('[data-current="true"]').text()).toContain('iratay')
      expect(wrapper.find('[data-testid="answer-link"]').exists()).toBe(false)

      document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
      await flushPromises()
      expect(detail(wrapper).exists()).toBe(false)
      expect(dialog(wrapper).exists()).toBe(true)
      expect(document.activeElement).toBe(whoSaid(wrapper).element)
    })

    it('細節視窗開著時：空白鍵不關對話框；走出範圍時細節視窗與對話框一起關掉', async () => {
      const wrapper = await openToPhotos()
      await pick(wrapper, 0)
      await whoSaid(wrapper).trigger('click')
      await flushPromises()

      await pressSpace(document.activeElement)
      expect(detail(wrapper).exists()).toBe(true)
      expect(dialog(wrapper).exists()).toBe(true)

      await reachSpot(null)
      expect(detail(wrapper).exists()).toBe(false)
      expect(dialog(wrapper).exists()).toBe(false)
    })

    it('「問別條」回到 ③（不重複問好），剛才問過的照片標「聽過了」，沒問過的不標', async () => {
      const wrapper = await openToPhotos()
      await pick(wrapper, 0)

      await dialog(wrapper).get('[data-testid="ask-another"]').trigger('click')
      await finishTyping()

      expect(npcLine(wrapper)).toBe('你帶了什麼魚來？')
      expect(photos(wrapper)[0].text()).toContain('聽過了')
      expect(photos(wrapper)[1].text()).not.toContain('聽過了')
    })

    it('關掉再打開：從 ① 重新開始，「聽過了」保留；走到另一個部落同一件：說那個部落的話，「聽過了」分開記', async () => {
      const wrapper = await openToPhotos()
      await pick(wrapper, 0)

      await pressSpace()
      await pressSpace()
      expect(playerLine(wrapper).text()).toBe('akay kong')
      await pressSpace()
      await pressSpace()
      expect(photos(wrapper)[0].text()).toContain('聽過了')

      await reachSpot({ tribe: 'ivalino', hex: 0, slot: 1 })
      await pressSpace()
      await pressSpace()
      await pressSpace()
      expect(photos(wrapper)).toHaveLength(2)
      expect(photos(wrapper)[0].text()).not.toContain('聽過了')
      await pick(wrapper, 0)
      // #232：ivalino 沒有說法、iratay 有 → 家人說不知道，指引去問 iratay
      expect(npcLine(wrapper)).toBe('我不知道這條魚。你去問 iratay（漁人）的人看看。')
      expect(whoSaid(wrapper).exists()).toBe(false)
    })

    it('焦點在對話框裡的按鈕上：空白鍵是按那顆按鈕，不關對話框、這一頁不攔', async () => {
      const wrapper = await openToPhotos()
      const photo = photos(wrapper)[0].element
      photo.focus()

      const event = await pressSpace(photo)

      expect(event.defaultPrevented).toBe(false)
      expect(dialog(wrapper).exists()).toBe(true)
    })

    it('點照片不會關掉對話框；⑤ 點對話框空白的地方關掉（#201）', async () => {
      const wrapper = await openToPhotos()

      await pick(wrapper, 0)
      expect(dialog(wrapper).exists()).toBe(true)

      await clickDialog(wrapper)
      expect(dialog(wrapper).exists()).toBe(false)
    })

    describe('照片列的箭頭（電腦）', () => {
      /** 假裝照片列有 $total 寬、看得到 $visible、已捲到 $left（jsdom 不排版） */
      const layout = async (wrapper, { visible, total, left }) => {
        const strip = dialog(wrapper).get('[data-testid="fish-strip"]')
        Object.defineProperty(strip.element, 'clientWidth', { configurable: true, value: visible })
        Object.defineProperty(strip.element, 'scrollWidth', { configurable: true, value: total })
        strip.element.scrollLeft = left
        strip.element.scrollBy = vi.fn()
        await strip.trigger('scroll')
        return strip
      }
      const arrow = (wrapper, side) => dialog(wrapper).find(`[data-testid="strip-${side}"]`)

      it('在最左邊：只顯示右箭頭；按右箭頭往右捲一個畫面寬（平滑）', async () => {
        const wrapper = await openToPhotos()
        const strip = await layout(wrapper, { visible: 300, total: 900, left: 0 })

        expect(arrow(wrapper, 'prev').exists()).toBe(false)
        await arrow(wrapper, 'next').trigger('click')
        expect(strip.element.scrollBy).toHaveBeenCalledWith({ left: 300, behavior: 'smooth' })
      })

      it('捲到最右邊：只顯示左箭頭；按左箭頭往左捲一個畫面寬', async () => {
        const wrapper = await openToPhotos()
        const strip = await layout(wrapper, { visible: 300, total: 900, left: 600 })

        expect(arrow(wrapper, 'next').exists()).toBe(false)
        await arrow(wrapper, 'prev').trigger('click')
        expect(strip.element.scrollBy).toHaveBeenCalledWith({ left: -300, behavior: 'smooth' })
      })

      it('全部看得到：兩邊都沒有箭頭', async () => {
        const wrapper = await openToPhotos()
        await layout(wrapper, { visible: 300, total: 300, left: 0 })

        expect(arrow(wrapper, 'prev').exists()).toBe(false)
        expect(arrow(wrapper, 'next').exists()).toBe(false)
      })

      it('「減少動態效果」時捲動不用平滑動畫', async () => {
        vi.stubGlobal('matchMedia', (query) => ({
          matches: query === '(prefers-reduced-motion: reduce)',
        }))
        const wrapper = await openToPhotos()
        const strip = await layout(wrapper, { visible: 300, total: 900, left: 0 })

        await arrow(wrapper, 'next').trigger('click')
        expect(strip.element.scrollBy).toHaveBeenCalledWith({ left: 300, behavior: 'auto' })
      })
    })
  })
  describe('#232 海邊找魚、手上的魚', () => {
    const HELD_KEY = 'island.heldFish.7'
    /** 角色走進或離開海邊的範圍（3D 畫面回報部落代碼或 null） */
    const reachBeach = async (tribe) => {
      sceneOptions().onBeachChange(tribe)
      await flushPromises()
    }
    /** 亂數固定：0 抽到第一條（編號 1）、0.99 抽到最後一條（編號 14） */
    const fixRandom = (value) => vi.spyOn(Math, 'random').mockReturnValue(value)
    const npcLine = (wrapper) => wrapper.get('[data-testid="npc-line"]').text()
    const buttonTexts = (wrapper) =>
      wrapper.findAll('[data-testid="beach-actions"] button').map((button) => button.text())
    /** 記錄視窗資料的 partial reload 回來：setProps 換成這條魚的三題，再呼叫 onSuccess */
    const answerReload = async (wrapper, questions) => {
      const options = inertia.reload.mock.calls.at(-1)[0]
      await wrapper.setProps({ record_questions: questions })
      options.onSuccess?.()
      await flushPromises()
    }
    const QUESTIONS = (answers = {}) =>
      ['name', 'food_category', 'processing_method'].map((key) => ({
        key,
        options: ['x'],
        my_answer: answers[key] ?? null,
      }))

    afterEach(() => vi.restoreAllMocks())

    it('3D 畫面有海邊的回報；走進海邊出現「海邊」＋「按空白鍵找魚」，走出去消失', async () => {
      const wrapper = await mountPage()

      await reachBeach('iratay')
      expect(prompt(wrapper).text()).toContain('海邊')
      expect(prompt(wrapper).text()).toContain('按空白鍵找魚')

      await reachBeach(null)
      expect(prompt(wrapper).exists()).toBe(false)
    })

    it('觸控裝置寫「點一下找魚」', async () => {
      mockTouch(true)
      const wrapper = await mountPage()

      await reachBeach('iratay')

      expect(prompt(wrapper).text()).toContain('點一下找魚')
    })

    it('按空白鍵：「你找到一條魚！」和那條魚的照片（不寫魚名），存在這個角色的瀏覽器儲存', async () => {
      fixRandom(0)
      const wrapper = await mountPage()
      await reachBeach('iratay')

      await pressSpace()

      expect(npcLine(wrapper)).toBe('你找到一條魚！')
      expect(wrapper.get('[data-testid="found-photo"]').attributes('src')).toBe('/k.jpg')
      expect(dialog(wrapper).text()).not.toContain('魚K')
      expect(window.localStorage.getItem(HELD_KEY)).toBe('1')
    })

    it('點提示也能找魚', async () => {
      fixRandom(0)
      const wrapper = await mountPage()
      await reachBeach('iratay')

      await prompt(wrapper).trigger('click')
      await finishTyping()

      expect(npcLine(wrapper)).toBe('你找到一條魚！')
    })

    it('手上已經有魚：先說「你把手上的魚放回海裡。」，按一下才是「你找到一條魚！」', async () => {
      window.localStorage.setItem(HELD_KEY, '2')
      fixRandom(0.99)
      const wrapper = await mountPage()
      await reachBeach('iratay')

      await pressSpace()
      expect(npcLine(wrapper)).toBe('你把手上的魚放回海裡。')

      await pressSpace()
      expect(npcLine(wrapper)).toBe('你找到一條魚！')
      expect(wrapper.get('[data-testid="found-photo"]').attributes('src')).toBe('/b.jpg')
    })

    it('找到魚後載入這條魚自己部落的記錄：沒有回答 → 「記下來」「先不記」', async () => {
      fixRandom(0)
      const wrapper = await mountPage()
      await reachBeach('iratay')
      await pressSpace()

      expect(inertia.reload).toHaveBeenCalledWith(
        expect.objectContaining({ only: ['record_questions'], data: { record_fish: 1 } })
      )
      await answerReload(wrapper, QUESTIONS())

      expect(buttonTexts(wrapper)).toEqual(['記下來', '先不記'])
    })

    it('自己部落已經有這條魚的回答（例如在 among 答過）→ 「收進圖鑑」「先不記」', async () => {
      fixRandom(0)
      const wrapper = await mountPage()
      await reachBeach('iratay')
      await pressSpace()

      await answerReload(wrapper, QUESTIONS({ food_category: 'oyod' }))

      expect(buttonTexts(wrapper)).toEqual(['收進圖鑑', '先不記'])
    })

    it('記錄還沒載入完成前不顯示按鈕', async () => {
      fixRandom(0)
      const wrapper = await mountPage()
      await reachBeach('iratay')
      await pressSpace()

      expect(buttonTexts(wrapper)).toEqual([])
    })

    it('已經在圖鑑裡：多一行「圖鑑裡已經有這條（第 N 條）」，按鈕「改我的記錄」「好」', async () => {
      fixRandom(0)
      const wrapper = await mountPage({
        collection: [
          { fish_id: 2, number: 1, my: {} },
          { fish_id: 1, number: 2, my: { food_category: 'oyod' } },
        ],
      })
      await reachBeach('iratay')
      await pressSpace()

      expect(dialog(wrapper).text()).toContain('圖鑑裡已經有這條（第 2 條）')
      expect(buttonTexts(wrapper)).toEqual(['改我的記錄', '好'])
    })

    it('「先不記」關掉對話框，魚留在手上', async () => {
      fixRandom(0)
      const wrapper = await mountPage()
      await reachBeach('iratay')
      await pressSpace()
      await answerReload(wrapper, QUESTIONS())

      await wrapper.findAll('[data-testid="beach-actions"] button')[1].trigger('click')

      expect(dialog(wrapper).exists()).toBe(false)
      expect(window.localStorage.getItem(HELD_KEY)).toBe('1')
    })

    it('找到魚之後再按空白鍵關掉對話框', async () => {
      fixRandom(0)
      const wrapper = await mountPage()
      await reachBeach('iratay')
      await pressSpace()

      await pressSpace()

      expect(dialog(wrapper).exists()).toBe(false)
    })

    it('沒有任何魚：「海裡現在沒有魚。」，沒有按鈕、不存手上的魚', async () => {
      const wrapper = await mountPage({ island_fish: [] })
      await reachBeach('iratay')

      await pressSpace()

      expect(npcLine(wrapper)).toBe('海裡現在沒有魚。')
      expect(buttonTexts(wrapper)).toEqual([])
      expect(window.localStorage.getItem(HELD_KEY)).toBeNull()
    })

    it('地圖下方「手上的魚」：沒魚寫「還沒有，去海邊找一條」，找到後顯示照片', async () => {
      fixRandom(0)
      const wrapper = await mountPage()
      const held = () => wrapper.get('[data-testid="held-fish"]')

      expect(held().text()).toContain('還沒有，去海邊找一條')

      await reachBeach('iratay')
      await pressSpace()

      expect(held().get('img').attributes('src')).toBe('/k.jpg')
    })

    it('重新整理後手上的魚還在；手上的魚已經不在魚清單裡（軟刪除）當成沒魚', async () => {
      window.localStorage.setItem(HELD_KEY, '2')
      const kept = await mountPage()
      expect(kept.get('[data-testid="held-fish"] img').attributes('src')).toBe('/a.jpg')

      window.localStorage.setItem(HELD_KEY, '99')
      const gone = await mountPage()
      expect(gone.get('[data-testid="held-fish"]').text()).toContain('還沒有，去海邊找一條')
    })
  })
  describe('#232 記錄視窗與圖鑑', () => {
    const HELD_KEY = 'island.heldFish.7'
    const QUESTIONS = (answers = {}) => [
      { key: 'name', options: ['魚K', 'kakan'], my_answer: answers.name ?? null },
      {
        key: 'food_category',
        options: ['oyod', 'rahet'],
        my_answer: answers.food_category ?? null,
      },
      {
        key: 'processing_method',
        options: ['去魚鱗', '我不知道'],
        my_answer: answers.processing_method ?? null,
      },
    ]
    const reachBeach = async (tribe) => {
      sceneOptions().onBeachChange(tribe)
      await flushPromises()
    }
    /** 最近一次 partial reload 回來：換成 questions，再呼叫 onSuccess */
    const answerReload = async (wrapper, questions) => {
      const options = inertia.reload.mock.calls.at(-1)[0]
      await wrapper.setProps({ record_questions: questions })
      options.onSuccess?.()
      await flushPromises()
    }
    /** 在海邊找到第一條魚（編號 1；亂數在 beforeEach 固定為 0），載入記錄後停在找到魚的對話 */
    const catchFirstFish = async (wrapper, answers = {}) => {
      await reachBeach('iratay')
      await pressSpace()
      await answerReload(wrapper, QUESTIONS(answers))
    }
    const beachButton = (wrapper, label) =>
      wrapper.findAll('[data-testid="beach-actions"] button').find((b) => b.text() === label)
    const panel = (wrapper) => wrapper.find('[data-testid="record-panel"]')
    const panelButton = (wrapper, label) =>
      panel(wrapper)
        .findAll('button')
        .find((b) => b.text().replace(/[›✎✓]/g, '').trim() === label)
    const lastCollectCall = () => inertia.post.mock.calls.at(-1)

    // 亂數要在頁面掛上前固定（手上的魚模組在掛上時拿到亂數函式）：0 抽到第一條（編號 1）
    beforeEach(() => vi.spyOn(Math, 'random').mockReturnValue(0))
    afterEach(() => vi.restoreAllMocks())

    it('「記下來」打開記錄視窗：記到自己的部落、三個分頁、只列選項不顯示結果', async () => {
      const wrapper = await mountPage()
      await catchFirstFish(wrapper)

      await beachButton(wrapper, '記下來').trigger('click')

      expect(panel(wrapper).text()).toContain('記到 yayo（椰油）')
      expect(
        panel(wrapper)
          .findAll('[role="tab"]')
          .map((t) => t.text())
      ).toEqual(['叫法', '分類', '處理'])
      expect(
        panel(wrapper)
          .findAll('[data-testid="option"]')
          .map((o) => o.text().replace(/[›✎]/g, '').trim())
      ).toEqual(['魚K', 'kakan', '都不對，我來補充'])
      expect(panel(wrapper).text()).not.toContain('多數')
      expect(panel(wrapper).text()).not.toContain('人')
    })

    it('點選項就送出那一題，記到自己的部落；第一題成功後收進圖鑑', async () => {
      const wrapper = await mountPage()
      await catchFirstFish(wrapper)
      await beachButton(wrapper, '記下來').trigger('click')
      await panel(wrapper).findAll('[role="tab"]')[1].trigger('click')

      await panelButton(wrapper, 'oyod').trigger('click')

      expect(inertia.formPosts.at(-1)).toMatchObject({
        url: '/island/tribes/yayo/fish/1/answers',
        question: 'food_category',
        value: 'oyod',
      })
      inertia.formPosts.at(-1).options.onSuccess()
      await flushPromises()
      expect(lastCollectCall()[0]).toBe('/island/collection/1')
    })

    it('收進圖鑑失敗：顯示「記錄已存好，還沒收進圖鑑」，按「收進圖鑑」再試一次', async () => {
      const wrapper = await mountPage()
      await catchFirstFish(wrapper)
      await beachButton(wrapper, '記下來').trigger('click')
      await panelButton(wrapper, '魚K').trigger('click')
      inertia.formPosts.at(-1).options.onSuccess()
      await flushPromises()

      lastCollectCall()[2].onFinish()
      await flushPromises()

      expect(panel(wrapper).text()).toContain('記錄已存好，還沒收進圖鑑')
      await panelButton(wrapper, '收進圖鑑').trigger('click')
      expect(inertia.post).toHaveBeenCalledTimes(2)
    })

    it('收進圖鑑成功（圖鑑多了這條）：不顯示失敗的提示', async () => {
      const wrapper = await mountPage()
      await catchFirstFish(wrapper)
      await beachButton(wrapper, '記下來').trigger('click')
      await panelButton(wrapper, '魚K').trigger('click')
      inertia.formPosts.at(-1).options.onSuccess()
      await flushPromises()

      await wrapper.setProps({ collection: [{ fish_id: 1, number: 1, my: { name: '魚K' } }] })
      lastCollectCall()[2].onFinish()
      await flushPromises()

      expect(panel(wrapper).text()).not.toContain('還沒收進圖鑑')
    })

    it('在 among 答過的魚：「收進圖鑑」打開記錄視窗，標出原本的答案，上方「收進圖鑑」按了就收', async () => {
      const wrapper = await mountPage()
      await catchFirstFish(wrapper, { name: 'kakan' })

      await beachButton(wrapper, '收進圖鑑').trigger('click')

      expect(panel(wrapper).find('[aria-pressed="true"]').text()).toContain('kakan')
      await panelButton(wrapper, '收進圖鑑').trigger('click')
      expect(lastCollectCall()[0]).toBe('/island/collection/1')
      expect(inertia.formPosts).toEqual([])
    })

    it('已經在圖鑑裡的魚改記錄：送出不再收進圖鑑', async () => {
      const wrapper = await mountPage({ collection: [{ fish_id: 1, number: 1, my: {} }] })
      await reachBeach('iratay')
      await pressSpace()

      await beachButton(wrapper, '改我的記錄').trigger('click')
      await answerReload(wrapper, QUESTIONS())
      await panelButton(wrapper, '魚K').trigger('click')
      inertia.formPosts.at(-1).options.onSuccess()
      await flushPromises()

      expect(inertia.post).not.toHaveBeenCalled()
    })

    it('記錄還沒載入完成：視窗寫「載入中…」', async () => {
      const wrapper = await mountPage({ collection: [{ fish_id: 1, number: 1, my: {} }] })
      await reachBeach('iratay')
      await pressSpace()

      await beachButton(wrapper, '改我的記錄').trigger('click')

      expect(panel(wrapper).text()).toContain('載入中…')
    })

    it('「好了」關掉記錄視窗', async () => {
      const wrapper = await mountPage()
      await catchFirstFish(wrapper)
      await beachButton(wrapper, '記下來').trigger('click')

      await panelButton(wrapper, '好了').trigger('click')

      expect(panel(wrapper).exists()).toBe(false)
    })

    it('地圖下方「手上的魚」的「記下來」也能打開記錄視窗', async () => {
      window.localStorage.setItem(HELD_KEY, '2')
      const wrapper = await mountPage()
      await answerReload(wrapper, QUESTIONS())

      await wrapper.get('[data-testid="held-fish"] button').trigger('click')

      expect(panel(wrapper).exists()).toBe(true)
    })

    it('第一題送出中：「好了」停用、按 Esc 也不關，成功後照樣收進圖鑑（review 第 1 項）', async () => {
      const wrapper = await mountPage()
      await catchFirstFish(wrapper)
      await beachButton(wrapper, '記下來').trigger('click')
      await panelButton(wrapper, '魚K').trigger('click')
      const pending = inertia.formPosts.at(-1)
      await flushPromises()

      expect(panelButton(wrapper, '好了').attributes('disabled')).toBeDefined()
      await panel(wrapper).trigger('keydown', { key: 'Escape' })
      expect(panel(wrapper).exists()).toBe(true)

      pending.options.onSuccess()
      await flushPromises()
      expect(lastCollectCall()[0]).toBe('/island/collection/1')
    })

    it('收進圖鑑送出中：「好了」停用；結束後才能關（review 第 1 項）', async () => {
      const wrapper = await mountPage()
      await catchFirstFish(wrapper)
      await beachButton(wrapper, '記下來').trigger('click')
      await panelButton(wrapper, '魚K').trigger('click')
      inertia.formPosts.at(-1).options.onSuccess()
      await flushPromises()

      expect(panelButton(wrapper, '好了').attributes('disabled')).toBeDefined()

      lastCollectCall()[2].onFinish()
      await flushPromises()
      expect(panelButton(wrapper, '好了').attributes('disabled')).toBeUndefined()
    })

    it('寫入後重新拿到的魚資料（照片）直接用在手上的魚與圖鑑，不用重新整理（review 第 2 項）', async () => {
      window.localStorage.setItem(HELD_KEY, '2')
      const wrapper = await mountPage({ collection: [{ fish_id: 2, number: 1, my: {} }] })

      await wrapper.setProps({
        island_fish: ISLAND_FISH.map((fish) =>
          fish.id === 2 ? { ...fish, image_url: '/a2.jpg' } : fish
        ),
      })
      await wrapper.get('[data-testid="open-collection"]').trigger('click')

      expect(wrapper.get('[data-testid="held-fish"] img').attributes('src')).toBe('/a2.jpg')
      expect(wrapper.get('[data-testid="collection-card"] img').attributes('src')).toBe('/a2.jpg')
    })

    describe('我的圖鑑', () => {
      const COLLECTION = [
        {
          fish_id: 14,
          number: 1,
          my: { name: null, food_category: 'rahet', processing_method: null },
        },
        {
          fish_id: 2,
          number: 2,
          my: { name: 'arayo', food_category: 'oyod', processing_method: '去魚鱗' },
        },
      ]
      const book = (wrapper) => wrapper.find('[data-testid="collection"]')

      it('按鈕寫已收集幾條；打開後「已收集 X 條」，依編號列第 N 條、照片、自己記的三題（沒記寫「還沒記」）', async () => {
        const wrapper = await mountPage({ collection: COLLECTION })

        await wrapper.get('[data-testid="open-collection"]').trigger('click')

        expect(book(wrapper).text()).toContain('已收集 2 條')
        const cards = book(wrapper).findAll('[data-testid="collection-card"]')
        expect(cards.map((card) => card.get('img').attributes('src'))).toEqual(['/b.jpg', '/a.jpg'])
        expect(cards[0].text()).toContain('第 1 條')
        expect(cards[0].text()).toContain('叫法還沒記')
        expect(cards[0].text()).toContain('分類rahet')
        expect(cards[1].text()).toContain('處理去魚鱗')
      })

      it('圖鑑是空的：「還沒有魚，去海邊找一條」', async () => {
        const wrapper = await mountPage()

        await wrapper.get('[data-testid="open-collection"]').trigger('click')

        expect(book(wrapper).text()).toContain('已收集 0 條')
        expect(book(wrapper).text()).toContain('還沒有魚，去海邊找一條')
      })

      it('「改我的記錄」載入那條魚的記錄並打開記錄視窗', async () => {
        const wrapper = await mountPage({ collection: COLLECTION })
        await wrapper.get('[data-testid="open-collection"]').trigger('click')

        await book(wrapper)
          .findAll('[data-testid="collection-card"]')[1]
          .get('button')
          .trigger('click')

        expect(inertia.reload).toHaveBeenLastCalledWith(
          expect.objectContaining({ data: { record_fish: 2 } })
        )
        expect(panel(wrapper).exists()).toBe(true)
      })

      it('「關閉」關掉圖鑑', async () => {
        const wrapper = await mountPage({ collection: COLLECTION })
        await wrapper.get('[data-testid="open-collection"]').trigger('click')

        await book(wrapper)
          .findAll('button')
          .find((b) => b.text() === '關閉')
          .trigger('click')

        expect(book(wrapper).exists()).toBe(false)
      })
    })
  })
  describe('#232 拿著魚問家人', () => {
    const HELD_KEY = 'island.heldFish.7'
    const YAYO_SPEECH = '這是 魚A。我們 yayo 多數人說它是 oyod；多數人說抓回來剝皮吃。'
    // 魚A 在 yayo（角色自己的部落）有說法：分類 oyod 3 人、處理方式剝皮 2 人
    const FISH_WITH_YAYO = ISLAND_FISH.map((fish) =>
      fish.id === 2
        ? {
            ...fish,
            results: {
              food_category: {
                yayo: [
                  { value: 'oyod', count: 3 },
                  { value: 'rahet', count: 1 },
                ],
              },
              processing_method: { yayo: [{ value: '剝皮', count: 2 }] },
            },
          }
        : fish
    )
    const SPEECH = {
      ...FISH_SPEECH,
      yayo: {
        1: { speech: '魚K 的話', has_detail: false },
        2: { speech: YAYO_SPEECH, has_detail: true },
        14: { speech: '無名的話', has_detail: false },
      },
    }
    const mountAsking = (overrides = {}) =>
      mountBasePage({
        spots: { iratay: tribeSpots(), ivalino: tribeSpots(), yayo: tribeSpots() },
        island_fish: FISH_WITH_YAYO,
        fish_speech: SPEECH,
        collection: COLLECTION_A_B,
        ...overrides,
      })
    const photos = (wrapper) => dialog(wrapper).findAll('[data-testid="fish-photo"]')
    const npcLine = (wrapper) => dialog(wrapper).get('[data-testid="npc-line"]').text()
    /** 走到某部落第 1 戶的 pananadengan，按到 ③「你帶了什麼魚來？」 */
    const openPhotos = async (tribe) => {
      await reachSpot({ tribe, hex: 0, slot: 1 })
      await pressSpace()
      await pressSpace()
      await pressSpace()
    }
    const askFish = async (wrapper, index) => {
      await photos(wrapper)[index].trigger('click')
      await pressSpace()
    }
    const cells = (wrapper, question) =>
      dialog(wrapper)
        .findAll(`[data-question="${question}"] td`)
        .map((td) => td.text())
    const dialogButton = (wrapper, label) =>
      dialog(wrapper)
        .findAll('button')
        .find((b) => b.text() === label)

    it('③「你帶了什麼魚來？」：手上的魚排第一並標「手上」，接著圖鑑；手上的魚已在圖鑑裡只出現一次', async () => {
      window.localStorage.setItem(HELD_KEY, '14')
      const wrapper = await mountAsking()

      await openPhotos('iratay')

      expect(npcLine(wrapper)).toBe('你帶了什麼魚來？')
      expect(photos(wrapper).map((p) => p.get('img').attributes('src'))).toEqual([
        '/b.jpg',
        '/a.jpg',
      ])
      expect(photos(wrapper)[0].find('[data-testid="held-tag"]').exists()).toBe(true)
      expect(photos(wrapper)[1].find('[data-testid="held-tag"]').exists()).toBe(false)
    })

    it('手上的魚不在圖鑑裡：排在圖鑑前面', async () => {
      window.localStorage.setItem(HELD_KEY, '1')
      const wrapper = await mountAsking()

      await openPhotos('iratay')

      expect(photos(wrapper).map((p) => p.get('img').attributes('src'))).toEqual([
        '/k.jpg',
        '/a.jpg',
        '/b.jpg',
      ])
    })

    it('這個部落有說法：家人的話＋並排「你記的／這裡多數說」，沒記寫「還沒記」、多數平手全列', async () => {
      const wrapper = await mountAsking({
        collection: [
          { fish_id: 2, number: 1, my: { food_category: 'oyod', processing_method: null } },
        ],
      })

      await openPhotos('yayo')
      await askFish(wrapper, 0)

      expect(npcLine(wrapper)).toBe(YAYO_SPEECH)
      expect(cells(wrapper, 'food_category')).toEqual(['oyod', 'oyod'])
      expect(cells(wrapper, 'processing_method')).toEqual(['還沒記', '剝皮'])
    })

    it('這一題這個部落沒有有效回答：「這裡多數說」寫「還沒有人說」、不出現「改成…」', async () => {
      const fish = FISH_WITH_YAYO.map((item) =>
        item.id === 2
          ? {
              ...item,
              results: {
                food_category: { yayo: [{ value: 'oyod', count: 1 }] },
                processing_method: { yayo: [{ value: '我不知道', count: 4 }] },
              },
            }
          : item
      )
      const wrapper = await mountAsking({
        island_fish: fish,
        collection: [
          { fish_id: 2, number: 1, my: { food_category: 'oyod', processing_method: '去魚鱗' } },
        ],
      })

      await openPhotos('yayo')
      await askFish(wrapper, 0)

      expect(cells(wrapper, 'processing_method')).toEqual(['去魚鱗', '還沒有人說'])
      expect(dialog(wrapper).find('[data-testid="change-processing_method"]').exists()).toBe(false)
    })

    it('在自己的部落、自己記的不在多數裡：「改成 {選項}」把自己的記錄改成多數；「維持我的」收起來', async () => {
      const wrapper = await mountAsking({
        collection: [
          { fish_id: 2, number: 1, my: { food_category: 'rahet', processing_method: '去魚鱗' } },
        ],
      })
      await openPhotos('yayo')
      await askFish(wrapper, 0)

      await dialogButton(wrapper, '改成 oyod').trigger('click')
      expect(inertia.post).toHaveBeenLastCalledWith(
        '/island/tribes/yayo/fish/2/answers',
        { question: 'food_category', value: 'oyod' },
        expect.objectContaining({
          only: ['record_questions', 'island_fish', 'fish_speech', 'collection'],
        })
      )

      dialog(wrapper)
        .get('[data-testid="change-processing_method"]')
        .findAll('button')
        .at(-1)
        .trigger('click')
      await flushPromises()
      expect(dialog(wrapper).find('[data-testid="change-processing_method"]').exists()).toBe(false)
    })

    it('「改成…」被限流：顯示還要等幾秒並倒數，倒數期間「改成…」停用（review 第 1 項）', async () => {
      const wrapper = await mountAsking({
        collection: [{ fish_id: 2, number: 1, my: { food_category: 'rahet' } }],
      })
      await openPhotos('yayo')
      await askFish(wrapper, 0)
      await dialogButton(wrapper, '改成 oyod').trigger('click')

      const event = {
        detail: { response: { status: 429, headers: { 'retry-after': '45' } } },
        preventDefault: vi.fn(),
      }
      inertia.handlers.filter((h) => h.name === 'invalid').forEach((h) => h.handler(event))
      inertia.post.mock.calls.at(-1)[2].onFinish()
      await flushPromises()

      expect(dialog(wrapper).get('[data-testid="change-notice"]').text()).toBe(
        '送出太多次了，請等 45 秒再試'
      )
      expect(dialogButton(wrapper, '改成 oyod').attributes('disabled')).toBeDefined()
    })

    it('「改成…」被拒：顯示伺服器的錯誤訊息（review 第 1 項）', async () => {
      const wrapper = await mountAsking({
        collection: [{ fish_id: 2, number: 1, my: { food_category: 'rahet' } }],
      })
      await openPhotos('yayo')
      await askFish(wrapper, 0)
      await dialogButton(wrapper, '改成 oyod').trigger('click')

      const options = inertia.post.mock.calls.at(-1)[2]
      options.onError({ value: '請選一個選項' })
      options.onFinish()
      await flushPromises()

      expect(dialog(wrapper).get('[data-testid="change-notice"]').text()).toBe('請選一個選項')
    })

    it('手上的魚自己部落已經有回答、還沒收進圖鑑：問自己部落時不出現「記下來」（review 第 2 項）', async () => {
      window.localStorage.setItem(HELD_KEY, '1')
      const wrapper = await mountAsking()
      const options = inertia.reload.mock.calls.at(-1)[0]
      await wrapper.setProps({
        record_questions: [
          { key: 'name', options: [], my_answer: null },
          { key: 'food_category', options: [], my_answer: 'oyod' },
          { key: 'processing_method', options: [], my_answer: null },
        ],
      })
      options.onSuccess()
      await flushPromises()

      await openPhotos('yayo')
      await askFish(wrapper, 0)

      expect(dialog(wrapper).find('[data-testid="record-asked"]').exists()).toBe(false)
    })

    it('在別的部落：有說法也不出現「改成…」（只改自己部落的記錄）', async () => {
      const speech = { ...SPEECH, iratay: { ...SPEECH.iratay } }
      const fish = FISH_WITH_YAYO.map((item) =>
        item.id === 2
          ? {
              ...item,
              results: {
                food_category: { iratay: [{ value: 'oyod', count: 2 }] },
                processing_method: {},
              },
            }
          : item
      )
      const wrapper = await mountAsking({
        fish_speech: speech,
        island_fish: fish,
        collection: [{ fish_id: 2, number: 1, my: { food_category: 'rahet' } }],
      })

      await openPhotos('iratay')
      await askFish(wrapper, 0)

      expect(cells(wrapper, 'food_category')).toEqual(['rahet', 'oyod'])
      expect(dialog(wrapper).find('[data-testid="change-food_category"]').exists()).toBe(false)
    })

    it('這個部落沒有、別的部落有：說不知道並指引那些部落；不出現並排', async () => {
      const wrapper = await mountAsking()

      await openPhotos('ivalino')
      await askFish(wrapper, 0)

      expect(npcLine(wrapper)).toBe('我不知道這條魚。你去問 iratay（漁人）、yayo（椰油）的人看看。')
      expect(dialog(wrapper).find('[data-testid="comparison"]').exists()).toBe(false)
    })

    it('全島都沒有：說全島還沒有人說過', async () => {
      const wrapper = await mountAsking()

      await openPhotos('yayo')
      await askFish(wrapper, 1)

      expect(npcLine(wrapper)).toBe(NOWHERE_LINE)
    })

    it('在自己的部落問手上還沒收進圖鑑的魚：「記下來」打開記錄視窗；別的部落沒有', async () => {
      window.localStorage.setItem(HELD_KEY, '1')
      const wrapper = await mountAsking()
      await openPhotos('yayo')
      await askFish(wrapper, 0)

      await dialog(wrapper).get('[data-testid="record-asked"]').trigger('click')
      expect(wrapper.find('[data-testid="record-panel"]').exists()).toBe(true)
      expect(inertia.reload).toHaveBeenLastCalledWith(
        expect.objectContaining({ data: { record_fish: 1 } })
      )
    })

    it('「好」關掉對話框', async () => {
      const wrapper = await mountAsking()
      await openPhotos('yayo')
      await askFish(wrapper, 0)

      await dialog(wrapper).get('[data-testid="answer-done"]').trigger('click')

      expect(dialog(wrapper).exists()).toBe(false)
    })
  })
})

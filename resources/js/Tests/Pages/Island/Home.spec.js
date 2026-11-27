import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
  Head: { render: () => null },
  Link: {
    props: ['href'],
    template: '<a :href="href"><slot /></a>',
  },
}))

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
/** 一個部落的 12 個地點，$filled 是 { 第幾筆: [魚] } */
const tribeSpotFish = (filled) => Array.from({ length: 12 }, (_, index) => filled[index] ?? [])
const SPOT_FISH = {
  iratay: tribeSpotFish({
    0: [
      {
        id: 1,
        speech: '這是 魚K。我們 iratay 還沒有分類的答案；怎麼處理還沒有答案。',
        has_detail: false,
      },
    ],
    1: [
      { id: 2, speech: SPEECH_A_IRATAY, has_detail: true },
      { id: 14, speech: SPEECH_UNNAMED_IRATAY, has_detail: false },
    ],
  }),
  ivalino: tribeSpotFish({ 1: [{ id: 2, speech: SPEECH_A_IVALINO, has_detail: false }] }),
}

// 每個測試結束時拆掉頁面，免得前一個頁面的鍵盤監聽留在 window 上
const mounted = []

const mountPage = async () => {
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
      spot_fish: SPOT_FISH,
      result_options: {
        food_category: ['oyod', 'rahet', 'kakanen no rarakeh'],
        processing_method: ['去魚鱗', '不去魚鱗', '剝皮', '不食用', '我不知道'],
      },
      unknown_processing_method: '我不知道',
    },
  })
  mounted.push(wrapper)
  await flushPromises()
  return wrapper
}

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
    scene.createIslandScene.mockReset().mockReturnValue({ dispose: scene.dispose })
    scene.dispose.mockReset()
    window.history.replaceState({}, '', '/island')
  })

  afterEach(() => {
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

  it('#207 3D 畫面下方標示地圖資料的來源（OpenStreetMap 的授權要求）', async () => {
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="map-credit"]').text()).toBe(
      '地圖資料 © OpenStreetMap contributors'
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
  /** 按空白鍵（預設送到 window）；回傳事件，看有沒有擋掉瀏覽器原本的行為（捲動網頁） */
  const pressSpace = async (target = window) => {
    const event = new KeyboardEvent('keydown', { key: ' ', cancelable: true, bubbles: true })
    target.dispatchEvent(event)
    await flushPromises()
    return event
  }
  /** 點了目前範圍那一件的家人（3D 畫面回報） */
  const tapMember = async () => {
    sceneOptions().onMemberTap()
    await flushPromises()
  }
  const prompt = (wrapper) => wrapper.find('[data-testid="spot-prompt"]')
  const dialog = (wrapper) => wrapper.find('[data-testid="spot-dialog"]')
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
      expect(dialog(wrapper).text()).toContain('kaka kong')

      await tapMember()
      expect(dialog(wrapper).exists()).toBe(false)
    })

    it('點提示橫條打開對話框，點對話框一句一句往下，到最後一句再點就關掉（#216 規格修訂 1）', async () => {
      const wrapper = await mountPage()
      await reachSpot({ tribe: 'iratay', hex: 2, slot: 3 })

      await prompt(wrapper).trigger('click')
      expect(dialog(wrapper).text()).toContain('kaka kong')

      // 這一件（iratay 第 3 戶 tagakal）沒有魚：① 問好 → ② 你好。這裡還沒有魚。→ 關掉
      await dialog(wrapper).trigger('click')
      expect(dialog(wrapper).exists()).toBe(true)
      await dialog(wrapper).trigger('click')
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
    /** 打開對話框並按到 ③「你想認識哪一條魚？」＋照片 */
    const openToPhotos = async (spot) => {
      const wrapper = await openAt(spot)
      await pressSpace()
      await pressSpace()
      return wrapper
    }
    /** 點照片（④ ikongo o ya?）再按一下到 ⑤ 家人講那條魚 */
    const pick = async (wrapper, index) => {
      await photos(wrapper)[index].trigger('click')
      await flushPromises()
      await pressSpace()
    }

    it('一句一句來：① 只有玩家的問好語 → ② 只有家人「阿海，你好」→ ③ 家人「你想認識哪一條魚？」才出現照片；句子右下角提示「按空白鍵」', async () => {
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
      expect(npcLine(wrapper)).toBe('你想認識哪一條魚？')
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

      await reachSpot({ tribe: 'iratay', hex: 0, slot: 2 })
      await pressSpace()
      await pressSpace()
      expect(npcLine(wrapper)).toBe('阿海，你好。這裡還沒有魚。')
      expect(hint(wrapper)).toBe('按空白鍵')
    })

    it('觸控裝置：③、⑤ 與沒有魚的 ② 的提示寫「點一下」', async () => {
      mockTouch(true)
      const hint = (wrapper) => dialog(wrapper).get('[data-testid="next-hint"]').text()
      const wrapper = await openToPhotos()
      expect(hint(wrapper)).toBe('點一下')

      await pick(wrapper, 0)
      expect(hint(wrapper)).toBe('點一下')

      await reachSpot({ tribe: 'iratay', hex: 0, slot: 2 })
      await pressSpace()
      await pressSpace()
      expect(hint(wrapper)).toBe('點一下')
    })

    it('觸控裝置：提示寫「點一下」，點對話框就是下一句', async () => {
      mockTouch(true)
      const wrapper = await openAt()
      expect(dialog(wrapper).get('[data-testid="next-hint"]').text()).toBe('點一下')

      await dialog(wrapper).trigger('click')
      expect(npcLine(wrapper)).toBe('阿海，你好')
      await dialog(wrapper).trigger('click')
      expect(npcLine(wrapper)).toBe('你想認識哪一條魚？')
    })

    it('點照片：④ 玩家「ikongo o ya?」＋剛才選的那條魚的照片（規格修訂 2），再按一下 ⑤ 家人才講那條魚', async () => {
      const wrapper = await openToPhotos()

      await photos(wrapper)[1].trigger('click')
      await flushPromises()
      expect(playerLine(wrapper).text()).toBe('ikongo o ya?')
      expect(dialog(wrapper).get('[data-testid="asked-photo"]').attributes('src')).toBe('/b.jpg')
      expect(dialog(wrapper).find('[data-testid="npc-line"]').exists()).toBe(false)
      expect(photos(wrapper)).toHaveLength(0)

      await dialog(wrapper).trigger('click')
      expect(npcLine(wrapper)).toBe(SPEECH_UNNAMED_IRATAY)
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

    it('照片上不寫魚名，無障礙名稱是「第 n 條魚」，依地點清單的順序', async () => {
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

    it('vahay 用 kaminan 的問好語，列的是 vahay 的魚', async () => {
      const wrapper = await openAt({ tribe: 'iratay', hex: 0, slot: 0 })
      expect(playerLine(wrapper).text()).toBe('kaminan kong')

      await pressSpace()
      await pressSpace()
      expect(photos(wrapper).map((photo) => photo.get('img').attributes('src'))).toEqual(['/k.jpg'])
    })

    it('這個地點沒有魚：② 家人說「阿海，你好。這裡還沒有魚。」，不列照片；再按就關掉', async () => {
      const wrapper = await openAt({ tribe: 'iratay', hex: 0, slot: 2 })

      await pressSpace()
      expect(npcLine(wrapper)).toBe('阿海，你好。這裡還沒有魚。')
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

      expect(npcLine(wrapper)).toBe(SPEECH_UNNAMED_IRATAY)
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
      await flushPromises()

      expect(npcLine(wrapper)).toBe('你想認識哪一條魚？')
      expect(photos(wrapper)[0].text()).toContain('聽過了')
      expect(photos(wrapper)[1].text()).not.toContain('聽過了')
    })

    it('關掉再打開：從 ① 重新開始，「聽過了」保留；走到另一個部落同一件：列那個部落的魚、說那個部落的話，「聽過了」分開記', async () => {
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
      expect(photos(wrapper)).toHaveLength(1)
      expect(photos(wrapper)[0].text()).not.toContain('聽過了')
      await pick(wrapper, 0)
      expect(npcLine(wrapper)).toBe(SPEECH_A_IVALINO)
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

      await dialog(wrapper).trigger('click')
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
})

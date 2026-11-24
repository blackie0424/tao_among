import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import FishAudioList from '@/Pages/FishAudioList.vue'
import EditAudio from '@/Pages/Fish/EditAudio.vue'
import AudioClipRow from '@/Components/FishAudio/AudioClipRow.vue'
import { router } from '@inertiajs/vue3'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
  router: { put: vi.fn(), delete: vi.fn(), visit: vi.fn() },
}))
vi.mock('@/Components/UI/Volume.vue', () => ({ default: { template: '<span data-testid="volume" />' } }))
vi.mock('@/utils/fishListCache', () => ({ markFishStale: vi.fn() }))

const Layout = { props: ['mobileBackUrl', 'mobileBackText'], template: '<main><a data-testid="back" :href="mobileBackUrl">{{ mobileBackText }}</a><slot /></main>' }
const stubs = { FishAppLayout: Layout, Volume: true }
let wrapper
afterEach(() => { wrapper?.unmount(); vi.clearAllMocks() })

const TRIBE_LIST = ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley']
const fish = { id: 4, name: 'mivazlt、mivanivang', audio_filename: 'a1.m4a' }
const audio = (over = {}) => ({ id: 1, name: 'a1.m4a', url: 'https://example.test/a1.m4a', locate: 'iraraley', duration: 4000, recorder_name: '甲', ...over })

describe('AudioClipRow 顯示值的意思', () => {
  const row = (a, extra = {}) => mount(AudioClipRow, { props: { audio: a, fishId: 4, tribes: TRIBE_LIST, ...extra }, global: { stubs: { Volume: true, Link: { props: ['href'], template: '<a :href="href"><slot /></a>' } } } })

  it('部落、長度、錄音者照資料顯示', () => {
    wrapper = row(audio())
    expect(wrapper.text()).toContain('iraraley')
    expect(wrapper.text()).toContain('0:04')
    expect(wrapper.text()).toContain('錄音者：甲')
  })

  it('部落為空顯示「未標部落」；錄音者為空顯示「未記錄」', () => {
    wrapper = row(audio({ locate: null, recorder_name: null }))
    expect(wrapper.text()).toContain('未標部落')
    expect(wrapper.text()).toContain('錄音者：未記錄')
  })

  it('不在六個部落內的值（unknown、supabase、檔名、其他字串）一律顯示「未標部落」，不當成部落顯示', () => {
    for (const locate of ['unknown', 'supabase', 'abc-123.m4a', '某個未知字串', '']) {
      wrapper = row(audio({ locate }))
      expect(wrapper.text()).toContain('未標部落')
      expect(wrapper.text()).not.toContain(locate === '' ? '__none__' : locate)
      wrapper.unmount()
    }
  })

  it('長度為 0 或空時不顯示長度（不顯示 0:00）', () => {
    for (const duration of [0, null]) {
      wrapper = row(audio({ duration }))
      expect(wrapper.text()).not.toContain('0:00')
      expect(wrapper.text()).toContain('錄音者：甲')
      wrapper.unmount()
    }
  })

  it('超過一分鐘照分秒顯示', () => {
    wrapper = row(audio({ duration: 65_000 }))
    expect(wrapper.text()).toContain('1:05')
  })

  it('魚頁目前使用的那一段有標示', () => {
    wrapper = row(audio(), { isCurrent: true })
    expect(wrapper.text()).toContain('魚頁目前使用')
  })

  it('編輯連結帶這段錄音的編號', () => {
    wrapper = row(audio({ id: 7 }))
    expect(wrapper.get('a[href="/fish/4/audio/7/edit"]').text()).toBe('編輯')
  })
})

describe('管理發音頁', () => {
  const mountList = (props = {}) => {
    wrapper = mount(FishAudioList, {
      props: {
        fish,
        audioGroups: [
          { name: 'mivazlt', audios: [audio(), audio({ id: 2, name: 'a2.m4a', recorder_name: '乙' })] },
          { name: 'mivanivang', audios: [] },
        ],
        unassignedAudios: [],
        tribes: TRIBE_LIST,
        ...props,
      },
      global: { stubs },
    })
    return wrapper
  }

  it('標題「{魚名}・管理發音」；返回「魚類資料」並回到發音分頁', () => {
    mountList()
    expect(wrapper.get('h1').text()).toBe('mivazlt、mivanivang・管理發音')
    expect(wrapper.get('[data-testid="back"]').attributes('href')).toBe('/fish/4?tab=audio')
    expect(wrapper.get('[data-testid="back"]').text()).toBe('魚類資料')
  })

  it('依名稱分組：每個名稱有「＋ 錄音」連結帶名稱，同名稱的各段列在下面', () => {
    mountList()
    const group = wrapper.get('[data-testid="name-group-mivazlt"]')
    expect(group.get('a[href^="/fish/4/audio/create?name="]').attributes('href')).toBe('/fish/4/audio/create?name=mivazlt')
    expect(group.findAll('[data-testid="audio-clip"]')).toHaveLength(2)
    expect(group.text()).toContain('錄音者：乙')
  })

  it('沒有錄音的名稱顯示「還沒有錄音」並仍有「＋ 錄音」', () => {
    mountList()
    const group = wrapper.get('[data-testid="name-group-mivanivang"]')
    expect(group.text()).toContain('還沒有錄音')
    expect(group.text()).toContain('＋ 錄音')
  })

  describe('每個拼字各自的主要發音（設為魚頁使用）', () => {
    const clipsOf = (name) => wrapper.get(`[data-testid="name-group-${name}"]`).findAll('[data-testid="audio-clip"]')
    const setButton = (clip) => clip.findAll('button').find((b) => b.text() === '設為魚頁使用')

    it('一個拼字只有一段錄音：不顯示「魚頁目前使用」也不顯示「設為魚頁使用」', () => {
      mountList({
        audioGroups: [
          { name: 'Ivey', audios: [audio({ id: 1 })] },
          { name: 'Ivay', audios: [audio({ id: 2, name: 'a2.m4a' })] },
        ],
      })
      expect(wrapper.text()).not.toContain('魚頁目前使用')
      expect(wrapper.text()).not.toContain('設為魚頁使用')
    })

    it('一個拼字有兩段以上：沒指定時最早的一段是魚頁使用，其他段有按鈕；按了指定那一段', async () => {
      mountList({
        audioGroups: [{ name: 'Ivey', audios: [audio({ id: 1 }), audio({ id: 4, name: 'a4.m4a' }), audio({ id: 6, name: 'a6.m4a' })] }],
      })
      const clips = clipsOf('Ivey')
      expect(clips[0].text()).toContain('魚頁目前使用')
      expect(setButton(clips[0])).toBeUndefined()
      expect(clips[1].text()).not.toContain('魚頁目前使用')

      await setButton(clips[2]).trigger('click')

      expect(router.put).toHaveBeenCalledWith('/fish/4/audio/6/set-base')
    })

    it('有指定的主要發音（is_primary）就標示它，原本最早的一段變成可以指定', () => {
      mountList({
        audioGroups: [{ name: 'Ivey', audios: [audio({ id: 1 }), audio({ id: 4, name: 'a4.m4a', is_primary: true })] }],
      })
      const clips = clipsOf('Ivey')
      expect(clips[0].text()).not.toContain('魚頁目前使用')
      expect(setButton(clips[0])).toBeTruthy()
      expect(clips[1].text()).toContain('魚頁目前使用')
      expect(setButton(clips[1])).toBeUndefined()
    })

    it('只有一個拼字有多段：只有那個拼字有指定功能，只有一段的拼字沒有', () => {
      mountList({
        audioGroups: [
          { name: 'Ivey', audios: [audio({ id: 1 }), audio({ id: 4, name: 'a4.m4a' })] },
          { name: 'Ivay', audios: [audio({ id: 2, name: 'a2.m4a' })] },
        ],
      })
      expect(wrapper.get('[data-testid="name-group-Ivey"]').text()).toContain('設為魚頁使用')
      expect(wrapper.get('[data-testid="name-group-Ivay"]').text()).not.toContain('設為魚頁使用')
      expect(wrapper.get('[data-testid="name-group-Ivay"]').text()).not.toContain('魚頁目前使用')
    })

    it('好幾個拼字都有多段：各拼字各自判斷、各自標示一段', () => {
      mountList({
        audioGroups: [
          { name: 'Ivey', audios: [audio({ id: 1 }), audio({ id: 4, name: 'a4.m4a', is_primary: true })] },
          { name: 'Ivay', audios: [audio({ id: 2, name: 'a2.m4a' }), audio({ id: 5, name: 'a5.m4a' })] },
        ],
      })
      expect(wrapper.findAll('[data-testid="audio-clip"]').filter((c) => c.text().includes('魚頁目前使用'))).toHaveLength(2)
      expect(clipsOf('Ivey')[1].text()).toContain('魚頁目前使用') // 有指定的
      expect(clipsOf('Ivay')[0].text()).toContain('魚頁目前使用') // 沒指定：最早的一段
    })

    it('未對應名稱的錄音不顯示「設為魚頁使用」（要先指派名稱）', () => {
      mountList({ unassignedAudios: [audio({ id: 9, name: 'old.m4a' })] })
      expect(wrapper.get('[data-testid="unassigned"]').text()).not.toContain('設為魚頁使用')
      expect(wrapper.get('[data-testid="unassigned"]').text()).not.toContain('魚頁目前使用')
    })
  })

  it('沒有未對應的錄音時不顯示「未對應名稱」區', () => {
    mountList()
    expect(wrapper.find('[data-testid="unassigned"]').exists()).toBe(false)
  })

  it('未對應名稱的錄音可指派到某個名稱', async () => {
    mountList({ unassignedAudios: [audio({ id: 9, name: 'old.m4a', locate: null, recorder_name: null })] })
    const section = wrapper.get('[data-testid="unassigned"]')
    expect(section.text()).toContain('未標部落')
    expect(section.text()).toContain('錄音者：未記錄')
    const assignButton = section.findAll('button').find((b) => b.text() === '指派名稱')
    expect(assignButton.attributes('disabled')).toBeDefined() // 還沒選名稱不能按

    await section.get('select').setValue('mivanivang')
    await assignButton.trigger('click')

    expect(router.put).toHaveBeenCalledWith('/fish/4/audio/9/name', { name: 'mivanivang' })
  })

  it('未對應名稱的錄音仍有編輯入口（可換音檔、改部落、刪除）', () => {
    mountList({ unassignedAudios: [audio({ id: 9, name: 'old.m4a', locate: 'supabase', recorder_name: null })] })
    const section = wrapper.get('[data-testid="unassigned"]')
    expect(section.get('a[href="/fish/4/audio/9/edit"]').text()).toBe('編輯')
    expect(section.text()).toContain('未標部落')
    expect(section.text()).not.toContain('supabase')
  })

  it('魚名為空時提示先編輯名稱；既有的錄音仍可編輯（沒有名稱可指派）', () => {
    mountList({ fish: { id: 4, name: '' }, audioGroups: [], unassignedAudios: [audio({ id: 9, name: 'old.m4a' })] })
    expect(wrapper.text()).toContain('這隻魚還沒有名稱')
    expect(wrapper.get('a[href="/fish/4/edit"]').text()).toBe('編輯名稱')
    expect(wrapper.get('[data-testid="unassigned"] a[href="/fish/4/audio/9/edit"]').text()).toBe('編輯')
    expect(wrapper.get('[data-testid="unassigned"] select').findAll('option')).toHaveLength(1) // 只有「選名稱」，沒有可指派的名稱
  })
})

describe('編輯發音頁', () => {
  const TRIBES = ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley']
  const mountEdit = (over = {}) => {
    wrapper = mount(EditAudio, { props: { fish, audio: audio({ spoken_name: 'mivazlt' }), tribes: TRIBES, maxSeconds: 5, ...over }, global: { stubs } })
    return wrapper
  }

  it('標題「{名稱}・編輯發音」，返回管理發音頁；沒有名稱欄', () => {
    mountEdit()
    expect(wrapper.get('h1').text()).toBe('mivazlt・編輯發音')
    expect(wrapper.get('[data-testid="back"]').attributes('href')).toBe('/fish/4/audio-list')
    expect(wrapper.find('input[type="text"]').exists()).toBe(false)
  })

  it('目前的部落已選取；只改部落時只送部落', async () => {
    mountEdit()
    expect(wrapper.get('[data-tribe="iraraley"]').attributes('aria-checked')).toBe('true')

    await wrapper.get('[data-tribe="yayo"]').trigger('click')
    await wrapper.get('form').trigger('submit')

    expect(router.put).toHaveBeenCalledWith('/fish/4/audio/1', { tribe: 'yayo' }, expect.any(Object))
  })

  it('舊資料沒有部落時不預選，沒選部落不能存檔', async () => {
    mountEdit({ audio: audio({ locate: null }) })
    expect(wrapper.findAll('[aria-checked="true"]')).toHaveLength(0)

    await wrapper.get('form').trigger('submit')

    expect(wrapper.text()).toContain('請選擇部落')
    expect(router.put).not.toHaveBeenCalled()
  })

  it('最下方「危險操作」有紅色細框「刪除這段發音」，確認後才刪除', async () => {
    mountEdit()
    const del = wrapper.findAll('button').find((b) => b.text() === '刪除這段發音')
    expect(wrapper.text()).toContain('危險操作')
    expect(del.classes()).toContain('border-red-600')

    vi.spyOn(window, 'confirm').mockReturnValueOnce(false)
    await del.trigger('click')
    expect(router.delete).not.toHaveBeenCalled()

    vi.spyOn(window, 'confirm').mockReturnValueOnce(true)
    await del.trigger('click')
    expect(router.delete).toHaveBeenCalledWith('/fish/4/audio/1')
  })
})

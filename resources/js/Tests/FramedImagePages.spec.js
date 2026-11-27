import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import SearchResults from '@/Components/UI/SearchResults.vue'
import EditorHome from '@/Pages/EditorHome.vue'
import ManageTribalClassification from '@/Pages/ManageTribalClassification.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<span />' },
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
  router: { get: vi.fn(), post: vi.fn(), visit: vi.fn() },
  usePage: () => ({ props: { auth: { user: { role: 'editor' } } } }),
}))
vi.mock('@/Components/UI/FramedImage.vue', () => ({
  default: { name: 'FramedImage', props: ['src', 'alt', 'framing'], template: '<img :src="src" />' },
}))
vi.mock('@/Components/Global/FormActionBar.vue', () => ({ default: { template: '<div />' } }))
vi.mock('@/Components/Layout/FishAppLayout.vue', () => ({ default: { template: '<div><slot /></div>' } }))
vi.mock('@/utils/fishListCache', () => ({ markFishStale: vi.fn() }))

const framing = { x: 30, y: 60, zoom: 1.5, rotation: 90 }
const framedProps = (wrapper) => wrapper.findAllComponents({ name: 'FramedImage' }).map((c) => c.props())

describe('搜尋結果（#218）', () => {
  it('顯示圖鑑主圖與同一張圖的調整值；沒有主圖時回退魚本身、framing 是空的', () => {
    const results = [
      { id: 1, name: '飛魚', image_url: '/own.jpg', display_image_url: '/main.jpg', display_image_framing: framing, tribal_classifications: [] },
      { id: 2, name: '鬼頭刀', image_url: '/own2.jpg', display_image_url: '/own2.jpg', display_image_framing: null, tribal_classifications: [] },
    ]
    const shown = framedProps(mount(SearchResults, { props: { results } }))

    expect(shown[0]).toMatchObject({ src: '/main.jpg', framing })
    expect(shown[1]).toMatchObject({ src: '/own2.jpg', framing: null })
  })
})

describe('編輯者首頁（#218）', () => {
  it('待補錄音與最近編輯的魚都用主圖與調整值', () => {
    const fish = { id: 1, name: '飛魚', image_url: '/own.jpg', display_image_url: '/main.jpg', display_image_framing: framing }
    const wrapper = mount(EditorHome, { props: { needAudio: [fish], recentEdits: [fish] } })

    const shown = framedProps(wrapper)
    expect(shown).toHaveLength(2)
    shown.forEach((props) => expect(props).toMatchObject({ src: '/main.jpg', framing }))
  })
})

describe('部落分類管理頁（#218）', () => {
  it('魚圖用主圖與調整值', () => {
    const fish = { id: 1, name: '飛魚', image_url: '/own.jpg', display_image_url: '/main.jpg', display_image_framing: framing }
    const wrapper = mount(ManageTribalClassification, {
      props: { fish, tribes: ['ivalino'], foodCategories: ['可食用'], processingMethods: ['不吃'], classifications: [] },
    })

    expect(framedProps(wrapper)[0]).toMatchObject({ src: '/main.jpg', framing })
  })
})

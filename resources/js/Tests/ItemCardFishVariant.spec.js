import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import ItemCard from '@/Components/UI/ItemCard.vue'

vi.mock('@inertiajs/vue3', () => ({
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
  usePage: () => ({ props: {} }),
}))
vi.mock('@/Components/UI/Volume.vue', () => ({ default: { template: '<span />' } }))

const mountFish = (props) =>
  mount(ItemCard, { props: { href: '/fish/1', title: '飛魚', variant: 'fish', ...props } })

// 真實 LazyImage：每次圖片 error 會依序 fallback（響應式 webp → 原始 webp → 原格式），最後才失敗
const failImageUntilPlaceholder = async (wrapper) => {
  for (let i = 0; i < 6; i++) {
    const img = wrapper.find('img')
    if (!img.exists()) return
    await img.trigger('error')
    await nextTick()
  }
}

describe('ItemCard fish 版', () => {
  it('圖高 190px、不用卡片陰影，魚名宋體 22px 且下方有細線', () => {
    const wrapper = mountFish({ imageUrl: '/fish.jpg' })

    expect(wrapper.get('[data-testid="item-image-wrapper"]').classes()).toContain('h-[190px]')
    expect(wrapper.html()).not.toContain('shadow')
    const name = wrapper.get('[data-testid="item-title"]')
    expect(name.classes()).toEqual(expect.arrayContaining(['font-serif', 'text-elder-name']))
    expect(name.element.closest('.border-b')).not.toBeNull()
  })

  it.each([null, ''])('圖片為 %j 時顯示 190px 佔位區塊', (imageUrl) => {
    const wrapper = mountFish({ imageUrl })

    const placeholder = wrapper.get('[data-testid="item-image-placeholder"]')
    expect(placeholder.classes()).toEqual(expect.arrayContaining(['h-[190px]', 'bg-rule']))
    expect(wrapper.find('img').exists()).toBe(false)
  })

  it('真實 LazyImage 最終載入失敗：改顯示佔位區塊，標題與連結仍在', async () => {
    const wrapper = mountFish({ imageUrl: '/storage/images/fish.jpg' })
    expect(wrapper.find('img').exists()).toBe(true)

    await failImageUntilPlaceholder(wrapper)

    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.get('[data-testid="item-image-placeholder"]').classes()).toContain('h-[190px]')
    expect(wrapper.get('[data-testid="item-title"]').text()).toBe('飛魚')
    expect(wrapper.get('a').attributes('href')).toBe('/fish/1')
  })

  it('圖片網址換掉後重新顯示圖片', async () => {
    const wrapper = mountFish({ imageUrl: '/storage/images/a.jpg' })
    await failImageUntilPlaceholder(wrapper)
    expect(wrapper.find('img').exists()).toBe(false)

    await wrapper.setProps({ imageUrl: '/storage/images/b.jpg' })

    expect(wrapper.find('img').exists()).toBe(true)
  })
})

describe('ItemCard 預設版（主題頁等）維持原樣式', () => {
  it('170px、白底陰影卡片，圖片失敗不換成佔位區塊', () => {
    const wrapper = mount(ItemCard, {
      props: { href: '/item/1', title: '主題', imageUrl: '/a.jpg' },
    })

    expect(wrapper.get('[data-testid="item-image-wrapper"]').classes()).toContain('h-[170px]')
    expect(wrapper.html()).toContain('shadow-md')
  })
})

describe('ItemCard 需登入時攔截點擊（AC 7–9）', () => {
  const click = (wrapper) => {
    const event = new MouseEvent('click', { bubbles: true, cancelable: true })
    wrapper.get('a').element.dispatchEvent(event)
    return event
  }

  it('gated 時點擊被攔下（不導向詳細頁）並 emit blocked，帶出被點的連結', () => {
    const wrapper = mountFish({ imageUrl: '/a.jpg', gated: true })
    const event = click(wrapper)
    expect(event.defaultPrevented).toBe(true)
    expect(wrapper.emitted('blocked')).toHaveLength(1)
    expect(wrapper.emitted('blocked')[0][0]).toBe(wrapper.get('a').element)
  })

  it('不 gated（已開通）時不攔截、不 emit', () => {
    const wrapper = mountFish({ imageUrl: '/a.jpg' })
    const event = click(wrapper)
    expect(event.defaultPrevented).toBe(false)
    expect(wrapper.emitted('blocked')).toBeUndefined()
  })
})

import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import FishSearchModal from '@/Components/FishList/FishSearchModal.vue'

const mountModal = (extraProps = {}) =>
  mount(FishSearchModal, {
    props: {
      show: true,
      canAccessLocation: true,
      showAdvanced: true,
      filters: { tribe: '紅頭' },
      nameQuery: '飛魚',
      searchOptions: { tribes: ['紅頭'], dietaryClassifications: [], processingMethods: [] },
      ...extraProps,
    },
  })

// 取出視窗內帶有深色模式專用樣式（dark:）的元素
const darkStyled = (wrapper) =>
  wrapper.findAll('*').filter((el) => /(^|\s)dark:/.test(el.attributes('class') ?? ''))

describe('FishSearchModal 不跟隨深色模式（#142 AC 1）', () => {
  it('進階篩選全開：所有元素都沒有 dark: 樣式', () => {
    expect(darkStyled(mountModal())).toHaveLength(0)
  })

  it('只有名稱欄位（未開通帳號）：同樣沒有 dark: 樣式', () => {
    expect(darkStyled(mountModal({ showAdvanced: false }))).toHaveLength(0)
  })
})

describe('FishSearchModal 按鈕行為不變（#142 AC 2）', () => {
  it('「清除」清空表單並通知重置', async () => {
    const wrapper = mountModal()
    const clear = wrapper.findAll('button').find((b) => b.text() === '清除')

    await clear.trigger('click')

    expect(wrapper.emitted('update:nameQuery').at(-1)).toEqual([''])
    expect(wrapper.emitted('update:filters').at(-1)[0].tribe).toBe('')
    expect(wrapper.emitted('reset')).toHaveLength(1)
  })

  it('「搜尋」送出目前的名稱與篩選', async () => {
    const wrapper = mountModal()

    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('update:nameQuery').at(-1)).toEqual(['飛魚'])
    expect(wrapper.emitted('update:filters').at(-1)[0].tribe).toBe('紅頭')
    expect(wrapper.emitted('submit')).toHaveLength(1)
  })

  it('關閉鈕與點背景都會關閉視窗', async () => {
    const wrapper = mountModal()

    await wrapper.get('button[aria-label="關閉搜尋"]').trigger('click')
    await wrapper.get('div.fixed').trigger('click')

    expect(wrapper.emitted('update:show')).toEqual([[false], [false]])
  })
})

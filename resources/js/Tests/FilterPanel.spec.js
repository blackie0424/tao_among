import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import FilterPanel from '@/Components/FilterPanel.vue'

describe('FilterPanel', () => {
  let wrapper
  const defaultProps = {
    filters: {},
    tribes: ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'],
    foodCategories: ['oyod', 'rahet', '不分類', '不食用', '?'],
    processingMethods: ['去魚鱗', '不去魚鱗', '剝皮', '不食用', '?'],
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders correctly with all filter elements', () => {
    wrapper = mount(FilterPanel, {
      props: defaultProps,
    })

    expect(wrapper.find('#tribe-filter').exists()).toBe(true)
    expect(wrapper.find('#food-category-filter').exists()).toBe(true)
    expect(wrapper.find('#processing-method-filter').exists()).toBe(true)
    expect(wrapper.find('#location-search').exists()).toBe(true)
    expect(wrapper.find('#fish-name-search').exists()).toBe(true)
  })

  it('displays correct title', () => {
    wrapper = mount(FilterPanel, {
      props: defaultProps,
    })

    expect(wrapper.text()).toContain('搜尋篩選')
  })

  it('renders all tribe options', () => {
    wrapper = mount(FilterPanel, {
      props: defaultProps,
    })

    const tribeSelect = wrapper.find('#tribe-filter')
    const options = tribeSelect.findAll('option')

    expect(options).toHaveLength(7) // 6 tribes + 1 default option
    expect(options[0].text()).toBe('所有部落')
    expect(options[1].text()).toBe('ivalino')
  })

  it('emits filters-change when select values change', async () => {
    wrapper = mount(FilterPanel, {
      props: defaultProps,
    })

    const tribeSelect = wrapper.find('#tribe-filter')
    await tribeSelect.setValue('iraraley')

    expect(wrapper.emitted('filters-change')).toBeTruthy()
    expect(wrapper.emitted('filters-change')[0][0]).toEqual({
      tribe: 'iraraley',
      food_category: '',
      processing_method: '',
      location: '',
      name: '',
    })
  })

  it('clears all filters when clear button is clicked', async () => {
    const initialFilters = {
      tribe: 'iraraley',
      food_category: 'oyod',
    }

    wrapper = mount(FilterPanel, {
      props: {
        ...defaultProps,
        filters: initialFilters,
      },
    })

    const clearButton = wrapper.find('button')
    await clearButton.trigger('click')

    expect(wrapper.emitted('filters-change')).toBeTruthy()
    const lastEmittedFilters = wrapper.emitted('filters-change').slice(-1)[0][0]
    expect(lastEmittedFilters).toEqual({
      tribe: '',
      food_category: '',
      processing_method: '',
      location: '',
      name: '',
    })
  })
})

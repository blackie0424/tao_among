import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { router } from '@inertiajs/vue3'
import ManageLocal from '@/Pages/ManageTribalClassification.vue'
import CreateNote from '@/Pages/CreateFishNote.vue'
import EditCapture from '@/Pages/EditCaptureRecord.vue'
import CreateAudio from '@/Pages/CreateFishAudio.vue'
vi.mock('@inertiajs/vue3', () => ({
  router: { visit: vi.fn(), post: vi.fn() },
  Head: { template: '<div />' },
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
  usePage: () => ({ props: { auth: { user: { role: 'editor' } } } }),
}))
beforeEach(() => vi.clearAllMocks())
const props = { fish: { id: 4, name: '測試魚' }, tribes: [], noteTypes: [], record: { id: 8 }, capture_methods: [] }
const options = { props, global: { stubs: { LazyImage: true, FishNoteForm: true, CaptureRecordForm: true } } }

describe('表單取消去向', () => {
  it('地方知識回地方知識 tab', async () => {
    const page = mount(ManageLocal, options)
    await page.get('button[aria-label="關閉"]').trigger('click')
    expect(router.visit).toHaveBeenCalledWith('/fish/4?tab=local')
  })
})

it('CreateNote 取消回 advanced tab', async () => {
 const page = mount(CreateNote, options)
 await page.get('button[aria-label="關閉"]').trigger('click')
 expect(router.visit).toHaveBeenCalledWith('/fish/4?tab=advanced')
})

it('EditCapture 取消回 capture tab', async () => {
 const page = mount(EditCapture, options)
 await page.get('button[aria-label="關閉"]').trigger('click')
 expect(router.visit).toHaveBeenCalledWith('/fish/4?tab=capture')
})

it('CreateAudio 取消回 audio tab', async () => {
 const page = mount(CreateAudio, options)
 await page.get('button[aria-label="關閉"]').trigger('click')
 expect(router.visit).toHaveBeenCalledWith('/fish/4?tab=audio')
})

it('編輯進階知識提供回進階 tab 的取消鍵', async () => {
  const { default: Page } = await import('@/Pages/Fish/EditKnowledge.vue')
  const page = mount(Page, { ...options, props: { ...props, note: { id: 8 } } })
  expect(page.get('a[href="/fish/4?tab=advanced"]').text()).toBe('取消')
})

it('編輯發音提供回發音 tab 的取消鍵', async () => {
  const { default: Page } = await import('@/Pages/Fish/EditAudio.vue')
  const page = mount(Page, { ...options, props: { ...props, audio: { id: 8 } }, global: { stubs: { EditFishAudioForm: true } } })
  expect(page.get('a[href="/fish/4?tab=audio"]').text()).toBe('取消')
})

it('文獻 Create 取消回文獻 tab', async () => {
 const {default: Page}=await import('@/Pages/ReferenceKnowledge/Create.vue')
 const page=mount(Page,{props:{...props,references:[],knowledge:{id:8}},global:{stubs:{FishAppLayout:{template:'<div><slot /></div>'}}}})
 expect(page.get('a[href="/fish/4?tab=reference"]').text()).toBe('取消')
})

it('文獻 Edit 取消回文獻 tab', async () => {
 const {default: Page}=await import('@/Pages/ReferenceKnowledge/Edit.vue')
 const page=mount(Page,{props:{...props,references:[],knowledge:{id:8}},global:{stubs:{FishAppLayout:{template:'<div><slot /></div>'}}}})
 expect(page.get('a[href="/fish/4?tab=reference"]').text()).toBe('取消')
})

const listOptions = { props: { ...props, groupedNotes: [], stats: {}, knowledge: { data: [], links: [] }, referenceKnowledge: [] }, global: { stubs: {
  FishAppLayout: { props: ['mobileBackUrl'], template: '<main><a :href="mobileBackUrl">返回</a><slot /></main>' },
} } }

it('FishKnowledgeList 返回 advanced tab', async () => {
const {default: Page}=await import('@/Pages/FishKnowledgeList.vue')
const page=mount(Page,listOptions)
expect(page.get('a[href="/fish/4?tab=advanced"]').text()).toBe('返回')
page.unmount()
})

it('FishAudioList 返回 audio tab', async () => {
const {default: Page}=await import('@/Pages/FishAudioList.vue')
const page=mount(Page,listOptions)
expect(page.get('a[href="/fish/4?tab=audio"]').text()).toBe('返回')
page.unmount()
})

it('CaptureRecords 返回 capture tab', async () => {
const {default: Page}=await import('@/Pages/CaptureRecords.vue')
const page=mount(Page,listOptions)
expect(page.get('a[href="/fish/4?tab=capture"]').text()).toBe('返回')
page.unmount()
})

it('ReferenceKnowledge/Index 返回 reference tab', async () => {
const {default: Page}=await import('@/Pages/ReferenceKnowledge/Index.vue')
const page=mount(Page,listOptions)
expect(page.get('a[href="/fish/4?tab=reference"]').text()).toBe('返回')
page.unmount()
})

it('FishKnowledgeList 新增按鈕打開有效表單', async () => {
const {default: Page}=await import('@/Pages/FishKnowledgeList.vue')
const page=mount(Page,listOptions)
const location={href:''}
const realWindow=window
vi.stubGlobal('window', new Proxy(realWindow, { get: (target, key) => key === 'location' ? location : Reflect.get(target, key) }))
try { await page.get('button[title="新增"]').trigger('click'); expect(location.href).toBe('/fish/4/knowledge/create') } finally {vi.stubGlobal('window', realWindow); page.unmount()}
})

it('FishAudioList 新增按鈕打開有效表單', async () => {
const {default: Page}=await import('@/Pages/FishAudioList.vue')
const page=mount(Page,listOptions)
const location={href:''}
const realWindow=window
vi.stubGlobal('window', new Proxy(realWindow, { get: (target, key) => key === 'location' ? location : Reflect.get(target, key) }))
try { await page.get('button[title="新增"]').trigger('click'); expect(location.href).toBe('/fish/4/audio/create') } finally {vi.stubGlobal('window', realWindow); page.unmount()}
})

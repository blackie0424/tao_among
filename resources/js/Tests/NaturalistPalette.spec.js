import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

// #132 AC 10：詳細頁範圍的元件不得再使用 Tailwind 預設色與投影。
// 例外：紅色（刪除、錯誤）與黃色（重試）這兩種狀態色。
const DETAIL_PAGE_FILES = [
  'resources/js/Pages/Fish.vue',
  'resources/js/Components/FishDetail/FishTabBar.vue',
  'resources/js/Components/Global/TabEditActions.vue',
  'resources/js/Components/FishDetail/FishDetailTop.vue',
  'resources/js/Components/UI/Volume.vue',
  'resources/js/composables/useAudioPlayback.js',
  'resources/js/Components/TribalClassification/TribalClassificationSummary.vue',
  'resources/js/Components/FishKnowledge/FishAdvancedKnowledgeSection.vue',
  'resources/js/Components/ReferenceKnowledge/ReferenceKnowledgeSection.vue',
  'resources/js/Components/CaptureRecord/CaptureRecordSection.vue',
  'resources/js/Components/CaptureRecord/CaptureRecordDisplayCard.vue',
]

const DEFAULT_PALETTE =
  /\b[a-z:-]*(?:bg|text|border|ring|outline|from|to|via|divide|fill|stroke)-(?:blue|teal|slate|emerald|green|gray|amber)-\d{2,3}\b/g
const SHADOW = /\bshadow(?:-[a-z0-9]+)?\b/g
const BLUE_RGBA = /rgba?\(\s*59\s*,\s*130\s*,\s*246/g

describe('博物誌配色（#132 AC 10）', () => {
  it.each(DETAIL_PAGE_FILES)('%s 不含 Tailwind 預設色與投影', (file) => {
    const source = readFileSync(resolve(process.cwd(), file), 'utf8')

    expect(source.match(DEFAULT_PALETTE) ?? []).toEqual([])
    expect(source.match(SHADOW) ?? []).toEqual([])
    expect(source.match(BLUE_RGBA) ?? []).toEqual([])
  })
})

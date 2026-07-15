import { describe, it, expect, vi, afterEach } from 'vitest'
import {
  getTribalClassifications,
  createTribalClassification,
  updateTribalClassification,
  deleteTribalClassification,
} from '../api/fishApi'

describe('getTribalClassifications', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('成功時 fetch 呼叫正確 URL，回傳 data 陣列', async () => {
    const mockData = [{ id: 1, tribe: 'iraraley', food_category: 'oyod' }]
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ data: mockData }),
      })
    )

    const result = await getTribalClassifications(1)

    expect(fetch).toHaveBeenCalledWith('/prefix/api/fish/1/tribal-classifications')
    expect(result).toEqual(mockData)
  })

  it('HTTP 非 ok 時回傳 null', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 404 }))

    const result = await getTribalClassifications(99999)

    expect(result).toBeNull()
  })
})

describe('createTribalClassification', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('成功時以正確 body 呼叫 POST，回傳 response json', async () => {
    const data = { tribe: 'iraraley', food_category: 'oyod' }
    const mockResponse = { message: 'Tribal classification created successfully', data: { id: 1, ...data } }
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve(mockResponse),
      })
    )

    const result = await createTribalClassification(1, data)

    expect(fetch).toHaveBeenCalledWith(
      '/prefix/api/fish/1/tribal-classifications',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify(data),
      })
    )
    expect(result).toEqual(mockResponse)
  })

  it('HTTP 非 ok 時拋出例外', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 422 }))

    await expect(createTribalClassification(1, { tribe: 'iraraley' })).rejects.toThrow()
  })
})

describe('updateTribalClassification', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('成功時以正確 body 呼叫 PUT，回傳 response json', async () => {
    const data = { tribe: 'imowrod', food_category: 'rahet' }
    const mockResponse = { message: 'Tribal classification updated successfully', data: { id: 5, ...data } }
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve(mockResponse),
      })
    )

    const result = await updateTribalClassification(5, data)

    expect(fetch).toHaveBeenCalledWith(
      '/prefix/api/tribal-classifications/5',
      expect.objectContaining({
        method: 'PUT',
        body: JSON.stringify(data),
      })
    )
    expect(result).toEqual(mockResponse)
  })

  it('HTTP 非 ok 時拋出例外', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 404 }))

    await expect(updateTribalClassification(99999, { tribe: 'iraraley' })).rejects.toThrow()
  })
})

describe('deleteTribalClassification', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('成功時呼叫 DELETE 正確 URL，回傳 response json', async () => {
    const mockResponse = { message: 'Tribal classification deleted successfully' }
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve(mockResponse),
      })
    )

    const result = await deleteTribalClassification(5)

    expect(fetch).toHaveBeenCalledWith(
      '/prefix/api/tribal-classifications/5',
      expect.objectContaining({ method: 'DELETE' })
    )
    expect(result).toEqual(mockResponse)
  })

  it('HTTP 非 ok 時拋出例外', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 404 }))

    await expect(deleteTribalClassification(99999)).rejects.toThrow()
  })
})

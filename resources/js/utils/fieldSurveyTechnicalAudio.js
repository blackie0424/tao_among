const DB_NAME = 'tao-among-field-survey-validation'
const DB_VERSION = 1
const CHUNK_STORE = 'audio_chunks'

export function selectRecordingMimeType(isSupported) {
  for (const mimeType of ['audio/mp4', 'audio/aac']) {
    if (isSupported(mimeType)) return mimeType
  }

  return null
}

export function assembleAudioChunks(chunks, mimeType) {
  const orderedBlobs = [...chunks]
    .sort((left, right) => left.sequence - right.sequence)
    .map((chunk) => chunk.blob)

  return new Blob(orderedBlobs, { type: mimeType })
}

export function formatDuration(milliseconds) {
  const totalSeconds = Math.max(0, Math.floor(milliseconds / 1000))
  const minutes = Math.floor(totalSeconds / 60)
  const seconds = totalSeconds % 60

  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
}

export function formatBytes(bytes) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(2)} KB`

  return `${(bytes / (1024 * 1024)).toFixed(2)} MB`
}

function requestToPromise(request) {
  return new Promise((resolve, reject) => {
    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(request.error ?? new Error('IndexedDB request failed'))
  })
}

function transactionToPromise(transaction) {
  return new Promise((resolve, reject) => {
    transaction.oncomplete = () => resolve()
    transaction.onerror = () =>
      reject(transaction.error ?? new Error('IndexedDB transaction failed'))
    transaction.onabort = () =>
      reject(transaction.error ?? new Error('IndexedDB transaction aborted'))
  })
}

export async function openTechnicalAudioDatabase(indexedDb = window.indexedDB) {
  if (!indexedDb) throw new Error('此瀏覽器不支援 IndexedDB')

  const request = indexedDb.open(DB_NAME, DB_VERSION)
  request.onupgradeneeded = () => {
    const database = request.result
    if (!database.objectStoreNames.contains(CHUNK_STORE)) {
      const store = database.createObjectStore(CHUNK_STORE, {
        keyPath: ['recordingId', 'sequence'],
      })
      store.createIndex('recordingId', 'recordingId', { unique: false })
    }
  }

  return requestToPromise(request)
}

export async function saveTechnicalAudioChunk(database, chunk) {
  const transaction = database.transaction(CHUNK_STORE, 'readwrite')
  transaction.objectStore(CHUNK_STORE).put(chunk)
  await transactionToPromise(transaction)
}

export async function loadTechnicalAudioChunks(database, recordingId) {
  const transaction = database.transaction(CHUNK_STORE, 'readonly')
  const index = transaction.objectStore(CHUNK_STORE).index('recordingId')
  const chunks = await requestToPromise(index.getAll(recordingId))
  await transactionToPromise(transaction)

  return chunks.sort((left, right) => left.sequence - right.sequence)
}

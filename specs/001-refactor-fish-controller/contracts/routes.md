# Routes Contract — web.php (SSR / Inertia)

This plan ignores deprecated `/prefix/api` endpoints. Canonical routes are defined in `routes/web.php` and rendered via Inertia views.

## Inventory

- GET `/` → FishController@index → Inertia::render('Index')
- GET `/fishs` → FishController@getFishs → Inertia::render('Fishs')
- GET `/search` → FishController@search (name: fish.search) → Inertia::render('Fish/Search')
- GET `/fish/{id}` → FishController@getFish → Inertia::render('Fish')
- GET `/fish/{id}/createAudio` → FishController@createAudio → Inertia::render('CreateFishAudio')

- GET `/fish/create` → FishController@create (name: fish.create) → Inertia::render('CreateFish')
- POST `/fish` → FishController@store (name: fish.store)

- GET `/fish/{id}/edit` → FishController@edit (name: fish.edit) → Inertia::render('EditFishName')
- PUT `/fish/{id}/name` → FishController@updateName (name: fish.updateName)
- DELETE `/fish/{id}` → FishController@destroy (name: fish.destroy)
- GET `/fish/{id}/editSize` → FishController@editSize (name: fish.editSize) → Inertia::render('EditFishSize')
- PUT `/fish/{id}/size` → FishController@updateSize (name: fish.updateSize)

- GET `/fish/{id}/capture-records` → FishController@captureRecords (name: fish.capture-records) → Inertia::render('CaptureRecords')
- GET `/fish/{id}/capture-records/create` → FishController@createCaptureRecord (name: fish.capture-records.create) → Inertia::render('CreateCaptureRecord')
- POST `/fish/{id}/capture-records` → FishController@storeCaptureRecord (name: fish.capture-records.store)
- GET `/fish/{id}/capture-records/{record_id}/edit` → FishController@editCaptureRecord (name: fish.capture-records.edit) → Inertia::render('EditCaptureRecord')
- PUT `/fish/{id}/capture-records/{record_id}` → FishController@updateCaptureRecord (name: fish.capture-records.update)
- DELETE `/fish/{id}/capture-records/{record_id}` → FishController@destroyCaptureRecord (name: fish.capture-records.destroy)

- GET `/fish/{id}/tribal-classifications` → FishController@tribalClassifications (name: fish.tribal-classifications) → Inertia::render('TribalClassifications')
- GET `/fish/{id}/tribal-classifications/create` → FishController@createTribalClassification (name: fish.tribal-classifications.create) → Inertia::render('CreateTribalClassification')
- POST `/fish/{id}/tribal-classifications` → FishController@storeTribalClassification (name: fish.tribal-classifications.store)
- GET `/fish/{id}/tribal-classifications/{classification_id}/edit` → FishController@editTribalClassification (name: fish.tribal-classifications.edit) → Inertia::render('EditTribalClassification')
- PUT `/fish/{id}/tribal-classifications/{classification_id}` → FishController@updateTribalClassification (name: fish.tribal-classifications.update)
- DELETE `/fish/{id}/tribal-classifications/{classification_id}` → FishController@destroyTribalClassification (name: fish.tribal-classifications.destroy)

- GET `/fish/{fish}/knowledge-list` → FishNoteController@knowledgeList (name: fish.knowledge-list)
- GET `/fish/{fish}/knowledge/{note}/edit` → FishNoteController@editKnowledge (name: fish.knowledge.edit)
- PUT `/fish/{fish}/knowledge/{note}` → FishNoteController@updateKnowledge (name: fish.knowledge.update)
- DELETE `/fish/{fish}/knowledge/{note}` → FishNoteController@destroyKnowledge (name: fish.knowledge.destroy)

- GET `/fish/{fish}/audio-list` → FishAudioController@audioList (name: fish.audio-list)
- PUT `/fish/{fish}/audio/{audio}` → FishController@updateAudioFilename
- DELETE `/fish/{fish}/audio/{audio}` → FishAudioController@destroyAudio (name: fish.audio.destroy)

## Inputs / Outputs (selected)

- FishController@getFishs: inputs = filters[name, tribe, dietary_classification, processing_method, capture_location, capture_method]; output props = { fishs, filters, searchOptions, searchStats }
- FishController@getFish: inputs = id; output props = { fish, tribalClassifications, captureRecords, fishNotes(grouped) }

## Testing Guidance (Feature)

- SSR routes: assert status 200, view component name (via Inertia testing helpers if available), and key props presence/shape
- Do NOT include `/prefix/api` tests; those endpoints are deprecated in this plan

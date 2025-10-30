# Phase 1 — Data Model

Entities

- Fish
  - id, name, image, audio_filename, has_webp?, created_at, updated_at
  - Relations: notes[*], audios[*], size[1], tribalClassifications[*], captureRecords[*]
- FishAudio
  - id, fish_id, name, locate, created_at, updated_at
- FishNote
  - id, fish_id, note, note_type, locate, created_at, updated_at

Validation (read-only scope)

- id: numeric > 0
- since: unix timestamp or RFC3339; reject invalid formats (422)
- keyword: optional string; min length 1 if present

Derived Fields

- image_url: default to `default.png` when image is empty; prefer webp when has_webp == true
- audio url: null when name/audio_filename is empty

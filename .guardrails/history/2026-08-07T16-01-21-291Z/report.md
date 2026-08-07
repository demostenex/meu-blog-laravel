# Guardrails Report

Project: blog
Generated at: 2026-08-07T16:01:21.291Z
Score geral: 77/100

Findings: 17

## Alto

- app/Console/Commands/SyncMediaToR2.php:18
  - Regra: long_function
  - Política: warn
  - Status: new
  - Problema: Function handle has 108 lines, above the limit of 80.
  - Ação sugerida: Extract cohesive steps into named functions or a service.

- app/Services/TtsService.php:10
  - Regra: large_service
  - Política: warn
  - Status: new
  - Problema: Service TtsService has 15 methods, above the limit of 12.
  - Ação sugerida: Split the service by responsibility or use case.

- resources/views/livewire/posts/create.blade.php, resources/views/livewire/posts/edit.blade.php
  - Regra: duplicate_functions
  - Política: warn
  - Status: new
  - Problema: Functions rules, rules have duplicated bodies.
  - Ação sugerida: Consolidate the duplicated logic behind one shared function or service.

- resources/views/livewire/posts/create.blade.php, resources/views/livewire/posts/edit.blade.php
  - Regra: duplicate_functions
  - Política: warn
  - Status: new
  - Problema: Functions toggleTag, toggleTag have duplicated bodies.
  - Ação sugerida: Consolidate the duplicated logic behind one shared function or service.

- resources/views/livewire/posts/create.blade.php, resources/views/livewire/posts/edit.blade.php
  - Regra: duplicate_functions
  - Política: warn
  - Status: new
  - Problema: Functions addNewTag, addNewTag have duplicated bodies.
  - Ação sugerida: Consolidate the duplicated logic behind one shared function or service.

- resources/views/livewire/posts/create.blade.php, resources/views/livewire/posts/edit.blade.php
  - Regra: duplicate_functions
  - Política: warn
  - Status: new
  - Problema: Functions storeTrixImage, storeTrixImage have duplicated bodies.
  - Ação sugerida: Consolidate the duplicated logic behind one shared function or service.

- resources/views/livewire/posts/create.blade.php, resources/views/livewire/posts/edit.blade.php
  - Regra: duplicate_functions
  - Política: warn
  - Status: new
  - Problema: Functions storeTrixVideo, storeTrixVideo have duplicated bodies.
  - Ação sugerida: Consolidate the duplicated logic behind one shared function or service.

- resources/views/livewire/posts/create.blade.php, resources/views/livewire/posts/edit.blade.php
  - Regra: duplicate_functions
  - Política: warn
  - Status: new
  - Problema: Functions update, update have duplicated bodies.
  - Ação sugerida: Consolidate the duplicated logic behind one shared function or service.

- resources/views/livewire/posts/show.blade.php:426
  - Regra: long_function
  - Política: warn
  - Status: new
  - Problema: Function buildToc has 134 lines, above the limit of 80.
  - Ação sugerida: Extract cohesive steps into named functions or a service.

## Médio

- app/Services/AiServiceFactory.php, app/Services/AiServiceFactory.php
  - Regra: similar_functions
  - Política: warn
  - Status: new
  - Problema: Functions imageModelFor, audioModelFor are structurally similar.
  - Ação sugerida: Review whether the shared structure is intentional or should be extracted.

- resources/views/layouts/app.blade.php, resources/views/layouts/blog.blade.php
  - Regra: similar_functions
  - Política: warn
  - Status: new
  - Problema: Functions setDarkMode, setDarkMode are structurally similar.
  - Ação sugerida: Review whether the shared structure is intentional or should be extracted.

- resources/views/livewire/dashboard.blade.php:1
  - Regra: large_file
  - Política: warn
  - Status: new
  - Problema: File has 415 lines, above the limit of 400.
  - Ação sugerida: Split unrelated responsibilities into smaller modules.

- resources/views/livewire/posts/create.blade.php:1
  - Regra: large_file
  - Política: warn
  - Status: new
  - Problema: File has 515 lines, above the limit of 400.
  - Ação sugerida: Split unrelated responsibilities into smaller modules.

- resources/views/livewire/posts/edit.blade.php:1
  - Regra: large_file
  - Política: warn
  - Status: new
  - Problema: File has 1155 lines, above the limit of 400.
  - Ação sugerida: Split unrelated responsibilities into smaller modules.

- resources/views/livewire/posts/edit.blade.php, resources/views/livewire/posts/edit.blade.php
  - Regra: similar_functions
  - Política: warn
  - Status: new
  - Problema: Functions refreshEnglishStatus, refreshAudioStatus are structurally similar.
  - Ação sugerida: Review whether the shared structure is intentional or should be extracted.

- resources/views/livewire/posts/show.blade.php:1
  - Regra: large_file
  - Política: warn
  - Status: new
  - Problema: File has 665 lines, above the limit of 400.
  - Ação sugerida: Split unrelated responsibilities into smaller modules.

- resources/views/livewire/settings/ai-providers.blade.php:1
  - Regra: large_file
  - Política: warn
  - Status: new
  - Problema: File has 665 lines, above the limit of 400.
  - Ação sugerida: Split unrelated responsibilities into smaller modules.

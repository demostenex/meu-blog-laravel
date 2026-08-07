# Guardrails Analysis

Project: blog
Generated at: 2026-08-07T15:57:39.171Z
Source report: 2026-08-07T15:57:25.832Z

## Read-only Contract

Radar only: this analysis points out where to inspect. It does not propose automatic code changes and does not modify application code.

## Maturity

Level: controlado com pontos de atenção
Score: 77/100
Findings: 17
High/Critical findings: 9

## Trend

No previous history version available for comparison.

## Inspection Priorities

- resources/views/livewire/posts/edit.blade.php
  - Findings: 9
  - Dominant rules: duplicate_functions, similar_functions, large_file
  - Why inspect: file size makes review and AI-assisted edits harder to keep scoped.

- resources/views/livewire/posts/create.blade.php
  - Findings: 7
  - Dominant rules: duplicate_functions, large_file
  - Why inspect: file size makes review and AI-assisted edits harder to keep scoped.

- resources/views/livewire/posts/show.blade.php
  - Findings: 2
  - Dominant rules: long_function, large_file
  - Why inspect: file size makes review and AI-assisted edits harder to keep scoped.

- app/Console/Commands/SyncMediaToR2.php
  - Findings: 1
  - Dominant rules: long_function
  - Why inspect: concentrates guardrail findings and should be inspected before nearby changes grow.

- app/Services/TtsService.php
  - Findings: 1
  - Dominant rules: large_service
  - Why inspect: service has enough responsibility to become an ownership and regression hotspot.

- app/Services/AiServiceFactory.php
  - Findings: 2
  - Dominant rules: similar_functions
  - Why inspect: concentrates guardrail findings and should be inspected before nearby changes grow.

- resources/views/layouts/app.blade.php
  - Findings: 1
  - Dominant rules: similar_functions
  - Why inspect: concentrates guardrail findings and should be inspected before nearby changes grow.

- resources/views/layouts/blog.blade.php
  - Findings: 1
  - Dominant rules: similar_functions
  - Why inspect: concentrates guardrail findings and should be inspected before nearby changes grow.

- resources/views/livewire/dashboard.blade.php
  - Findings: 1
  - Dominant rules: large_file
  - Why inspect: file size makes review and AI-assisted edits harder to keep scoped.

- resources/views/livewire/settings/ai-providers.blade.php
  - Findings: 1
  - Dominant rules: large_file
  - Why inspect: file size makes review and AI-assisted edits harder to keep scoped.

## AI-Ready Prompt

The prompt below is safe to send to an external analysis model. It asks for interpretation only.

```text
You are analyzing a guardrails report for code health.
Do not edit code. Do not produce patches. Do not propose automatic fixes.
Only explain what humans should inspect, why it matters, and what questions they should ask before changing code.

Project: blog
Score: 77/100
Files scanned: 157
Findings: 17
Maturity: controlado com pontos de atenção
Trend: No previous history version available for comparison.

Top inspection priorities:
- resources/views/livewire/posts/edit.blade.php: 9 finding(s), rules=duplicate_functions, similar_functions, large_file
- resources/views/livewire/posts/create.blade.php: 7 finding(s), rules=duplicate_functions, large_file
- resources/views/livewire/posts/show.blade.php: 2 finding(s), rules=long_function, large_file
- app/Console/Commands/SyncMediaToR2.php: 1 finding(s), rules=long_function
- app/Services/TtsService.php: 1 finding(s), rules=large_service
- app/Services/AiServiceFactory.php: 2 finding(s), rules=similar_functions
- resources/views/layouts/app.blade.php: 1 finding(s), rules=similar_functions
- resources/views/layouts/blog.blade.php: 1 finding(s), rules=similar_functions
```

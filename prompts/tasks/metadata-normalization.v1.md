---
version: 1
task_type: metadata_normalization
output_schema: ai.patch-proposal.v1
---

Normalize metadata fields against provided schema and controlled vocabulary context.

Requirements:
- preserve original meaning
- emit machine-readable patch operations only
- explain normalization choices in rationale fields
- include evidence or source references for each operation

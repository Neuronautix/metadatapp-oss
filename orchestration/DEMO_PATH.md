# DEMO PATH — WellFAIR Conference Edition

**Owner:** Demo Finisher

## Objective

Deliver a compelling live demonstration that makes the thesis *"Data welfare is animal welfare"* immediately
visible and credible to a conference audience of FAIR-data researchers, policy makers, and animal welfare
scientists. Every step must be self-explanatory on a projector — no console commands, no config switches.

## Prerequisites
- Data mode set to `mock` (or `real` if backend is seeded via `castor fixture`).
- `metadatapp-feat.enabled` flag set to `true` (default in mock mode).
- Browser font size bumped to 125% for projector legibility.

---

## Script

### Step 1 — WellFAIR Framework page  (`/wellfair`)

**Narrative:** "Before we look at the tool, let me show you *why* this matters."

- Open `/wellfair` (sidebar → Research & Studies → WellFAIR Framework).
- Walk through the hero headline: **"Data welfare IS animal welfare"**.
- Point at each of the four FAIR pillar cards:
  - **F – Findable** → *"No lost data = fewer re-runs = fewer animals."*
  - **A – Accessible** → *"Locked silos force labs to repeat each other's work."*
  - **I – Interoperable** → *"Cage sensors + ELN data + study metadata = one welfare picture."*
  - **R – Reusable** → *"Reusable data trains NAM models — the 3Rs in action."*
- Scroll to the **3Rs panel** and call out Replacement / Reduction / Refinement connections.
- Use the **Live demo workflow** steps list to signal the path ahead.

---

### Step 2 — Dashboard  (`/`)

**Narrative:** "This is the command centre — FAIR compliance is visible at a glance."

- Show the **WellFAIR banner** at the top of the dashboard, reinforcing the core message.
- Point to the investigation and studies counts in the stat cards.
- Show the Ongoing Investigations widget and note the FAIR score badges visible in the list.

---

### Step 3 — Investigations list  (`/investigations`)

**Narrative:** "Every research programme is scored against FAIR principles — not retrospectively, but live."

- Open the Investigations page.
- **FAIR column** in the table shows a coloured badge (green ≥ 85, amber ≥ 70, outline < 70).
- Point out that two investigations have different scores — one green, one amber.
- *"What does that difference mean for the animals? Let's find out."*

---

### Step 4 — Investigation detail — FAIR Score panel

**Narrative:** "Here we can see exactly which FAIR criteria pass and which don't."

- Click into the highest-scoring investigation.
- Scroll to the **FAIR Score panel** (four coloured progress bars + 15 criterion checkboxes).
- Explain a failing criterion, e.g. *"F4 — not indexed in a searchable resource"* means other labs
  cannot discover this data → risk of duplicated experiment → unnecessary animals.
- Click back and open the lower-scoring investigation. Show the contrast.

---

### Step 5 — AI Assistant  (`/assistant`)

**Narrative:** "You don't have to manually read 15 criteria. The AI assistant reads them for you."

- Open the Assistant page.
- Type: **"Check the FAIR compliance of study EXP-401 and explain which criteria are failing."**
- The MCP bridge calls the live FAIR assessment endpoint and returns a structured explanation.
- Highlight that the assistant cites the criterion IDs (F2, R1.1 …) — not vague AI output.

---

### Step 6 — FAIR3R Sync  (`/connected-apps` → Fair3r)

**Narrative:** "One click sends your FAIR-compliant metadata to the FAIR3R validation portal in
machine-readable form — no manual copy-paste, no format translation."

- Navigate to **Connected Apps** and click the **Fair3r** integration card.
- In the **FAIR3R Schema Exchange** panel, select the highest-scoring investigation.
- Click **Fetch (JSON)** — the FDF JSON document appears in the preview.
  - *"This is the exact JSON payload defined by the `fdf-dataset` schema — every FAIR criterion is
    expressed in a format FAIR3R and any CKAN-compatible repository can consume."*
- Click **Verify (semantic)** — the validation endpoint checks the payload against the schema.
  - Show the green "Schema-valid FDF JSON" badge.
  - *"This verification step uses web-semantics standards: JSON-LD context, URI validation, and
    structural schema checks — the same approach used by FAIR3R's own intake pipeline."*
- Click **Export DataCite XML** — a DataCite v4 XML file downloads.
  - *"This XML is the format required by DOI registration agencies (DataCite, CrossRef). Depositing
    it formally mints a persistent identifier — the F1 criterion achieved in one click."*
- Click **Push (JSON)** — the FDF JSON is saved locally and FAIR3R opens in a new tab.
  - *"Drag the downloaded file into the FAIR3R import dialog at validation.fair3r.fr to complete
    the deposit. The round-trip from lab data to FAIR repository is fully automated."*

---

### Step 7 — Export  (`/export`)

**Narrative:** "The final step: making data ready for re-use by others."

- Open Export.
- Show the **RO-Crate** and **FAIR PDF report** download options.
- *"Downloading this bundle is the act of closing the loop — this data now becomes findable,
  accessible, interoperable, and reusable by the next team."*

---

## End State

The audience has seen:
1. The conceptual WellFAIR framework fully explained in the UI.
2. A live FAIR score for every investigation, colour-coded for instant comprehension.
3. A criterion-by-criterion audit trail directly connecting metadata quality to welfare outcomes.
4. An AI assistant that operates on real assessment data, not hallucinations.
5. A standards-compliant FAIR3R push/fetch cycle with schema validation and DataCite XML export.
6. A full FAIR lifecycle closed by RO-Crate and PDF export for regulatory submission.

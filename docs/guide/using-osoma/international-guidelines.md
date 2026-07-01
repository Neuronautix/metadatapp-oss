# International guidelines reporting

The **Guidelines** workspace turns the data you already have in Metadatapp into
compliance reports for the major life-science reporting standards — and uses your
whole account (plus connected apps and AI) to help you fill them.

It is reached from the **Guidelines** item in the left sidebar (route
`/guidelines`) and is gated by the `feature.guidelinesReporting` flag, so it may
not appear on every instance. For the FAIR scoring it feeds into, see
[FAIR & exports](fair).

## The four standards

Instead of four overlapping checklists, the workspace models all four standards as
one schema. You fill a concept **once** and every standard that shares it is
credited automatically.

| Standard | Answers | Level |
| --- | --- | --- |
| **PREPARE** | What you *planned* before the study | study |
| **ARRIVE 2.0** | What you must *report* when publishing | study |
| **EQIPD** | What quality controls your *unit* has | lab |
| **MNMS** | What columns your *data file* must contain | CSV column |

A fifth template, the **ARRIVE + PREPARE crosswalk**, fills both study-level
standards in a single pass.

## The workflow

1. **Pick a target.** Choose an **Investigation** (and optionally a specific
   **Study**) from the selectors, then pick a **template**.
2. **Read the conformance dashboard.** You immediately see an overall FAIR
   *R-dimension* score, the satisfied / partial / missing totals, and a
   by-section list where every field shows a status badge:
   - ✅ **Satisfied** — a value is present (or borrowed from a sibling standard,
     annotated *"via ARRIVE: …"*).
   - ⚠️ **Partial** — e.g. an MNMS column exists but is missing its unit.
   - ❌ **Missing** — needs filling.
3. **Fill the gaps** (see below).
4. **Export.** Download a human-readable **`report.md`** (sections rendered with
   each item's prompt and resolved value, plus a checklist and an action-items
   list) or a bundled **`report.zip`**.

## Intelligence: where suggested values come from

The workspace does not start blank, and it does not guess. It draws on your real
data, account-wide and tenant-isolated (you only ever see your own account).

- **Auto-computed facts.** Counts and summaries are calculated in code from your
  records — number of subjects, sex split, species and strains, housing density,
  cage types, DVC light schedule and activity presence, study time span. Fields
  like subject count or species show ✅ **without any AI**, tagged
  *auto-computed*.
- **Reuse your previous answers.** For a given field, the most recent value you
  entered for that *same* field in another investigation is offered as a
  one-click suggestion, labelled with its source (*"from Investigation …"*).
- **Connected-app grounding.** Locally synced data from your connected apps
  (elabFTW metadata, protocol links, strains, housing) is surfaced as cited
  candidates for fields like ethics statements, licences, and procedures.
- **AI drafting.** For free-text items computation can't supply (most EQIPD
  requirements, narrative PREPARE items), the assistant drafts a candidate
  **grounded only in the evidence above** and lists *"Grounded in: …"* citations
  with its rationale. It never invents facts or numbers.

```{admonition} Crosswalks fill siblings automatically
:class: tip
Saving one concept satisfies the equivalent field in other standards. Save
ARRIVE's *humane endpoints* and PREPARE's *humane endpoints* goes green on its
own. Crosswalks are directional — planning something (PREPARE) never lets you
claim you *reported* it (ARRIVE).
```

## Filling fields

Each field row offers three paths, all writing into one shared store:

- **Manual** — type a value inline and save.
- **One-click suggestion** — apply a deterministic suggestion (computed fact,
  previous answer, or connected-app value). This works even with **no AI provider
  configured**.
- **AI draft** — the ✨ button drafts a grounded value you review, edit, and
  accept. With no API key it cleanly shows *"AI unavailable — fill manually"* and
  the deterministic suggestions remain.

### Auto-fill all missing (review queue)

The **Auto-fill all missing** button drafts every unfilled field at once and opens
a **review queue**. For each field you see the drafted value (editable), its
rationale, citations, and any deterministic suggestions, with **Accept / Reject**
and **Accept all**.

```{admonition} Nothing is saved without your approval
:class: important
The review queue never writes automatically. Accepting saves the value you see —
including any edits you made — and re-checks conformance so crosswalk
auto-satisfaction updates live. If the AI provider is unavailable, the queue still
shows the deterministic suggestions so it stays useful offline.
```

## How it relates to FAIR

Guideline conformance contributes to the **Reusable** pillar of a study's
[FAIR score](fair): satisfied fields raise it, missing fields lower it. Missing
guideline items never block any other action — they only degrade the score and
raise action items.

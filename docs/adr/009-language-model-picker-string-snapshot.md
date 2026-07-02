# 009: Language-model picker — string snapshot with union of defaults + custom values

| Field              | Value                                               |
| ------------------ | --------------------------------------------------- |
| **Created By**     | Martin Yde-Granath                                  |
| **Date**           | 2026-07-02                                          |
| **Decision Maker** | ITK Dev team                                        |
| **Stakeholders**   | ITK Dev developers, future maintainers of ai-reolen |
| **Status**         | Draft                                               |

## Context

The `languageModel` column on `Assistant` is a free-typed string. The
"Del assistent" wizard renders it as a plain text input, which drives
two problems:

- **Typos**: `gpt-4o` and `gpt4o` end up as two distinct facets on the
  catalogue's language-model filter, muddying counts and offering the
  operator two roads that look the same to a human but different to the
  query.
- **Discovery**: a first-time creator has no idea what previous operators
  typed in — the free-text input is a blank space, not a menu.

The `framework` field solved a related problem in ADR 003 / issue #154
by mapping a deploy-time env-var list (`SUPPORTED_FRAMEWORKS`) onto a
closed `<select>` via `App\Framework\SupportedFrameworks`. But
`framework` is *closed*: only the values in the env var may be persisted.
`languageModel` is deliberately open — the ship-list can never keep up
with model releases, so the picker must accept a free-typed value the
operator can't find in the list.

This ADR records the shape chosen for the picker's options list and
the persistence side effects.

### Drivers

- **Functional:**
  - Show a searchable autocomplete list combining the install's
    deploy-time defaults with every distinct value already persisted
    on `Assistant`, so common answers are one keystroke away.
  - Accept a free-typed value that isn't on the list, so a new model
    can be shared before the operator waits for a deploy.
  - Keep the catalogue's language-model facet counts working
    unchanged (grouped scalar `IN` filter, no schema change).
- **Non-functional:**
  - Match the `SUPPORTED_FRAMEWORKS` pattern so operators don't need
    to learn two mental models for two related deploy-time lists.
  - Keep the diff small enough to ship inside a single sprint; the
    entity route (Approach B, below) is a schema + migration + voter +
    JSON endpoint that overshoots the immediate need.

## Options Considered

### Approach A — keep `languageModel` as a string snapshot (chosen)

- `Assistant::$languageModel` stays `string`.
- A new `App\Model\SupportedLanguageModels` service returns
  `array_unique(array_merge($defaults, $custom))`, where `$defaults`
  is parsed from a `SUPPORTED_LANGUAGE_MODELS` env var and `$custom`
  is `SELECT DISTINCT languageModel FROM assistant` via
  `AssistantRepository::distinctLanguageModels()`.
- The wizard's metadata step renders the field as a native
  `<input type="text" list="…">` + `<datalist>` combination, with a
  small Stimulus controller adding an "Add new" hint when the typed
  value matches nothing in the datalist. The server accepts the
  typed value as-is; no round-trip is needed for a new value.
- **Pros**:
  - Smallest diff. No schema change, no migration, no new controller
    endpoint, no voter.
  - Matches the shape `SUPPORTED_FRAMEWORKS` established.
  - Facet counts on the catalogue work unchanged — the column shape
    hasn't moved.
  - Free-tagging is client-only, which naturally falls back to a
    keyboard-usable native combobox when JS is off.
- **Cons**:
  - No rename without touching every assistant row — renaming
    `gpt-4o` to `GPT-4o` in the picker doesn't propagate to
    already-persisted rows. Facet dedup can be added in a follow-up
    if this becomes painful.
  - `SELECT DISTINCT` on `assistant` runs on every form render.
    Cheap today (small catalogue), might warrant a short in-memory
    cache when the catalogue grows.
  - Typos still land in the pool by design — they're "custom values"
    the moment they're persisted. The picker's autocomplete makes
    them less likely, but doesn't gate them.

### Approach B — promote to a `LanguageModel` entity

- New `App\Entity\LanguageModel` with `id`, `machineName` (unique),
  `readableName`.
- `Assistant::$languageModel` becomes a `?LanguageModel` reference.
- Migration seeds one `LanguageModel` row per existing distinct value
  on `Assistant`, plus one per env-var default.
- "+ Add new" hits a JSON `POST /language-models` endpoint that
  idempotently inserts a row (unique on `machineName`), CSRF-guarded,
  returns id + label.
- **Pros**:
  - Rename is one row.
  - Facet counts are trivial joins.
  - Machine name / readable name decouple.
- **Cons**:
  - Schema change + migration on real rows.
  - New controller endpoint + voter (matching the `admin-user-action`
    JSON pattern from #111).
  - Touches `AssistantCreator`, `CatalogCriteria`, fixtures,
    repository facet queries, and every test that constructs an
    `Assistant`.
  - Roughly the same size as #154 was, but with a live migration.

## Decision

Adopt **Approach A**.

The picker UI is what closes the discovery and typo gaps today; the
schema change buys nothing extra beyond rename-in-one-place, which
isn't a current pain point.

- `App\Model\SupportedLanguageModels` merges env-var defaults with
  `AssistantRepository::distinctLanguageModels()` results, defaults
  first (env-var order), remaining custom values appended
  alphabetically (case-insensitive natural order), deduped.
- The env var `SUPPORTED_LANGUAGE_MODELS` is optional. Empty / unset
  yields no deploy-time defaults, and the picker still shows whatever
  custom values the DB has. Operators are expected to populate the
  env var with the install's shortlist (e.g.
  `gpt-4o,gpt-4o-mini,claude-3.5-sonnet,llama-3.1-70b,mistral-large`).
- `AssistantMetadataStepType` sets `list="assistantLanguageModelOptions"`
  on the `languageModel` `<input>` and exposes the resolved options
  list + datalist id via `finishView()` so the template can render
  a native `<datalist>` fallback and hand the same option set to
  the JS enhancer without pulling the service in as a Twig global.
- The Stimulus controller
  `assets/controllers/language_model_picker_controller.js`
  initialises [Choices.js](https://github.com/Choices-js/Choices)
  in text mode (`maxItemCount: 1`) so the field behaves as a
  single-value pill-style combobox with search-as-you-type, keyboard
  navigation, and an explicit "+ Tilføj: '…'" affordance for values
  not on the known-value list. Choices.js hides the original
  `<input>` at connect time, so the JS-off `<datalist>` fallback and
  the JS-on combobox don't fight each other. Choices.js is pulled
  in via `importmap:require` + a same-named CSS import in
  `assets/app.js`; no npm/webpack step.

If the rename or delete workflows later become worth the schema cost,
Approach B is the natural follow-up. This ADR is not superseded until
that happens.

## Consequences

### Positive

- Discovery and typo problems close with a single deploy: the picker
  guides operators to the common values without gating what can be
  persisted.
- Zero migration risk. Existing rows keep their existing values; the
  facet queries don't change shape.
- Free-tagging is a client-only concern — the server round-trip is
  identical to today's plain-text submit.

### Negative

- Renames are a data-migration problem, not a config change. Worked
  around today by not renaming; called out in the follow-up options
  for Approach B.
- The picker's typo protection is advisory, not enforced. A committed
  operator who ignores the "+ Add new" hint on an obvious typo still
  lands the typo in the catalogue. The catalogue facet's `IN` filter
  still handles that correctly (the typo just becomes its own facet
  bucket).
- `SELECT DISTINCT languageModel FROM assistant` runs per form render.
  Acceptable today; may warrant a request-scoped or short TTL cache
  when the catalogue's row count justifies it.

## Related

- Issue [#177](https://github.com/itk-dev/ai-reolen/issues/177) — the
  originating ticket, which spells out both approaches and asks for
  an explicit choice recorded here.
- ADR 003 — `SUPPORTED_FRAMEWORKS` established the deploy-time
  env-var-driven picker pattern this ADR mirrors.
- Issue [#154](https://github.com/itk-dev/ai-reolen/issues/154) — the
  framework-picker implementation, useful reference for how the same
  env-var / service / picker shape was applied to a *closed* choice
  set.

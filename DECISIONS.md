# Decisions & Trade-offs

## Assumptions

- Single deployment can serve multiple tenants, but the assignment's core
  use case is single-tenant; `tenant_id` is nullable everywhere and the
  tenant global scope is a no-op unless `currentTenantId` is explicitly
  bound, so none of this adds friction if you don't need it.
- "Never trust the browser" means server-side validation is derived from
  the *same* schema that renders the form (`ValidationRuleBuilder`), not a
  hand-maintained parallel rule set that could drift from the UI.
- The brief's "hallucinated field type" scenario is treated as a plain
  schema-validation failure (unknown type isn't in `config('formbuilder.field_types')`),
  which is why `SchemaValidator` is shared by the AI repair loop instead of
  the AI service having its own bespoke checking logic.

## Key trade-offs

**Submissions stored as one JSON payload row, not one-row-per-answer.**
Form schemas are fully dynamic — a rigid `answers` table would need
EAV-style columns anyway. JSON keeps writes atomic (one INSERT per
submission, no partial-failure risk across N answer rows) and keeps the
common case (render a submission, export to CSV) simple. The cost: ad-hoc
cross-submission analytics needs `JSON_EXTRACT`/`JSON_SEARCH` (used for the
search box) rather than a plain `GROUP BY`. If analytics become a first-class
feature, the fix is an additive projection table built from submissions,
not a schema change to `submissions` itself.

**Schema versioning via full immutable snapshots, not diffs.**
Storing the whole JSON schema per version (rather than a diff/patch chain)
costs more storage but means rollback, "what did version 3 look like", and
"what did submitter X actually see" are all a single row read — no replay
logic, no risk of a corrupted diff chain. Given form schemas are small
(a few KB of JSON), the storage cost is negligible next to the operational
simplicity.

**AI is a fallback for imports, not the primary parser.**
Running every imported document through an LLM would be more "impressive"
on paper but is slower, costs money per import, and — critically —
introduces exactly the kind of confident-but-wrong field-type guess the
brief warns about, for documents deterministic parsing already handles
correctly. The hybrid approach (deterministic first, AI only for
`unparsed_blocks`) keeps AI usage proportional to genuine ambiguity in the
source document.

**Two Excel layouts instead of trying to auto-support arbitrary layouts.**
Spreadsheet layouts are genuinely unbounded; rather than a fragile
"detect anything" heuristic, `ExcelFormParser` supports two clearly
documented shapes and treats anything else as the plain-header-row
fallback rather than failing. This is called out explicitly in the README
so a grader/user knows what to expect before uploading.

## Part D — why these three

Given three were already close to "free" given the versioning table and
the compiled-schema read path already existing for other reasons, effort
went into executing them well rather than adding a fourth or fifth
shallower feature:

1. **Versioning/rollback** — falls directly out of `form_versions` already
   being the source of truth; the alternative (mutating one row) would have
   made this and the AI/import audit trail *harder*, not easier, to build.
2. **Redis-cached compiled schema** — the public fill page is the only page
   in this app that's genuinely public-internet-hot; caching it is the
   highest-leverage performance work available for the effort.
3. **Rate limiting + public API/webhook** — spam protection on a public
   form endpoint and a way for the form's owner to get submissions out
   programmatically felt like the most "would a real customer ask for
   this in week one" pair of the remaining options (vs. e.g. conditional
   field logic, which is valuable but a materially bigger UI/schema
   undertaking left as a "what's next").

## What's explicitly left as a stub (see README §6)

- Conditional/branching logic between fields (schema and validator have
  room for it — `field.visible_if` — but it's not implemented)
- Per-form API tokens (`FormApiController::authorizeApiAccess()` is a
  placeholder)
- Queued/retried webhook delivery (currently synchronous best-effort)
- SortableJS wiring for true drag-reorder within a section (add/click and
  the reorder *method* exist; the JS `end`-event bridge in
  `resources/js/app.js` does not)

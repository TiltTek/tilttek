# Exceptions

Every exception of the library implements `Exception\PredictionsException`: catch this one to catch them all.

| Exception | Thrown when | Message |
|---|---|---|
| `QuestionClosed` | A prediction once the question is closed, a cancellation once it is resolved, a resolution once it is cancelled or resolved otherwise, a settlement before it is resolved | `The question "…" takes no more predictions.`, `The question "…" is resolved: it can no longer be cancelled or resolved otherwise.`, `The question "…" is cancelled: it can no longer be resolved.`, `The question "…" is not resolved yet: there is nothing to settle.` |
| `UnknownQuestion` | No question is asked under the key | `No question is asked under the key "…".` |
| `InvalidPrediction` | A key, an outcome, a pick, points, a setting or a read out of bounds; a pick that does not fit its question; a question asked again differently or closing in the past | What is out of bounds, and the bound |

`QuestionClosed` and `UnknownQuestion` extend `DomainException`; `InvalidPrediction` extends
`InvalidArgumentException`.

The exceptions of `tilttek/points` reach the caller unchanged: `MovementConflict` when a prediction's key is written in
the ledger for other points.

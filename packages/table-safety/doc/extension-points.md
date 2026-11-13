# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Keep the data elsewhere | Implement the four contracts of [Writing a store](writing-a-store.md) |
| Push the signals to the screens | Implement `SignalObserver` and give it to `TableSafety` |
| Use other themes | Give `TableSafety` another `Catalogue` of `phil/content-warnings` |
| Change what a table uses by default | Give `TableSafety` the `TableSettings` of a table that chose nothing |
| Offer other templates | Build a `Template` of your questions and keep it in the table's settings |
| Filter content by what a table agreed | Give `TableAgreement::preferences()` to the `WarningMatcher` of `phil/content-warnings` |

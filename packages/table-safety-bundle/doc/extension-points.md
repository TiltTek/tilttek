# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Say who takes part in a table and who runs it | Implement `TableAccess` and name it in `table_access` |
| Keep the data elsewhere | Alias a contract of `tilttek/table-safety` — `TableStore`, `ParticipationStore`, `SignalStore`, `FeedbackStore` — to your service |
| Push the signals elsewhere | Alias `SignalObserver` to your service and give `TableSafety` a reference to it |
| Use other themes | Configure the catalogue of `phil/content-warnings-bundle` |
| Change a screen | Override the template under `templates/bundles/TilttekTableSafetyBundle/` |
| Change the texts | Override the translations of the domain `tilttek_table_safety` |
| Name the tools on an announcement | Add `SafetyToolsType` to your form |
| Print the cards on another page size, in another font | Register `SafetyCardsPdf` yourself with a `PageSize` of `phil/paged-media` and a TrueType font and its bold |
| Lay out the printed page | Style the sections `.tilttek-table-safety__card` in your print stylesheet, or override `cards.html.twig` |

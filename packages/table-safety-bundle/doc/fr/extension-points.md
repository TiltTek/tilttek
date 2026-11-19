<!-- phil-dev source-sha256=41df0025b589d7689957d4dd95882a4579177d2fc07a1ea2dbb716bbb7050d4f -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Dire qui participe à une table et qui la mène | Implémentez `TableAccess` et nommez-la dans `table_access` |
| Garder les données ailleurs | Faites un alias d'un contrat de `tilttek/table-safety` — `TableStore`, `ParticipationStore`, `SignalStore`, `FeedbackStore` — vers votre service |
| Diffuser les signaux ailleurs | Faites un alias de `SignalObserver` vers votre service et donnez à `TableSafety` une référence vers lui |
| Utiliser d'autres thèmes | Configurez le catalogue de `phil/content-warnings-bundle` |
| Changer un écran | Remplacez le gabarit sous `templates/bundles/TilttekTableSafetyBundle/` |
| Changer les textes | Remplacez les traductions du domaine `tilttek_table_safety` |
| Nommer les outils dans une annonce | Ajoutez `SafetyToolsType` à votre formulaire |
| Imprimer les cartes sur un autre format de page, dans une autre police | Enregistrez vous-même `SafetyCardsPdf` avec un `PageSize` de `phil/paged-media`, une police TrueType et sa version grasse |
| Mettre en page la page imprimée | Stylez les sections `.tilttek-table-safety__card` dans votre feuille de style d'impression, ou remplacez `cards.html.twig` |

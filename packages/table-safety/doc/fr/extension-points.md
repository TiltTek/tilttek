<!-- phil-dev source-sha256=2b575d6521c3795b11b3d67d26bbe33018f605582e3c62ac2918621d3b9dda0e -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Garder les données ailleurs | Implémentez les quatre contrats de [Écrire un stockage](writing-a-store.md) |
| Diffuser les signaux aux écrans | Implémentez `SignalObserver` et donnez-le à `TableSafety` |
| Utiliser d'autres thèmes | Donnez à `TableSafety` un autre `Catalogue` de `phil/content-warnings` |
| Changer ce qu'une table utilise par défaut | Donnez à `TableSafety` les `TableSettings` d'une table qui n'a rien choisi |
| Offrir d'autres gabarits | Construisez un `Template` de vos questions et gardez-le dans les réglages de la table |
| Filtrer le contenu selon ce qu'une table a convenu | Donnez `TableAgreement::preferences()` au `WarningMatcher` de `phil/content-warnings` |
| Montrer plus ou moins de réponses à la fois | Donnez à `TableSafety` un `answersThreshold` pour l'entente et le pacte, un `feedbackThreshold` pour les étoiles et les souhaits |

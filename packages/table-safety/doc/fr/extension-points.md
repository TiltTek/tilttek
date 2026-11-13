<!-- phil-dev source-sha256=63c15a89bed28bd48f335beda6bac7ab3b294a786f49b1839186d068d4b13c42 -->
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

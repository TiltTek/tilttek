<!-- phil-dev source-sha256=e4437ac86d45fbf0da0639b3b53d69cb7454abe4c157803cbde5843e5fa344dc -->
# Installation

```sh
composer require tilttek/table-safety
```

La bibliothèque exige PHP 8.5 ou une version plus récente avec `ext-mbstring` et `ext-sodium`, `phil/content-warnings`
pour les thèmes et `phil/resource` pour les références des tables et des participants. Elle n'a aucune autre
dépendance.

`TableSafety` reçoit un catalogue de thèmes — `BaseCatalogue::load()` ou celui de l'application —, les quatre
stockages de [Écrire un stockage](writing-a-store.md), et, facultativement, un observateur des signaux et les réglages
d'une table qui n'a rien choisi. Les stockages en mémoire servent aux tests ; une application garde les réponses dans
une base de données, comme le fait `tilttek/table-safety-bundle` avec Doctrine DBAL.

<!-- phil-dev source-sha256=7351ad8dabe7c7b1e379362617715565eb1a9154ce07e59625de5dbf86b41959 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/rules-lookup` seront consignés dans ce fichier.

Les mises à jour devraient suivre les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-27

### Ajouté

- `RuleId` et `Rule` : des règles identifiées par leur système, leur clé et leur version — écrites
  `srd-5.2@5.2.1:condition.blinded` —, avec des titres et des textes par locale, une catégorie, au plus 64 renvois à des
  règles du même système et de la même version, et la provenance de `tilttek/rpg-systems` qui nomme leur source, leur
  licence et leur attribution.
- `RuleQuery` : une recherche par mots, locale, système, version et catégorie, une page de 1 à 100 résultats ; les mots
  ramenés sans casse ni accents — NFKD, signes diacritiques retirés, minuscules, NFC — par le `Normalizer` d'`intl` ou
  de `symfony/polyfill-intl-normalizer`, `intl` étant suggéré ; au plus 200 caractères et 16 mots.
- `Contract\RuleStore` et `Store\InMemoryRuleStore` : le port du stockage, et un stockage en mémoire d'au plus 100 000
  règles qui indexe leurs mots ramenés une fois et ne change plus ensuite ; une recherche qui lit le texte que voit un
  lecteur de la locale, le dernier mot valant pour les mots qu'il commence, notée selon l'endroit où chaque mot est
  trouvé, la meilleure en premier, les égalités départagées par le titre puis par l'identifiant.
- `RulesLookup`, `SearchResults` et `RuleHit` : la recherche, une règle par son identifiant, les règles auxquelles elle
  renvoie et celles qui renvoient à elle, chaque résultat avec la mention de sa source, et une page avec les mentions de
  ses sources en texte ou en liste HTML échappée.
- `RulesFromSystem` : les termes, les attributs et les jets nommés d'un système de `tilttek/rpg-systems` en règles,
  chacun avec la source de sa fiche.
- `Message\ReindexRules` : le message d'un système dont les règles ont changé, avec sa clé et sa version seulement.
- Des exceptions sous une seule interface `RulesLookupException` : `InvalidRule` et `InvalidQuery`.
- Une cible de fuzzing des requêtes et des bancs d'essai de la construction d'un stockage et de sa recherche.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.

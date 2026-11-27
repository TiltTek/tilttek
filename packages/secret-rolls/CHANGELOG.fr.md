<!-- phil-dev source-sha256=f15e81d9923c5a90b17c24f59f40debcd08659128c4096a25b99626403af70a3 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/secret-rolls` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.2.3-alpha - 2026-11-24

### Modifié

- `tilttek/dice` 0.3 requis : 0.2 n'est plus accepté.

## 0.2.2-alpha - 2026-11-20

### Modifié

- `tilttek/dice` 0.2 ou 0.3 requis : 0.1 n'est plus accepté.

## 0.2.1-alpha - 2026-11-17

### Modifié

- `tilttek/dice` 0.3 accepté, tout comme 0.1 et 0.2.

## 0.2.0-alpha - 2026-11-14

### Ajouté

- `JournalHead` : où en est le journal d'une scène sans ses entrées — sa longueur, l'empreinte de sa dernière entrée,
  s'il est fermé —, et la tête une fois une entrée ajoutée.

### Modifié

- `SecretRollStore` reçoit `head()`, que le stockage tient à jour à mesure qu'il ajoute ; un stockage de l'application
  l'implémente.
- `SecretRolls::roll()` et `close()` lisent la tête du journal au lieu du journal entier, pour qu'un lancer ne coûte
  plus davantage à mesure que la scène grandit ; le journal, sa chaîne d'empreintes et ses engagements ne changent pas.
- `tilttek/dice` 0.2 accepté, tout comme 0.1.

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `SecretRolls` : des lancers cachés scène par scène, lancés avec `tilttek/dice` à partir d'un germe de 32 octets tiré
  d'une source sûre, l'identifiant du lancer — dérivé de sa scène et de sa position — servant de germe du client ;
  l'ouverture scellée dans le stockage et son engagement ajouté au journal public de la scène ; des révélations dans
  l'ordre donné, chaque lancer une seule fois, rien d'ajouté quand un lancer est refusé ; la fermeture d'une scène avec
  une révélation partielle, vide ou totale, les lancers non révélés restant retenus et révélables plus tard ; une scène
  pleine à 4 999 entrées.
- `HiddenRoll`, `RollOpening` et `RollCommitment` : le lancer scellé avec la personne qui lance et son heure, son
  ouverture — expression sous forme canonique, arrondi, germe, résultat et contexte —, et le SHA-256 du JSON canonique
  de l'ouverture, comparé en temps constant ; ouvertures et lancers cachés écrits et relus en valeurs simples, un champ
  inconnu refusé.
- `RollVerifier` : une ouverture vérifiée contre l'engagement publié, son expression lue dans les limites des dés et
  sous forme canonique, et relancée à partir de son germe jusqu'au résultat révélé ; `verifyPublished()` pour les
  données telles que publiées.
- `Journal\SceneJournal`, `Journal\JournalEntry` et `Journal\EntryKind` : le journal public d'une scène, chaque entrée
  enchaînée à la précédente par son SHA-256 ; son état, ses ouvertures révélées et ses lancers retenus ; `problems()`,
  chaque défaut d'un journal en une phrase ; l'exportation JSON qu'un joueur télécharge et vérifie seul, relue dans les
  limites de 32 Mio et de 64 niveaux.
- `Contract\SecretRollStore` et `Contract\JournalPublisher`, avec `Store\InMemorySecretRollStore` et
  `Store\NoPublisher` : les journaux et les lancers scellés gardés par l'application, une entrée mal placée refusée
  comme écriture concurrente, chaque entrée poussée vers la table.
- `Exception\SecretRollsException`, avec `InvalidSecretRoll`, `SecretRollRefused` et `JournalConflict`.
- Des cibles de fuzzing pour la vérification d'une ouverture et pour un journal publié, et des bancs d'essai d'un
  lancer, d'une vérification et de la vérification d'une scène de 500 lancers.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.

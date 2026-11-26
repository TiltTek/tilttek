<!-- phil-dev source-sha256=981de0c883d6a065951ac1b2c649caa17d17e1851532967d9f725d64502ed855 -->
# Feuilles de partie

## La vie d'une feuille

`ScoreKeeper` fait vivre les feuilles d'un magasin, une par partie, et ne garde rien entre deux appels.

| Méthode | De | Vers |
|---|---|---|
| `prepare(MatchId, SheetDefinition, Entries)` | — | Préparée : cachée aux joueurs |
| `publish(MatchId, int $version)` | Préparée | Publiée : montrée, ouverte aux saisies |
| `record(MatchId, int $version, ParticipantId, string $category, ?int $value)` | Préparée ou publiée | La même ; null efface la valeur |
| `close(MatchId, int $version)` | Publiée | Fermée : le résultat est transmis |
| `reopen(MatchId, int $version)` | Fermée | De nouveau publiée, pour la corriger |

Une feuille préparée accepte des valeurs saisies d'avance — des handicaps, un score reporté d'une première manche —
pendant que les joueurs ne la voient pas ; `MatchSheet::isVisible()` dit s'ils peuvent la voir. Une feuille fermée
n'accepte aucune valeur tant qu'elle n'est pas rouverte. Un changement depuis tout autre statut est refusé avec
`IllegalTransition`.

`record()` retourne le tableau des scores recalculé ; `scoreboard()` le calcule à tout moment, `sheet()` retourne la
feuille enregistrée.

## Un score saisi trop tôt

Une feuille fermée avant que le score soit juste est rouverte, corrigée et fermée de nouveau. Chaque fermeture compte
une révision de plus : le premier résultat est la révision 1, le corrigé la révision 2, et
`MatchResult::isCorrection()` dit qu'il remplace le précédent ([Transmettre les résultats](results.md)).

## Versions

Une `MatchSheet` a une version, augmentée de un à chaque changement. Un changement se fait contre la version que le
pointeur a vue, et `ConcurrentChange` le refuse quand un autre changement a été enregistré avant : deux pointeurs qui
corrigent la même valeur en même temps obtiennent un changement et un refus.

## Le port du magasin

`Contract\MatchSheetStore` a deux méthodes : `find(MatchId): ?MatchSheet`, et `save(MatchSheet)`, qui n'écrit la feuille
que par-dessus la version qui la précède — aucune pour la version 0 — et lève `ConcurrentChange` autrement. La feuille
contient la déclaration, les saisies, le statut, la révision et la version ; les scores ne sont jamais enregistrés,
ils sont calculés.

## Un magasin à vous

Un magasin sur une base de données garde une ligne par partie, avec `SheetDefinition::toArray()` et
`Entries::toArray()` en JSON, le statut, la révision, et la version dans une colonne que la mise à jour vérifie :

```sql
UPDATE match_sheet SET sheet = :sheet, entries = :entries, status = :status, revision = :revision, version = :version
WHERE id = :id AND version = :version - 1
```

Aucune ligne mise à jour signifie `ConcurrentChange`. Il relit la ligne avec `SheetDefinition::fromArray()` et
`Entries::fromArray()`, qui vérifie de nouveau chaque valeur contre la feuille.

`Store\InMemoryMatchSheetStore` est le magasin pour les tests.

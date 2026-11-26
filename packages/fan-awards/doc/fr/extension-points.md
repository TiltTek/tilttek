<!-- phil-dev source-sha256=c1a6d4f8907fbbde401a0d494ed38cdc1324f097ec394e6ec207b9cb8a3d74e3 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que l'application fournit | Valeur par défaut |
|---|---|---|
| `Contract\AwardStore` | Où l'on garde les éditions, les candidats, les bulletins et les décomptes | `Store\InMemoryAwardStore` dans les tests |
| `Contract\NomineeEligibility` | Si un candidat peut se présenter dans une catégorie, et pourquoi non | `Eligibility\AnyNominee` |
| `Contract\VoterEligibility` | Si un membre peut voter dans une édition | `Eligibility\AnyVoter` |
| `Phil\Poll\CountingMethods` | Les méthodes de dépouillement, dont une méthode classée qui lui est propre | `CountingMethods::defaults()` |
| `Psr\Clock\ClockInterface` | Le moment où chaque phase commence | — |

## Écrire un magasin

Un `AwardStore` garde :

- les éditions par clé — `edition()` et `saveEdition()`, qui remplace l'édition de même clé ;
- les candidats de chaque catégorie et de chaque phase, dans l'ordre où on les a ajoutés — `candidates()` et
  `addCandidate()` ;
- un bulletin par votant, par catégorie et par phase — `ballot()` et `saveBallot()`, qui remplace le précédent — et
  chaque bulletin d'une catégorie et d'une phase, ceux des votants effacés compris, lu une fois par le dépouillement
  avec `ballots()`, qui peut les lire en flux depuis une table ;
- le décompte de chaque phase — `saveCount()` et `counts()`, dans l'ordre des phases ;
- `forget($voter)`, qui détache le votant de ses bulletins et garde les bulletins.

Les votants sont désignés par leurs références écrites `kind:identifier` ; un bulletin est la liste des clés de ses
candidats, dans l'ordre du votant.

## Admissibilité

`NomineeEligibility::refusal()` retourne null pour un candidat admissible, ou la raison pour laquelle il ne l'est pas,
qui parvient au votant. `VoterEligibility::mayVote()` répond à partir des propres données du serveur. Ni l'une ni
l'autre ne garde d'état d'un appel à l'autre. Ni l'une ni l'autre ne peut lier un vote à un paiement ou à un achat.

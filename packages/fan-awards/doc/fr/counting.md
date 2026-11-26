<!-- phil-dev source-sha256=920818e079aedb7756492b6549fab31098a87458c0512545e3b7aab2f53baa3e -->
# Dépouillement et résultats

Chaque décompte est fait par `phil/poll` quand `advance()` ferme une phase de vote ; la bibliothèque dit seulement
quels candidats passent au tour suivant.

## Les candidatures et la demi-finale

Leurs bulletins sont dépouillés par le décompte des choix, `choices` dans `phil/poll`, sur les candidats de la phase.
La présélection retient les candidats classés dans la taille de la phase suivante — les `semifinalists` de la catégorie
pour la demi-finale, ses `finalists` pour la finale — qui ont reçu au moins un vote. Les candidats à égalité à la limite
passent tous, puisque `phil/poll` donne la même place aux candidats à égalité : une présélection peut être plus longue
que sa taille, jamais plus courte à cause d'une égalité. À l'intérieur d'une place, le classement garde d'abord le
score le plus élevé, puis l'ordre d'arrivée des candidats.

## La finale

Les options de la finale sont ses finalistes, puis `no-award` quand la catégorie l'offre. Ses bulletins sont dépouillés
par la méthode classée de la catégorie : `instant_runoff`, `borda`, `schulze`, ou une méthode classée propre à
l'application.

| Issue | Gagnants | `noAward` |
|---|---|---|
| Des candidats seuls en première place | Ces candidats, plusieurs en cas d'égalité | false |
| « Aucun prix » seul en première place | Aucun | true |
| « Aucun prix » à égalité avec des candidats en première place | Les candidats à égalité | false |
| Aucun bulletin, ou seulement des abstentions | Aucun | false |

Une phase dont la catégorie offre moins de deux options ne prend aucun bulletin et ne compte rien : ses candidats
passent tels quels, et une finale avec un seul finaliste et sans « aucun prix » donne le prix à ce finaliste.

## Résultats

`preview($edition, $category)` lit les résultats une fois l'édition dépouillée, pour les organisateurs seulement :
l'application garde qui l'appelle. `results()` lit les mêmes une fois l'édition annoncée, pour tout le monde ; avant,
il est refusé avec `Refusal::NotAnnounced`, et `preview()` avant le dépouillement avec `Refusal::NotCounted`.

Un `Results` contient la catégorie, les gagnants, `noAward` et le `PhaseCount` de chaque phase dans l'ordre —
`count($phase)` en trouve un. Un `PhaseCount` contient les candidats que la phase a offerts, le `Result` de
`phil/poll` — ses classements, ses scores et ses places, et les tours d'un second tour instantané — et les candidats
qu'elle a fait passer. Publier ces décomptes détaillés après la cérémonie permet à chaque fan de vérifier comment
chaque phase s'est déroulée.

## Effacement

`erase($voter)` oublie le votant sur chaque bulletin qu'il a déposé : les bulletins restent, anonymes, de sorte qu'un
décompte fait après l'effacement donne le même résultat qu'avant.

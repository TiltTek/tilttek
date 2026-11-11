<!-- phil-dev source-sha256=4ae2b0903ea1306bb6a5b2fb5d90981e16f7d861d2f7268268b1f7cfe3288fc3 -->
# Badges et critères

## Un badge

`Badge` contient :

| Champ | Sens |
|---|---|
| `code` | De 1 à 64 lettres minuscules, chiffres, `_`, `.` ou `-`, en commençant par une lettre : `commenter.gold` |
| `name`, `description` | Des textes, ou des clés de traduction que traduit l'application ; un nom de 1 à 255 caractères, une description de 1 020 au plus |
| `image` | L'emplacement de son image, tel que l'écrit l'application ; `null` s'il n'y en a pas |
| `criterion` | Ce qui le fait gagner |
| `repeatable` | Gagné de nouveau chaque fois que sa cible est de nouveau atteinte : 23 commentaires pour un badge de 10 font deux attributions |
| `hidden` | Un succès secret : absent de la vue d'ensemble d'un membre, critère compris, jusqu'à ce qu'il le gagne |
| `revocable` | Retiré quand l'activité qui l'a valu est annulée |
| `tier` | Sa série et son rang : le bronze est le rang 1 de la série `commenter`, l'argent le rang 2 |

Chaque badge d'un palier a son propre critère et son propre seuil ; `Catalog::series('commenter')` les énumère par
rang.

## Critères

Un critère est un arbre. Ses feuilles lisent l'activité — chaque activité qu'accepte une feuille lui apporte une
`Contribution` — et une combinaison lit la progression de ses enfants.

| Critère | Exemple | Progression |
|---|---|---|
| `Count` | Avoir commenté 10 nouvelles | Les activités acceptées |
| `Count` de 1 — *première fois* | Avoir publié une première fiche | 0 ou 1 sur 1 |
| `Total` | Avoir lu 1 000 pages | Les quantités des activités, additionnées |
| `Streak` | Être venu 7 jours de suite | La plus longue suite de jours comptant au moins une activité, dans le fuseau horaire du catalogue |
| `Measure` | Avoir 1 000 points | La valeur que mesure l'application, lue quand survient l'une des activités de la portée |
| `Manual` | Attribué par un administrateur | Jamais atteint par l'activité |
| `AtLeast` | Tous ceux d'une liste, ou 2 sur 3 | Les critères atteints |

Une *période* — *avoir participé pendant le mois de l'événement* — est une feuille dont la `Scope` a un `since`, inclus,
et un `until`, exclu. La `Scope` d'une feuille nomme aussi ses verbes par motif (`game.played`, `game.*`) et les faits
que doit contenir le contexte de l'activité (`system: dnd5e`).

`Measure` permet à un badge de lire une valeur que tient un autre paquet, comme un solde de points, sans en dépendre :
l'application implémente `Measures` et le passe à l'`Evaluator`. La valeur lue est la valeur actuelle, pas celle du
moment de l'activité.

## Le catalogue à partir de valeurs simples

`Catalog::fromArray()` lit les badges tels qu'une configuration les contient, et `CriterionReader` lit leurs critères :

```php
$catalog = Catalog::fromArray([
    'commenter.bronze' => [
        'name' => 'badges.commenter.bronze.name',
        'description' => 'badges.commenter.bronze.description',
        'image' => '/images/badges/commenter-bronze.svg',
        'tier' => ['series' => 'commenter', 'rank' => 1],
        'revocable' => true,
        'criterion' => ['count' => ['verbs' => ['news.commented'], 'times' => 10]],
    ],
    'reader' => ['repeatable' => true, 'criterion' => ['total' => ['verbs' => ['book.read'], 'amount' => 1000]]],
    'regular' => ['criterion' => ['streak' => ['verbs' => ['site.visited'], 'days' => 7]]],
    'event' => ['criterion' => ['first' => ['verbs' => ['event.*'], 'since' => '2026-12-01', 'until' => '2027-01-01']]],
    'rich' => ['criterion' => ['measure' => ['name' => 'points', 'threshold' => 1000, 'verbs' => ['game.*']]]],
    'organiser' => ['criterion' => ['manual' => true]],
    'all-round' => ['criterion' => ['at_least' => ['count' => 2, 'of' => [
        ['first' => ['verbs' => ['game.played']]],
        ['first' => ['verbs' => ['news.commented']]],
        ['first' => ['verbs' => ['sheet.published'], 'context' => ['system' => 'dnd5e']]],
    ]]]],
], 'America/Toronto');
```

Le fuseau horaire est celui dans lequel se lisent les jours d'une suite et les dates d'une période. Un nom omis est le
code. Chaque erreur est refusée par une `InvalidBadge` qui dit ce qui ne va pas : un code, une sorte de critère, un
verbe, une valeur de contexte, une date, un rang donné deux fois, un critère imbriqué si profondément qu'un chemin
dépasse 64 octets.

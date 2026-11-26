<!-- phil-dev source-sha256=25fb06e54328d84e50763b25fd5026ecc87e95b7199cf3fe373598777e73e85d -->
# Bris d'égalité

## Classement

Le classement d'une étape suisse, des poules ou d'une poule range les participants par points, puis par les bris
d'égalité que le tournoi déclare, dans l'ordre. Chaque bris d'égalité divise le groupe encore à égalité, et le suivant
est calculé sur chaque groupe plus petit qui reste ; une égalité qui reste à la fin est départagée par le tirage de la
graine publiée. Chaque `Standing` contient le rang, les points, le score de chaque bris d'égalité calculé pour le
participant, par code, si le tirage a eu le dernier mot — `drawn` —, et si le participant s'est retiré.

Une élimination classe selon la ronde que chaque participant a atteinte, le champion d'abord, puis selon la tête de
série.

## Les bris d'égalité de la bibliothèque

| Code | Score |
|---|---|
| `buchholz` | La somme des points de chaque adversaire avec qui le participant a été apparié, joué ou perdu par forfait. Une exemption n'ajoute rien. |
| `buchholz-cut-1`, `buchholz-cut-2` | Le Buchholz sans le ou les deux plus bas scores des adversaires, pour qu'un adversaire faible tombé par hasard pèse moins. |
| `sonneborn-berger` | Pour chaque match, les points de l'adversaire multipliés par les points que le participant y a marqués : une victoire contre un adversaire fort pèse le plus. |
| `head-to-head` | Les points marqués dans les matchs entre les participants encore à égalité, ceux-là seulement ; recalculés sur chaque groupe plus petit. |
| `wins` | Les matchs gagnés sur l'échiquier ; les exemptions et les victoires par forfait sont exclues. |
| `mission-points` | La somme des points de mission marqués, comme les jeux de figurines les comptent en plus de la victoire. |

Avec les points du pointage en nombres entiers, le score Sonneborn-Berger est le score classique multiplié par les
points d'une victoire : le même ordre.

```php
new Tournament('cup', 'cup-2027', $participants, [Stage::swiss('swiss', 5)], tiebreakers: [
    'buchholz-cut-1',
    'buchholz',
    'sonneborn-berger',
    'head-to-head',
]);
```

Un tournoi déclare jusqu'à huit bris d'égalité, chacun une seule fois ; un code inconnu est refusé au démarrage du
tournoi.

## Le dernier tirage

Le tirage ordonne les participants encore à égalité selon le SHA-256 de la graine et du participant, le plus petit
d'abord. Il est le même chaque fois, et quiconque détient la graine publiée le calcule : il ne dépend jamais d'un hasard
qu'on ne peut pas rejouer. Le même tirage ordonne les participants avant la première ronde quand aucune source de
classement initial ne donne leurs forces.

## Un bris d'égalité bien à vous

Implémentez `Contract\Tiebreaker` — un code stable, et un score pour chaque participant d'un groupe à égalité, le plus
haut d'abord — et donnez-le à `Tiebreakers` :

```php
$tournaments = new Tournaments($store, new Tiebreakers(new GamesWonWithoutLoss()));
```

Le groupe est donné en entier, avec les `Records` de l'étape, pour qu'un bris d'égalité qui ne lit que les matchs à
l'intérieur du groupe soit recalculé sur chaque groupe plus petit. Un code compte de 1 à 64 lettres minuscules,
chiffres ou `-`, commence par une lettre, et n'est le code d'aucun autre bris d'égalité.

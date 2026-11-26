<!-- phil-dev source-sha256=93a4b22806ab74062744547544381cf46fc5c5175f31cdb555f5355a7bd60089 -->
# Données

`data/events.php` contient les événements fournis, une ligne par entrée, et `data/VERSION` le jour de leur dernier
changement, comme `2026.11.21`. Une ligne a les champs que lit `EntryReader` : `id`, `names`, `rule`, `source`,
`community`, `verified`, et facultativement `tags`, `days`, `status` et `prompts`.

## Événements

Chaque règle ci-dessous a été vérifiée contre sa source le 2026-11-21.

| Identifiant | Règle | Communauté | Étiquettes |
|---|---|---|---|
| `gm-day` | `03-04` | `tabletop-roleplaying` | game-masters, appreciation |
| `rpgaday` | `08-01`, 31 jours, une invite par jour | `tabletop-roleplaying` | daily-prompts, writing |
| `nagademon` | `11-01`, 30 jours | `game-designers` | game-design, challenge |

Le Jour du MJ remercie les meneurs de jeu ; sa date répond à l'appel « march forth ». RPGaDAY est un mois d'invites
quotidiennes sur le jeu de rôle, publiées chaque année par ses hôtes : le paquet donne l'indice du jour, jamais les
invites. Le Mois national de la conception de jeux demande aux concepteurs d'écrire et de jouer un jeu en novembre.

## Sources

La règle de chaque événement vient de l'adresse de son champ `source` ; les noms sont ceux du paquet, et aucun texte
d'une source n'est copié.

| Événement | Source |
|---|---|
| `gm-day` | <https://sjgames.com/ill/archive/February_29_2012> |
| `rpgaday` | <https://www.autocratik.com/> |
| `nagademon` | <https://itch.io/jam/national-game-design-month-2025> |

## Ce que les données excluent

- **Les congrès.** Un congrès fixe sa date chaque année, souvent sans règle : l'application ajoute les congrès qu'elle
  connaît, comme ci-dessous.
- **Les événements dont l'organisateur fixe la date chaque année.** Une journée de jeu annuelle et gratuite tenue un
  samedi différent d'une année à l'autre n'a aucune règle à fournir ; l'application ajoute la date de l'année comme une
  entrée d'un jour.
- **Les invites d'un mois thématique.** Elles appartiennent à leurs auteurs : l'application écrit les siennes, ou montre
  une liste qu'elle a le droit de montrer, selon l'indice que donne `promptDays()`.
- **Les « fêtes nationales » commerciales, et une date qu'une marque déclare à propos d'elle-même.**
- **Les noms et logos de marques comme marque du paquet.** Un nom n'apparaît que là où il dit de quel événement il
  s'agit.
- **Un événement qui n'est pas sûr.** Une entrée dont la source ne confirme pas la règle est exclue plutôt que devinée.

## Ajouter un événement

Une application ajoute ses propres événements sans changer le fichier fourni :

```php
$catalog = Catalog::shipped($zone, new EntryReader()->readAll([
    [
        'id' => 'local-con',
        'names' => ['en' => 'Local convention', 'fr' => 'Convention locale'],
        'rule' => '3rd saturday of june',
        'days' => 2,
        'source' => 'https://convention.example/dates',
        'community' => 'montreal',
        'verified' => '2026-11-21',
        'tags' => ['convention'],
    ],
    [
        'id' => 'prompt-month',
        'names' => ['en' => 'Our month of prompts', 'fr' => 'Notre mois d’invites'],
        'rule' => '02-01',
        'days' => 28,
        'prompts' => true,
        'source' => 'https://club.example/prompts',
        'community' => 'our-club',
        'verified' => '2026-11-21',
    ],
]));
```

Un identifiant que le catalogue contient déjà est refusé. Vérifiez d'abord les lignes lues dans un fichier ou un
formulaire avec `new DataCheck()->problems($rows)`, qui liste chaque problème au lieu de s'arrêter au premier.

Pour proposer un événement pour les données fournies : ajoutez sa ligne à `data/events.php` dans l'ordre des dates, avec
une source qui énonce la règle, `verified` fixé au jour de la vérification, et `data/VERSION` fixé à ce jour ; ajoutez-le
ensuite aux tableaux ci-dessus. Le test des données lit chaque ligne, calcule chaque événement sur des années ordinaires
et bissextiles, et échoue au moindre problème.

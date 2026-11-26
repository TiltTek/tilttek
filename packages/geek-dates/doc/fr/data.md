<!-- phil-dev source-sha256=8b31128c9965bca2d948d706d4e8ab08cc13720d13223bdaf0b235b1a09d0fc9 -->
# Données

`data/dates.php` contient les entrées fournies, une ligne par entrée, et `data/VERSION` le jour de leur dernier
changement, comme `2026.11.21`. Une ligne a les champs que lit `EntryReader` : `id`, `names`, `rule`, `source`,
`community`, `verified`, et facultativement `tags`, `days` et `status`.

## Entrées

Chaque règle ci-dessous a été vérifiée contre sa source le 2026-11-21.

| Identifiant | Règle | Communauté | Étiquettes |
|---|---|---|---|
| `tolkien-birthday-toast` | `01-03` | `tolkien-readers` | books, fantasy |
| `pi-day` | `03-14` | `worldwide` | mathematics, science |
| `tolkien-reading-day` | `03-25` | `tolkien-readers` | books, fantasy |
| `world-backup-day` | `03-31` | `worldwide` | computing |
| `yuris-night` | `04-12` | `worldwide` | space, science |
| `may-the-fourth` | `05-04` | `star-wars-fans` | films, science-fiction |
| `towel-day` | `05-25` | `douglas-adams-readers` | books, science-fiction, humour |
| `geek-pride-day` | `05-25` | `worldwide` | geek-culture |
| `tau-day` | `06-28` | `worldwide` | mathematics, science |
| `asteroid-day` | `06-30` | `worldwide` | space, science |
| `sysadmin-day` | `last friday of july` | `worldwide` | computing |
| `programmers-day` | `day 256` | `worldwide` | computing |
| `software-freedom-day` | `3rd saturday of september` | `worldwide` | computing, free-software |
| `talk-like-a-pirate-day` | `09-19` | `worldwide` | humour |
| `hobbit-day` | `09-22` | `tolkien-readers` | books, fantasy |
| `world-space-week` | `10-04`, 7 jours | `worldwide` | space, science |
| `ada-lovelace-day` | `2nd tuesday of october` | `worldwide` | science, computing, women-in-science |
| `mole-day` | `10-23` | `chemistry-teachers` | chemistry, science |

`day 256` est le 13 septembre, ou le 12 septembre d'une année bissextile. `may-the-fourth` porte le nom du jeu de mots
des fans, le jour qu'ils gardent, et non celui de la marque.

## Sources

La règle de chaque entrée vient de l'adresse de son champ `source` ; les noms sont ceux du paquet, et aucun texte d'une
source n'est copié. Les sources sont :

| Entrées | Source |
|---|---|
| `tolkien-birthday-toast`, `tolkien-reading-day` | <https://en.wikipedia.org/wiki/The_Tolkien_Society> |
| `pi-day` | <https://en.wikipedia.org/wiki/Pi_Day> |
| `world-backup-day` | <https://en.wikipedia.org/wiki/World_Backup_Day> |
| `yuris-night` | <https://en.wikipedia.org/wiki/Yuri%27s_Night> |
| `may-the-fourth` | <https://en.wikipedia.org/wiki/Star_Wars_Day> |
| `towel-day` | <https://en.wikipedia.org/wiki/Towel_Day> |
| `geek-pride-day` | <https://en.wikipedia.org/wiki/Geek_Pride_Day> |
| `tau-day` | <https://en.wikipedia.org/wiki/Tau_Day> |
| `asteroid-day` | <https://en.wikipedia.org/wiki/Asteroid_Day> |
| `sysadmin-day` | <https://en.wikipedia.org/wiki/System_Administrator_Appreciation_Day> |
| `programmers-day` | <https://en.wikipedia.org/wiki/Programmers%27_Day> |
| `software-freedom-day` | <https://en.wikipedia.org/wiki/Software_Freedom_Day> |
| `talk-like-a-pirate-day` | <https://en.wikipedia.org/wiki/International_Talk_Like_a_Pirate_Day> |
| `hobbit-day` | <https://en.wikipedia.org/wiki/Hobbit_Day> |
| `world-space-week` | <https://en.wikipedia.org/wiki/World_Space_Week> |
| `ada-lovelace-day` | <https://en.wikipedia.org/wiki/Ada_Lovelace_Day> |
| `mole-day` | <https://en.wikipedia.org/wiki/Mole_Day> |

## Ce que les données excluent

- **Les « fêtes nationales » commerciales.** Les listes d'un service de calendrier sont son propre contenu, souvent
  promotionnel : aucune n'est copiée.
- **Une date qu'une marque déclare à propos d'elle-même.** Le jour qu'une entreprise consacre à son propre produit est
  son marketing, non une date de la communauté.
- **Les noms et logos de marques comme marque du paquet.** Un nom n'apparaît que là où il dit de quel jour il s'agit.
- **Les dates de congrès.** Un congrès fixe sa date chaque année : l'application l'ajoute, comme ci-dessous.
- **Une date qui n'est pas sûre.** Une entrée dont la source ne confirme pas la règle est exclue plutôt que devinée.

## Ajouter une entrée

Une application ajoute ses propres entrées sans changer le fichier fourni :

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
        'status' => 'to-verify',
    ],
]));
```

Un identifiant que le catalogue contient déjà est refusé. Vérifiez d'abord les lignes lues dans un fichier ou un
formulaire avec `new DataCheck()->problems($rows)`, qui liste chaque problème au lieu de s'arrêter au premier.

Pour proposer une entrée pour les données fournies : ajoutez sa ligne à `data/dates.php` dans l'ordre des dates, avec
une source qui énonce la règle, `verified` fixé au jour de la vérification, et `data/VERSION` fixé à ce jour ; ajoutez-la
ensuite aux tableaux ci-dessus. Le test des données lit chaque ligne, calcule chaque entrée sur des années ordinaires
et bissextiles, et échoue au moindre problème.

<!-- phil-dev source-sha256=f2f6e170762ff17bdecfa621ee2c19bf5ceb4bc238d60947b1f6214fa8558fd7 -->
# Stockage

## Les journaux

`DbalLogStore` conserve les journaux des tables — la vérité de chaque table — sur une connexion Doctrine DBAL, sur
MySQL, PostgreSQL ou SQLite :

| Table | Une ligne par | Colonnes |
|---|---|---|
| `tilttek_game_engine_table` | table | `game_id`, `game`, `version`, `seed`, `seats`, `options`, `moves`, `revision` |
| `tilttek_game_engine_move` | coup | `game_id`, `number`, `type`, `seat`, `payload`, `drew` |

Les sièges, les options et les charges utiles sont du JSON dans des colonnes de texte, écrit et lu par le magasin, de
sorte qu'aucune base de données ne réordonne les clés d'un tableau associatif qu'un jeu lit dans l'ordre ; les nombres
à virgule gardent leur fraction.

La ligne d'une table est l'en-tête de son journal : la lire coûte une ligne, quel que soit le nombre de coups.
`revision` compte chaque changement du journal, chaque coup ajouté et chaque coup repris, et ne diminue jamais.

Ajouter un coup change le nombre de coups et la révision de sa table en une seule instruction qui compare les deux avec
ceux que l'appelant a lus, puis insère le coup, dans une seule transaction. De deux coups faits en même temps,
l'instruction du second ne change aucune ligne, et le coup est refusé avec `ConcurrentMove` ; il en va de même d'un coup
calculé avant qu'un coup soit repris et un autre fait à sa place, ce qui a laissé le même nombre de coups à une révision
plus tardive. La clé primaire des coups écarte un second coup d'un même numéro quoi qu'il arrive. Reprendre un coup
abaisse le nombre, augmente la révision de la même façon et supprime le coup.

Les coups sont lus fenêtre par fenêtre, `DbalLogStore::WINDOW` (256) à la fois, à mesure que la bibliothèque les demande :
un rejeu garde donc une fenêtre en mémoire plutôt que le journal entier. Un journal entier, pour son point de terminaison
ou sa vérification, est relu jusqu'au nombre de coups de sa table, lu de nouveau si sa révision a changé entre-temps,
chaque champ vérifié par la bibliothèque, une colonne qui n'est pas du JSON refusée avec `InvalidGame`.

`DbalLogStore::delete()` oublie une table et ses coups, quand l'application purge les tables qu'elle ne garde plus.

## Le cache d'états

`CacheStateCache` garde l'état courant de chaque table dans un pool PSR-6 partagé par tous les processus — `cache.app`,
ou un pool sur Valkey ou sur Doctrine DBAL —, sous une clé calculée par empreinte à partir de l'identifiant de la
table. Il garde le texte JSON de `Snapshot::toArray()` — l'état, la révision du journal dont il est construit et
l'engagement sur le germe —, jamais un objet PHP, de sorte que rien de ce que le pool redonne n'est transformé en
objet :

- un état dont le JSON ne redonnerait pas tel quel les données — un objet dans les données du jeu — n'est pas mis en
  cache du tout, et chaque lecture de cette table rejoue son journal ;
- tout ce que le pool redonne est lu par `Snapshot::fromArray()`, et tout ce qu'elle refuse, ou l'état d'une autre
  table, est oublié ;
- un instantané n'est pas écrit par-dessus celui d'une révision plus récente de la même table : un processus qui a lu
  le journal avant un changement garde pour lui son état périmé.

La bibliothèque n'emploie un état en cache que si sa révision et son germe sont ceux du journal, et met à jour un état
antérieur de la même filiation avec les coups faits depuis ; ce que le cache perd, le journal le reconstruit, quelques
microsecondes par coup. PSR-6 n'a pas de comparaison et échange. Avec `lock.factory`, le cache lit l'instantané qu'il détient et écrit
le nouveau en tenant le verrou de la clé, 5 secondes au plus, et ne l'attend jamais : un écrivain qui trouve le verrou
pris laisse le cache tel quel, de sorte qu'un état périmé n'atterrit jamais sur un plus récent, et un état laissé de
côté est reconstruit par la lecture suivante. Sans fabrique de verrous, une écriture périmée peut encore atterrir entre
la lecture et l'écriture d'un autre processus ; elle n'est jamais servie, puisque sa révision n'est pas celle du
journal, et la lecture suivante met l'état du journal en cache à sa place.

## Une lecture par requête

`GameTables` lit une table par le `Tables` de la bibliothèque, qui lit l'en-tête du journal à chaque appel et garde les
états qu'il a construits tant que cet en-tête reste le même. `GET /{table}` demande l'état, la vue d'un siège et
l'engagement sur le germe : une ligne d'en-tête pour chacun, et les coups une fois au plus, aucune quand le cache est à
jour. Le service porte l'étiquette `kernel.reset` : un *worker* lâche donc les états gardés entre deux requêtes ; un état
gardé n'est jamais employé au-delà d'un changement de son journal, même dans une seule requête.

Mesuré par `tests/Benchmark/LargeTableBench.php`, sur une table de 8 000 coups de 16 000 octets chacun, sur SQLite en
mémoire : la vue d'un siège coûte environ 71 µs et un coup environ 0,2 ms sur un état en cache, et un rejeu depuis la
mise en place environ 0,43 s avec un pic de 15 Mo, les coups lus 256 à la fois plutôt que tous d'un coup.

## Le verrou

Avec `lock.factory`, chaque coup et chaque coup repris est fait en tenant le verrou de sa table, nommé à partir de
l'empreinte de son identifiant : le second de deux coups faits en même temps attend que le premier soit entré, puis
joue contre la table telle que le premier l'a laissée — refusé comme coup concurrent seulement quand le joueur a dit
quel nombre de coups il a vu. La durée de vie du verrou, 30 secondes sauf configuration contraire, borne le temps
pendant lequel un processus mort fait attendre les autres.

Sans verrou, le magasin de journaux seul sépare deux coups : le second est refusé avec `ConcurrentMove`, et le joueur
relit la table.

## Les identifiants de siège opaques

Un identifiant de siège est un identifiant de la table, pas d'une personne : `seat:3`, `north`, `red`, jamais
`member:44` ni une adresse courriel. Le journal garde chaque siège pour toujours, chaque coup nomme son siège, et le
journal d'une table terminée, germe compris, peut être montré à quiconque veut vérifier ses tirages. L'application garde
quel membre occupe quel siège, dans une correspondance qui lui est propre :

```php
$seats = ['seat:1', 'seat:2'];
$this->seating->assign($gameId, ['seat:1' => $ann->getId(), 'seat:2' => $bob->getId()]);
$tables->open('chess', $gameId, $seats);
```

Ces mêmes identifiants opaques sont tout ce qui sort du journal : une mise à jour Mercure porte la table, ses
compteurs et les sièges qui peuvent agir ; une exception nomme la table et le siège ; un message que l'application
envoie à propos d'une table porte ces identifiants et rien d'autre, son joueur étant lu dans la correspondance par son
gestionnaire.

Effacer un membre revient alors à effacer cette correspondance : chaque journal reste rejouable, et le bundle n'a rien à
exporter ni à effacer.

`GameTables::open()` refuse, avec `IdentifyingSeat`, un siège qui semble nommer une personne :

| Refusé | Par exemple |
|---|---|
| Tout ce qui contient un `@`, brut ou encodé en `%40` | `ann@example.com` |
| Un nom à points qui se termine par un domaine courant de courriel | `ann.example.com`, `jane.doe.gmail.com` |
| `member`, `user`, `account`, `customer`, `subscriber`, `profile`, `person`, `uid`, `login`, `email` ou `mail`, au singulier ou au pluriel, suivi d'un séparateur et de n'importe quoi, ou de chiffres | `member:44`, `user-7`, `account_12`, `uid42` |

La garde attrape les erreurs usuelles, pas toutes les façons de nommer une personne : un siège tiré au hasard ou numéroté
dans la table est celui qui garde le journal libre de renseignements personnels.

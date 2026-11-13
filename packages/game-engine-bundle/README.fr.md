<!-- phil-dev source-sha256=29a6390564ea735b442831cabc08ce0fd249d39d237eac4cd155b9ec6a3243bf -->
# tilttek/game-engine-bundle

**Des tables en ligne pour Symfony sur tilttek/game-engine : des journaux sur Doctrine DBAL, des états dans un cache
partagé, un verrou par table, chaque changement poussé par Mercure, et la vue de chaque siège lue depuis le serveur.**

tilttek/game-engine-bundle fait tourner les tables de `tilttek/game-engine` dans une application Symfony. Chaque
service qui implémente le contrat `Game` de la bibliothèque est un jeu. Le journal de chaque table — la vérité de la
table, avec son germe — est conservé par Doctrine DBAL sur MySQL, PostgreSQL ou SQLite, ajouté en une seule instruction
qui refuse un coup concurrent ; l'état courant est mis en cache dans un pool PSR-6 partagé par tous les processus, sous
forme de texte JSON plutôt que d'objet PHP ; chaque coup est joué sous le verrou de sa table quand une fabrique de
verrous est nommée. Une lecture coûte la ligne de la table et les coups faits depuis son état en cache, lus fenêtre par
fenêtre et au plus une fois par requête ; un état construit avant qu'un coup soit repris n'est jamais servi.

Le serveur vérifie chaque coup et Mercure pousse : chaque changement est publié en privé sur le sujet de sa table, avec
des identifiants et des compteurs seulement, et la page de chaque joueur lit la vue de son siège depuis un point de
terminaison qui demande à l'application qui est le lecteur. Le journal d'une table, germe compris, n'est donné qu'une
fois sa partie terminée, pour que chacun vérifie chaque tirage contre l'engagement montré à l'ouverture, et aucun coup n'est repris après cela. Les sièges sont des identifiants
opaques de la table : un identifiant qui ressemble à une adresse courriel ou à une référence à un membre est refusé. Un
gabarit construit avec les recettes du kit PHIL UX et un contrôleur Stimulus affichent la table et relisent la vue à
chaque mise à jour.

La bibliothèque seule convient à une application sans Symfony.

## État

| Partie | État |
|---|---|
| `TilttekGameEngineBundle` et sa configuration `tilttek_game_engine` | Implémenté et testé |
| Les jeux rassemblés depuis le conteneur, la scène de jeu de rôle de la bibliothèque sur demande | Implémenté et testé |
| `DbalLogStore` sur MySQL, PostgreSQL et SQLite, ses tables dans le schéma généré | Implémenté et testé |
| `CacheStateCache`, les états dans un pool PSR-6 partagé sous forme de texte JSON, écrits sous le verrou de leur clé | Implémenté et testé |
| `SymfonyTableLock` et `GameTables`, les coups sous le verrou de leur table | Implémenté et testé |
| `SeatIdentifiers`, identifiants de siège opaques seulement | Implémenté et testé |
| `MercureTablePublisher`, chaque changement sur le sujet de sa table | Implémenté et testé |
| `TableController`, la vue d'un siège et le journal d'une table terminée, par le `ViewerResolver` de l'application | Implémenté et testé |
| `tilttek:game-engine:setup` et `tilttek:game-engine:verify` | Implémenté et testé |
| Le gabarit d'une table, ses fonctions Twig et le contrôleur Stimulus `game-table` | Implémenté et testé |
| La recette Flex, dans `recipe/` | Écrite |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, la recette, les tables des journaux, les jeux, les routes, le contrôleur et le kit. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Stockage](doc/fr/storage.md) | Les journaux sur Doctrine DBAL, le cache d'états, une lecture par requête, le verrou, et les identifiants de siège opaques. |
| [Tables en direct](doc/fr/live-tables.md) | Mercure, le lecteur, les points de terminaison, le gabarit et le contrôleur Stimulus. |
| [Commandes](doc/fr/commands.md) | Créer les tables des journaux et vérifier un journal. |
| [Points d'extension](doc/fr/extension-points.md) | Les services à remplacer, les étiquettes à compléter, et quand ils sont stables. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, les serveurs que lisent ses tests d'intégration, et comment tester un jeu. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque fichier du bundle. |

## Démarrage rapide

```yaml
# config/packages/tilttek_game_engine.yaml
tilttek_game_engine:
    lock: { factory: lock.factory }
    mercure: { enabled: true }
    view: { resolver: App\Game\SeatOfTheMember }
```

```php
public function move(GameTables $tables, string $table, Request $request): Response
{
    $seat = $this->seating->seatOf($table, $this->getUser());
    $tables->play($table, new Move('chess.move', $seat, ['from' => 'e2', 'to' => 'e4']), $request->getPayload()->getInt('seen'));

    return new Response(null, Response::HTTP_NO_CONTENT);
}
```

## Prérequis

PHP 8.5 ou une version plus récente, et Symfony 8.2 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).

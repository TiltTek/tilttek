<!-- phil-dev source-sha256=350d748aaf2bd8de789d7621ec6b673baec5ebd117154df0bdd98ef7f9e4d2a0 -->
# tilttek/table-safety-bundle

**Les outils de sécurité des tables de jeu de rôle dans une application Symfony : les écrans où les participants répondent et lèvent des signaux, l'écran du meneur de jeu, et les signaux en temps réel — anonymes par défaut.**

tilttek/table-safety-bundle intègre `tilttek/table-safety` à Symfony. Les réglages des tables, les lignes et voiles des
participants, les listes de consentement, les réponses à la séance zéro, les étoiles et souhaits, et les signaux de
la partie sont gardés dans Doctrine DBAL, dans quatre tables que le bundle ajoute au schéma que génère l'ORM, de sorte
que les migrations de l'application les créent. Les écrans — la table pendant la partie, les réponses d'un participant,
la séance zéro, les étoiles et souhaits d'une séance, l'écran du meneur de jeu — sont construits des recettes d'un kit
de UX Toolkit et fonctionnent sans JavaScript : chaque signal est un simple formulaire. Un contrôleur Stimulus envoie
les signaux en arrière-plan, ajoute chaque nouveau signal dès qu'il est levé et l'annonce aux lecteurs d'écran. La carte
X, les lignes et voiles et la liste de consentement s'impriment en cartes vierges pour une table jouée en personne, en
page ou, avec `phil/paged-media`, en PDF. Avec un hub Mercure, chaque signal est diffusé sur le sujet privé de sa table
— ou de son meneur de jeu, pour un signal discret — avec des identifiants seulement. L'application dit qui participe à
une table et qui la mène par une seule interface ; une commande purge les vieux signaux ; et les réponses d'un
participant sont exportées et effacées avec son compte par `phil/privacy-bundle`.

Il s'adresse à une application Symfony qui fait tourner des tables — un club, un congrès, un site de jeu par message,
une table virtuelle — et veut leurs outils de sécurité sans exposer qui a dit quoi. La bibliothèque seule convient à une
application sans Symfony.

Il exige `tilttek/table-safety`, `phil/content-warnings-bundle` pour le catalogue de thèmes, Doctrine DBAL, les
composants de formulaire, HTTP, de routage et CSRF de Symfony, et Twig ; l'ORM, la console, le composant Mercure,
`phil/paged-media` et `phil/privacy-bundle` sont facultatifs, et le bundle n'enregistre ce qui les utilise que lorsqu'ils sont installés ou
configurés.

## État

| Partie | État |
|---|---|
| Configuration des stockages, de l'accès, de la mise en page, des valeurs par défaut d'une table, du hub et de la conservation | Implémenté et testé |
| `DbalTableStore`, `DbalParticipationStore`, `DbalSignalStore`, `DbalFeedbackStore` et les quatre tables, avec l'écouteur de schéma | Implémenté et testé |
| Les formulaires des lignes et voiles, de la liste, de la séance zéro, des étoiles et souhaits, des outils d'une annonce et des réglages | Implémenté et testé |
| Les écrans de la table, des réponses, de la séance zéro, des étoiles et souhaits et du meneur de jeu, en anglais et en français | Implémenté et testé |
| Les routes des signaux : lever, le fil, marquer comme vu | Implémenté et testé |
| Les signaux diffusés sur Mercure, et l'abonnement de chaque lecteur | Implémenté et testé |
| Le contrôleur Stimulus des signaux | Implémenté |
| Les cartes imprimables — carte X, lignes et voiles, liste de consentement — en page et en PDF | Implémenté et testé |
| La commande `tilttek:table-safety:purge-signals` | Implémenté et testé |
| Export et effacement d'un participant pour `phil/privacy-bundle` | Implémenté et testé |
| La recette Symfony Flex, dans `recipe/` | Préparée, non publiée |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, les routes, les tables, l'accès et la recette. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Écrans](doc/fr/screens.md) | Les routes, les gabarits, les cartes imprimables, les recettes du kit, les formulaires, le contrôleur Stimulus et l'accessibilité. |
| [Temps réel](doc/fr/real-time.md) | Les signaux diffusés sur Mercure, les sujets et les abonnements. |
| [Base de données](doc/fr/database.md) | Les quatre tables et leurs clés. |
| [Mettre à niveau](doc/fr/upgrading.md) | Les migrations qu'une application exécute quand les tables changent. |
| [Vie privée](doc/fr/privacy.md) | Ce qui reste anonyme, l'export et l'effacement des données d'un participant, et la purge des signaux. |
| [Points d'extension](doc/fr/extension-points.md) | Remplacer l'accès, un stockage, le catalogue, les gabarits. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et tester une application qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Phil\ContentWarningsBundle\PhilContentWarningsBundle::class => ['all' => true],
Tilttek\TableSafetyBundle\TilttekTableSafetyBundle::class => ['all' => true],
```

```yaml
# config/packages/tilttek_table_safety.yaml
tilttek_table_safety:
    table_access: App\TableSafety\TableAccess
    mercure: { hub: mercure.hub.default }

# config/routes/tilttek_table_safety.yaml
tilttek_table_safety:
    resource: '@TilttekTableSafetyBundle/config/routes.php'
```

Un participant de `table:7` ouvre alors `/table-safety/table:7` ; son meneur de jeu, `/table-safety/table:7/game-master`.

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-intl`, Symfony 8.2, Doctrine DBAL 4.6, Twig 3.31.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).

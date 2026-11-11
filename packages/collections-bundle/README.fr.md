<!-- phil-dev source-sha256=593bca0d28dcb424c5fb2e95b134e8b594bd11868034a2dfa293692e5601d281 -->
# tilttek/collections-bundle

**Des objets à collectionner pour Symfony : le catalogue, les exemplaires des membres, l'objet du jour, les paquets et
les dés, dans Doctrine DBAL.**

tilttek/collections-bundle intègre `tilttek/collections` à Symfony. Le catalogue — raretés, objets, ensembles, paquets
— est configuré et vérifié à la compilation du conteneur, ou gardé dans la base de données et modifié une définition à
la fois. Les exemplaires, les ensembles complétés, les paquets ouverts, les dés choisis et le calendrier de l'objet du
jour vivent dans six tables d'une connexion Doctrine DBAL. Ces tables sont ajoutées au schéma que génère l'ORM, pour
que les migrations de l'application les créent, et chaque exemplaire est écrit une seule fois, même quand deux
processus le remettent. L'objet du jour est remis sur l'activité des membres par une projection que tient à jour
`tilttek/activity-bundle`, à des dates fixes, à des dates que calcule `phil/almanac` et par un tirage pondéré par la
rareté les jours libres. Un exemplaire remis et un ensemble complété — avec ses récompenses — deviennent des
événements du répartiteur de l'application. Le bundle fournit l'inventaire par lequel échange `tilttek/exchange` et
l'apparence des dés d'un membre pour `tilttek/dice-bundle`. Il offre des fonctions et des gabarits Twig pour l'album,
l'objet du jour et les chances d'un paquet, des commandes qui génèrent le calendrier, remettent un exemplaire et
affichent un album, et l'export et l'effacement d'un membre pour `phil/privacy-bundle`.

Il s'adresse à une application Symfony qui donne à ses membres quelque chose à collectionner. Ses dépendances sont
`tilttek/collections`, Doctrine DBAL, `phil/resource`, le répartiteur d'événements PSR, ainsi que la configuration et
l'injection de dépendances de Symfony. Toute autre intégration n'est enregistrée que si son paquet est installé.

La conception suit la bibliothèque : les stockages implémentent ses contrats sur DBAL, `CollectionsServices` câble les
services à partir de la configuration, et des adaptateurs sous `Infrastructure` rejoignent les autres paquets —
`Almanac`, `Exchange`, `Dice`, `Event`, `Console`, `Twig`, `Privacy`. Le bundle n'a aucun modèle propre.

## État

| Partie | État |
|---|---|
| `TilttekCollectionsBundle`, `CollectionsServices` : la configuration, vérifiée à la compilation du conteneur, et les services | Implémenté et testé |
| `CollectionsTables`, `CollectionsSchemaListener`, `Rows` : les six tables dans le schéma généré, l'insertion idempotente selon la plateforme | Implémenté et testé |
| `DbalCopyStore`, `DbalCompletionStore`, `DbalOpeningStore`, `DbalChoiceStore`, `DbalDailyStore` : les stockages de la bibliothèque sur DBAL | Implémenté et testé |
| `DbalCatalog` : le catalogue dans la base de données, modifié une définition à la fois | Implémenté et testé |
| `AlmanacDateRule` : des objets du jour aux dates que calcule `phil/almanac` | Implémenté et testé |
| `CollectionsInventory` : les exemplaires comme inventaire de `tilttek/exchange` | Implémenté et testé |
| `CollectionDiceAppearance` : le dé choisi comme apparence de `tilttek/dice-bundle` | Implémenté et testé |
| `DispatchingObserver`, `CopyGranted` : les événements | Implémenté et testé |
| `DailyCommand`, `GrantCommand`, `AlbumCommand` : les commandes | Implémenté et testé |
| `CollectionsExtension` et les gabarits `album`, `daily`, `odds`, en anglais et en français | Implémenté et testé |
| `CollectionsPrivacy` : l'export et l'effacement d'un membre | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement du bundle, la migration. |
| [Configuration](doc/fr/configuration.md) | Chaque clé, avec sa valeur par défaut. |
| [La base de données](doc/fr/database.md) | Les six tables, le catalogue dans la base de données, la concurrence. |
| [L'objet du jour](doc/fr/item-of-the-day.md) | Le calendrier, ses dates, son tirage, la projection et la commande. |
| [Points d'extension](doc/fr/extension-points.md) | Les échanges par `tilttek/exchange`, les dés pour `tilttek/dice-bundle`, les germes, le catalogue. |
| [Événements](doc/fr/events.md) | `CopyGranted` et `SetCompleted`. |
| [Gabarits](doc/fr/templates.md) | Les fonctions Twig et les trois gabarits. |
| [Commandes](doc/fr/commands.md) | `tilttek:collections:daily`, `tilttek:collections:grant`, `tilttek:collections:album`. |
| [Vie privée](doc/fr/privacy.md) | Le module de `phil/privacy-bundle`. |
| [Exceptions](doc/fr/exceptions.md) | Ce que lève le bundle. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester une application qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque classe du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/collections-bundle tilttek/activity-bundle
```

```yaml
# config/packages/tilttek_collections.yaml
tilttek_collections:
    timezone: America/Toronto
    rarities: { common: 70, rare: 25, legendary: 5 }
    items:
        deckhand: { name: { en: Deckhand, fr: Matelot }, rarity: common, sets: [crew] }
        captain: { name: Captain, rarity: legendary, sets: [crew] }
    sets:
        crew: { name: The crew, rewards: [{ kind: points, code: xp, amount: 50 }] }
    daily:
        enabled: true
        verbs: ['page.viewed']
```

```twig
{{ include('@TilttekCollections/album.html.twig', {album: tilttek_collections_album('member:' ~ app.user.id), locale: app.request.locale}) }}
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2, Doctrine DBAL 4.5.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).

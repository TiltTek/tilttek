<!-- phil-dev source-sha256=70891a421829a75adab3f7234f291a9d49fdefa21cb752ed32f7e525e01c6314 -->
# tilttek/generator-bundle

**Des générateurs aléatoires pour Symfony : les listes et les gabarits de l'application, tirés dans ses pages, son
terminal et son API.**

tilttek/generator-bundle intègre `tilttek/generator` à une application Symfony. Il lit les listes et les générateurs
dans les fichiers YAML et JSON de l'application, les garde en cache, et construit à la première utilisation le
générateur qui les vérifie. Il ajoute des commandes qui valident les fichiers, écrivent un nouveau générateur et font
des tirages dans le terminal. Il ajoute aussi une fonction Twig qui fait un tirage dans un gabarit, un gabarit et un
contrôleur Stimulus qui refont le tirage sur place, et une API de tirage que d'autres sites et applications appellent
sans jamais recevoir les listes. L'API est désactivée tant que l'application ne l'active pas. Elle n'ouvre que les
générateurs qu'elle nomme, à tout le monde ou aux clients qui présentent leur jeton, sous les limites de débit de
`framework.rate_limiter`. Il charge les tables aléatoires de la bibliothèque sur demande, en anglais ou en français, et
garde les états des tables à mémoire (la météo d'une campagne, un voyage, une exploration) par générateur et par clé,
dans un pool de cache ou dans un stockage de l'application.

Il s'adresse aux applications Symfony qui offrent des générateurs à leurs membres ou à d'autres sites : une page de
générateur de noms, la boîte à outils d'un meneur de jeu, des tables aléatoires servies à une table virtuelle. Les
listes appartiennent à l'application ; le bundle n'en fournit aucune, et les tables aléatoires de la bibliothèque ne
sont chargées que quand l'application les demande.

C'est un bundle Symfony (`AbstractBundle`) sur `tilttek/generator` et `tilttek/dice`. Ses intégrations (l'API, les
commandes, les limites de débit, le cache des états, la fonction Twig et le contrôleur Stimulus) sont enregistrées
seulement quand leur composant est installé. Quand `tilttek/dice-bundle` est installé, ses dés, y compris les dés
personnalisés, sont lancés dans les gabarits.

La conception est petite : `CachedCatalog` lit les fichiers et les met en cache, `GeneratorFactory` construit le
`Generator`, `MemoryTables` fait des tirages à partir de l'état que garde un `StateStore`, derrière un `StateLock`
quand un verrou est configuré, `ApiAccess` décide qui peut tirer quoi, `DrawController` sert les tirages, et les
commandes et l'extension Twig utilisent le même générateur.

## État

| Partie | État |
|---|---|
| Listes et générateurs lus dans des fichiers et des répertoires, mis en cache, rafraîchis en mode débogage | Implémenté et testé |
| Les tables aléatoires de la bibliothèque, chargées sur demande, en anglais ou en français | Implémenté et testé |
| Tables à mémoire : états gardés par générateur et par clé dans un pool de cache, en mémoire ou dans le stockage de l'application | Implémenté et testé |
| Un verrou facultatif sur `symfony/lock`, pour que deux tirages sur le même état ne perdent pas une mise à jour | Implémenté et testé |
| Commandes : valider, créer (un texte, des champs, une carte, une table à mémoire, ou une copie d'une table aléatoire) et tirer, avec une clé | Implémenté et testé |
| Fonction Twig `tilttek_draw`, gabarit et contrôleur Stimulus qui refont le tirage | Implémenté et testé |
| API de tirage : désactivée par défaut, générateurs publics par adresse, clients par jeton, limites de débit, détails de problème, états renvoyés par le client | Implémenté et testé |
| Les dés de `tilttek/dice-bundle` dans les gabarits, quand il est installé | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, les fichiers et les routes. |
| [Configuration](doc/fr/configuration.md) | Chaque clé de `tilttek_generator`. |
| [Commandes](doc/fr/commands.md) | `tilttek:generator:validate`, `create` et `draw`. |
| [Tables à mémoire](doc/fr/memory.md) | `MemoryTables`, le stockage des états, le verrou, et les états par l'API. |
| [Gabarits](doc/fr/templates.md) | `tilttek_draw`, le gabarit qui refait le tirage et son contrôleur Stimulus. |
| [API de tirage](doc/fr/api.md) | La route, les paramètres, les réponses, l'accès et les limites de débit. |
| [Points d'extension](doc/fr/extension-points.md) | Les services qu'une application peut remplacer ou utiliser. |
| [Exceptions](doc/fr/exceptions.md) | Les exceptions du bundle et comment l'API y répond. |
| [Tests](doc/fr/testing.md) | Les tests du bundle et sa barre de mutation, et comment tester les générateurs d'une application. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle, avec son rôle. |

La syntaxe des générateurs (listes, gabarits, grammaire, cartes et tirages) est celle de `tilttek/generator`,
documentée dans son propre `doc/`.

## Démarrage rapide

```sh
composer require tilttek/generator-bundle
```

```yaml
# config/generators/names.yaml
lists:
  name.syllable: [ka, ri, to, mu, sel]
generators:
  name.short:
    templates: "{% set a = pick('name.syllable') %}{{ a }}{{ pick('name.syllable', not: a) }}"
    filters: [capitalize]
```

```twig
{{ include('@TilttekGenerator/draw.html.twig', {
    result: tilttek_draw('name.short', 5, true),
    url: path('tilttek_generator_draw', {generator: 'name.short'}),
}) }}
```

```sh
bin/console tilttek:generator:validate
bin/console tilttek:generator:draw name.short --count=5 --unique
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).

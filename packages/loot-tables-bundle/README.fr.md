<!-- phil-dev source-sha256=60fc3093c3a9d0526c06d7300827aaae5d6907f7c59176d022492f8bbd5ad934 -->
# tilttek/loot-tables-bundle

**Des tables de butin pour Symfony et ses commandes de commerce : engagées au moment de la commande, tirées une fois
celle-ci payée.**

tilttek/loot-tables-bundle intègre `tilttek/loot-tables` à une application Symfony et aux options de
`phil/commerce-bundle`. Les tables et les boîtes se déclarent dans la configuration. Un produit déclare la boîte qu'il
lance par le tireur d'une option de tirage, `loot-box:<key>`, et une option de texte tirée parmi ses entrées nomme
`loot-text`. Quand le workflow de la commande passe la commande, `LootCommitments` engage un tirage par unité : un
germe tiré du générateur cryptographique, gardé secret, dont l'engagement est montré à l'acheteur avant le paiement.
Une fois la commande payée, le noyau du commerce interroge `LootOptionDrawer`, qui révèle chaque tirage une seule fois
à partir du germe du serveur seul, sans JavaScript : la ligne de commande garde ses faces et les noms de ses entrées,
ou le texte tiré, et le bundle garde sa preuve, qui publie le germe, dans Doctrine. À un congrès, `PhysicalDice` saisit
les dés qu'un acheteur a lancés devant le caissier, un code de boutique par unité, par le formulaire `PhysicalDiceType`
que la page de la caisse dessine avec `<twig:TilttekLootTables:PhysicalDice>`. Sur la page de l'acheteur,
`<twig:TilttekLootTables:Commitments>` montre les engagements, puis les germes révélés et les entrées, dessinés avec
les recettes du kit UX. `LootResults` donne à la production, aux reçus et aux acheteurs les entrées avec leurs images
et leurs emplacements, le code de boutique, si un tirage se vérifie, et le tirage relancé à partir de son germe, ce que
fait `tilttek:loot-tables:replay` depuis la console ; `LootPrint` compose ce qui s'imprime de chaque unité, et
`LootSheetPdf` l'imprime en fiche de production.

## État

| Partie | État |
|---|---|
| Tables et boîtes tirées de la configuration, vérifiées à la compilation du conteneur | Implémentées et testées |
| Germes du générateur cryptographique, engagés quand le workflow de la commande passe la commande | Implémentés et testés |
| Révélation unique après le paiement à partir du germe du serveur seul, par le tireur d'options du commerce | Implémentée et testée |
| Un tirage révélé relancé à partir de son germe, et la commande de console qui le vérifie | Implémentés et testés |
| Textes tirés parmi les entrées d'une option de texte | Implémentés et testés |
| Dés physiques saisis à une caisse | Implémentés et testés |
| Tirages gardés dans Doctrine avec leur preuve, résultats, codes de boutique et vérification | Implémentés et testés |
| Les tirages de l'acheteur sans JavaScript : engagements, puis germes et entrées, sur les recettes du kit UX | Implémentés et testés |
| Le formulaire des dés physiques et son composant, pour une caisse | Implémentés et testés |
| Ce qui s'imprime de chaque unité, lu dans les résultats, et la fiche de production en PDF, avec `phil/paged-media` | Implémentés et testés |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, activation du bundle et sa table. |
| [Configuration](doc/fr/configuration.md) | Chaque clé de `tilttek_loot_tables` et les services qu'elle enregistre. |
| [Tirer les commandes](doc/fr/drawing-orders.md) | Les tireurs des options, les engagements au moment de la commande, le germe du serveur seul, la révélation après le paiement, les textes tirés d'une liste. |
| [Dés physiques](doc/fr/physical-dice.md) | `PhysicalDice` à une caisse. |
| [Résultats](doc/fr/results.md) | `LootResults`, les tirages gardés, leur vérification, et un tirage relancé à partir de son germe. |
| [Écrans](doc/fr/screens.md) | Les tirages de l'acheteur et le formulaire des dés physiques. |
| [Fiche de production](doc/fr/production-sheet.md) | `LootPrint`, ce qui s'imprime de chaque unité, et `LootSheetPdf`, les tirages d'une commande en PDF. |
| [Exceptions](doc/fr/exceptions.md) | `LootDrawRefused` et les exceptions de la bibliothèque. |
| [Points d'extension](doc/fr/extension-points.md) | Où l'application se branche. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester une application qui s'en sert. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/loot-tables-bundle
```

```yaml
# config/packages/tilttek_loot_tables.yaml
tilttek_loot_tables:
    tables:
        rings:
            name: Rings
            entries:
                - { key: copper, name: Copper ring, image: /loot/copper.png, slot: finger }
                - { key: silver, name: Silver ring, image: /loot/silver.png, slot: finger }
                # … one entry per side of the die
    boxes:
        starter: { name: Starter box, tables: [rings, rings] }
```

```php
// the catalogue of the commerce core declares the option
OptionDefinition::draw('loot', 'Loot', Drawers::box('starter'));

$lifecycle->place($order, $clock->now());                // commits the draws of the order
$optionDraws->draw($order, $payments->summary($order)); // once it is paid
```

```twig
{# the order page: the commitments before the payment, the seeds and the entries after #}
<twig:TilttekLootTables:Commitments order="{{ order.number }}" />
```

```sh
php bin/console tilttek:loot-tables:replay <draw>  # rolls a revealed draw again from its seed
```

## Prérequis

PHP 8.5 ou plus récent avec sodium, Symfony 8.2 avec son composant Workflow, Doctrine ORM 3.7,
`phil/commerce-bundle` et `tilttek/loot-tables` ; le composant Form, Symfony UX avec le kit PHIL UX, le composant
Console et `phil/paged-media` pour le formulaire, les composants, la commande de relance et la fiche de production.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).

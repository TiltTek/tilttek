<!-- phil-dev source-sha256=329f58caff7cb6896d6c707048302cf4cb02f36510e052bd071fbd1b7c805787 -->
# Configuration

```yaml
tilttek_loot_tables:
    tables:
        rings:
            name: Rings
            entries:
                - { key: copper, name: Copper ring, image: /loot/copper.png, slot: finger }
                - { key: silver, name: Silver ring, image: /loot/silver.png, slot: finger }
                - { key: gold, name: Gold ring, image: /loot/gold.png, slot: finger }
                - { key: bone, name: Bone ring, slot: finger }
                - { key: glass, name: Glass ring, slot: finger }
                - { key: none, name: No ring }
    boxes:
        starter: { name: Starter box, tables: [rings, rings] }
    require_commitment: true
    commit_on_place: true
    store: doctrine
    entity_manager: default
    kit_subdirectory: null
```

| Clé | Valeur par défaut | Sens |
|---|---|---|
| `tables` | `{}` | Les tables, par clé : un `name` et ses `entries`, une par face du dé, chacune avec une `key`, un `name`, une `image` et un `slot` |
| `boxes` | `{}` | Les boîtes, par clé : un `name` et les clés des `tables` que lance un produit, dans l'ordre |
| `require_commitment` | `true` | Si un tirage est refusé à moins d'avoir été engagé au moment de la commande ; désactivé, il est engagé et révélé d'un coup |
| `commit_on_place` | `true` | Si les tirages d'une commande sont engagés dès que le workflow de commande du noyau du commerce la passe ; désactivé, l'application appelle `LootCommitments::commit()` |
| `store` | `doctrine` | Où sont gardés les tirages : `doctrine`, ou `memory` pour les tests |
| `entity_manager` | `default` | Le gestionnaire d'entités Doctrine des tirages |
| `kit_subdirectory` | `null` | Où sont les recettes du kit UX, sous le répertoire des composants ; le sous-répertoire de `ux_toolkit` quand la valeur est `null` |

Les clés gardent leurs traits d'union. Les tables et les boîtes sont lues par `CatalogReader` à la compilation du
conteneur : une clé, un nom, un dé ou une boîte que la bibliothèque refuse fait échouer la compilation avec son
`InvalidLoot`, jamais une vente.

## Services

| Service | Rôle |
|---|---|
| `Tilttek\LootTables\LootCatalog` (public) | Le catalogue de la configuration |
| `Tilttek\LootTablesBundle\Application\LootCommitments` (public) | Engage les tirages d'une commande au moment où elle est passée |
| `Tilttek\LootTablesBundle\Infrastructure\Workflow\CommitOnPlace` | Avec `commit_on_place`, écoute `workflow.phil_commerce_order.completed.place` et engage les tirages de la commande |
| `Tilttek\LootTablesBundle\Application\LootOptionDrawer` | Étiqueté `phil_commerce.option_drawer` : révèle les tirages une fois la commande payée |
| `Tilttek\LootTablesBundle\Application\PhysicalDice` (public) | Saisit des dés physiques à une caisse |
| `Tilttek\LootTablesBundle\Application\LootResults` (public) | Les résultats, les codes de boutique, la vérification et la relance des tirages |
| `Tilttek\LootTablesBundle\Application\LootPrint` (public) | Ce qui s'imprime des tirages d'une commande |
| `Tilttek\LootTablesBundle\Domain\Repository\LootDrawRepository` (alias public) | Le stockage des tirages |
| `Tilttek\LootTablesBundle\Application\Drawers` | Les tireurs des options et leurs boîtes |
| `Tilttek\LootTablesBundle\Application\SecureSeeds` | Les germes, de `Random\Engine\Secure` |
| `Tilttek\LootTables\Draw\LootDrawer` | Le tireur de la bibliothèque |
| `Tilttek\LootTablesBundle\Infrastructure\Form\PhysicalDiceType` | Étiqueté `form.type`, avec le composant Form : le formulaire des dés physiques |
| `Tilttek\LootTablesBundle\Infrastructure\Twig\Components\PhysicalDiceComponent` | Avec TwigComponent : `TilttekLootTables:PhysicalDice` |
| `Tilttek\LootTablesBundle\Infrastructure\Twig\Components\CommitmentsComponent` | Avec TwigComponent : `TilttekLootTables:Commitments` |
| `Tilttek\LootTablesBundle\Infrastructure\Twig\KitComponents` | Avec TwigComponent : les noms des recettes du kit UX |
| `Tilttek\LootTablesBundle\Infrastructure\Console\ReplayDrawCommand` | Avec le composant Console : `tilttek:loot-tables:replay` |
| `Tilttek\LootTablesBundle\Infrastructure\Pdf\LootSheetPdf` (public) | Avec `phil/paged-media` : la fiche de production |

Les services ne gardent aucun état d'une requête à l'autre : ils sont sûrs dans un *worker*. Le stockage en mémoire,
pour les tests, se vide quand le noyau se réinitialise.

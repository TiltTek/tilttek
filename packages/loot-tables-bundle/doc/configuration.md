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

| Key | Default | Meaning |
|---|---|---|
| `tables` | `{}` | The tables, by key: a `name` and its `entries`, one per side of the die, each with a `key`, a `name`, and an `image` and a `slot` |
| `boxes` | `{}` | The boxes, by key: a `name` and the keys of the `tables` a product rolls, in order |
| `require_commitment` | `true` | Whether a draw is refused unless it was committed when the order was placed; off, it is committed and revealed at once |
| `commit_on_place` | `true` | Whether the draws of an order are committed as soon as the order workflow of the commerce core places it; off, the application calls `LootCommitments::commit()` |
| `store` | `doctrine` | Where the draws are kept: `doctrine`, or `memory` for tests |
| `entity_manager` | `default` | The Doctrine entity manager of the draws |
| `kit_subdirectory` | `null` | Where the recipes of the UX kit are, under the components directory; the subdirectory of `ux_toolkit` when `null` |

The keys keep their dashes. The tables and the boxes are read by `CatalogReader` when the container is compiled: a
key, a name, a die or a box the library refuses fails the compilation with its `InvalidLoot`, never a sale.

## Services

| Service | Role |
|---|---|
| `Tilttek\LootTables\LootCatalog` (public) | The catalogue of the configuration |
| `Tilttek\LootTablesBundle\Application\LootCommitments` (public) | Commits the draws of an order when it is placed |
| `Tilttek\LootTablesBundle\Infrastructure\Workflow\CommitOnPlace` | With `commit_on_place`, listens to `workflow.phil_commerce_order.completed.place` and commits the order's draws |
| `Tilttek\LootTablesBundle\Application\LootOptionDrawer` | Tagged `phil_commerce.option_drawer`: reveals the draws once the order is paid |
| `Tilttek\LootTablesBundle\Application\PhysicalDice` (public) | Enters physical dice at a register |
| `Tilttek\LootTablesBundle\Application\LootResults` (public) | The results, shop codes, verification and replay of the draws |
| `Tilttek\LootTablesBundle\Application\LootPrint` (public) | What is printed of an order's draws |
| `Tilttek\LootTablesBundle\Domain\Repository\LootDrawRepository` (public alias) | The store of the draws |
| `Tilttek\LootTablesBundle\Application\Drawers` | The drawers of the options and their boxes |
| `Tilttek\LootTablesBundle\Application\SecureSeeds` | The seeds, from `Random\Engine\Secure` |
| `Tilttek\LootTables\Draw\LootDrawer` | The drawer of the library |
| `Tilttek\LootTablesBundle\Infrastructure\Form\PhysicalDiceType` | Tagged `form.type`, with the Form component: the form of the physical dice |
| `Tilttek\LootTablesBundle\Infrastructure\Twig\Components\PhysicalDiceComponent` | With TwigComponent: `TilttekLootTables:PhysicalDice` |
| `Tilttek\LootTablesBundle\Infrastructure\Twig\Components\CommitmentsComponent` | With TwigComponent: `TilttekLootTables:Commitments` |
| `Tilttek\LootTablesBundle\Infrastructure\Twig\KitComponents` | With TwigComponent: the names of the recipes of the UX kit |
| `Tilttek\LootTablesBundle\Infrastructure\Console\ReplayDrawCommand` | With the Console component: `tilttek:loot-tables:replay` |
| `Tilttek\LootTablesBundle\Infrastructure\Pdf\LootSheetPdf` (public) | With `phil/paged-media`: the production sheet |

The services keep no state between requests: they are safe in a worker. The store in memory, for tests, empties
itself when the kernel resets.

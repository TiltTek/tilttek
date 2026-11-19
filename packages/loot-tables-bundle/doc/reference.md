# Reference

| Type | Kind | Role |
|---|---|---|
| `TilttekLootTablesBundle` | bundle | The configuration and the mapping of the draws |
| `Application\Drawers` | `final readonly class` | The drawers of the options and the box each draws |
| `Application\SecureSeeds` | `final readonly class` | The seeds, from the cryptographic generator |
| `Application\LootCommitments` | `final readonly class` | Commits the draws of an order when it is placed |
| `Application\LootOptionDrawer` | `final readonly class`, commerce `OptionDrawer` | Reveals the draws once the order is paid |
| `Application\PhysicalDice` | `final readonly class` | Enters physical dice at a register |
| `Application\LootResults` | `final readonly class` | Results, shop codes, verification and replay |
| `Application\LootPrint` | `final readonly class` | What is printed of an order's draws |
| `Application\PrintedDraw` | `final readonly class` | What goes on one unit |
| `Application\PrintState` | enum | Where a printed draw stands |
| `Domain\Model\LootDraw` | entity | The draw of one unit |
| `Domain\Repository\LootDrawRepository` | interface | The store of the draws |
| `Domain\Exception\LootDrawRefused` | `final class` | A draw refused |
| `Infrastructure\Doctrine\DoctrineLootDrawRepository` | `final readonly class` | The draws in Doctrine |
| `Infrastructure\Memory\InMemoryLootDrawRepository` | `final class` | The draws in memory, for tests |
| `Infrastructure\DependencyInjection\LootServices` | `final class` | The services of the configuration |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | compiler pass | Names the kit's recipes after the Toolkit's subdirectory |
| `Infrastructure\Workflow\CommitOnPlace` | event listener | Commits the draws when the order workflow places the order |
| `Infrastructure\Console\ReplayDrawCommand` | console command | `tilttek:loot-tables:replay`, a draw rolled again from its seed |
| `Infrastructure\Twig\KitComponents` | `final readonly class` | The names of the kit's recipes |
| `Infrastructure\Form\PhysicalDiceType` | form type | The form of the physical dice, one field per unit |
| `Infrastructure\Twig\Components\PhysicalDiceComponent` | Twig component | `TilttekLootTables:PhysicalDice`, the form on the register's page |
| `Infrastructure\Twig\Components\CommitmentsComponent` | Twig component | `TilttekLootTables:Commitments`, the buyer's draws |
| `Infrastructure\Pdf\LootSheetPdf` | `final readonly class` | The production sheet of an order's draws |

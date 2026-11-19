<!-- phil-dev source-sha256=52bc9610fe89ae3ee912ce20c48ecfcaa99f4759ed42084cfe5bff9b4edd5ba4 -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `TilttekLootTablesBundle` | bundle | La configuration et l'association des tirages |
| `Application\Drawers` | `final readonly class` | Les tireurs des options et la boîte que chacun tire |
| `Application\SecureSeeds` | `final readonly class` | Les germes, du générateur cryptographique |
| `Application\LootCommitments` | `final readonly class` | Engage les tirages d'une commande au moment où elle est passée |
| `Application\LootOptionDrawer` | `final readonly class`, `OptionDrawer` du commerce | Révèle les tirages une fois la commande payée |
| `Application\PhysicalDice` | `final readonly class` | Saisit des dés physiques à une caisse |
| `Application\LootResults` | `final readonly class` | Résultats, codes de boutique, vérification et relance |
| `Application\LootPrint` | `final readonly class` | Ce qui s'imprime des tirages d'une commande |
| `Application\PrintedDraw` | `final readonly class` | Ce qui va sur une unité |
| `Application\PrintState` | énumération | Où en est un tirage imprimé |
| `Domain\Model\LootDraw` | entité | Le tirage d'une unité |
| `Domain\Repository\LootDrawRepository` | interface | Le stockage des tirages |
| `Domain\Exception\LootDrawRefused` | `final class` | Un tirage refusé |
| `Infrastructure\Doctrine\DoctrineLootDrawRepository` | `final readonly class` | Les tirages dans Doctrine |
| `Infrastructure\Memory\InMemoryLootDrawRepository` | `final class` | Les tirages en mémoire, pour les tests |
| `Infrastructure\DependencyInjection\LootServices` | `final class` | Les services de la configuration |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | passe du compilateur | Nomme les recettes du kit d'après le sous-répertoire du Toolkit |
| `Infrastructure\Workflow\CommitOnPlace` | écouteur d'événement | Engage les tirages quand le workflow de la commande passe la commande |
| `Infrastructure\Console\ReplayDrawCommand` | commande de console | `tilttek:loot-tables:replay`, un tirage relancé à partir de son germe |
| `Infrastructure\Twig\KitComponents` | `final readonly class` | Les noms des recettes du kit |
| `Infrastructure\Form\PhysicalDiceType` | type de formulaire | Le formulaire des dés physiques, un champ par unité |
| `Infrastructure\Twig\Components\PhysicalDiceComponent` | composant Twig | `TilttekLootTables:PhysicalDice`, le formulaire sur la page de la caisse |
| `Infrastructure\Twig\Components\CommitmentsComponent` | composant Twig | `TilttekLootTables:Commitments`, les tirages de l'acheteur |
| `Infrastructure\Pdf\LootSheetPdf` | `final readonly class` | La fiche de production des tirages d'une commande |

<!-- phil-dev source-sha256=9160baec732c15a43e3a0ad9d771a8163f71603f9f3ea5e13a866f428c72a6d3 -->
# Points d'extension

| Point | Ce que fournit une application | Où |
|---|---|---|
| `CatalogProvider`, `WritableCatalog` | La provenance du catalogue : du code, une configuration, ou une base de données que modifie le site | [Le catalogue](catalog.md#un-catalogue-gardé-ailleurs) |
| `Store\CopyStore`, `Store\CompletionStore`, `Pack\OpeningStore`, `Daily\DailyStore`, `Dice\ChoiceStore` | Le stockage des exemplaires, des ensembles complétés, des paquets ouverts, du calendrier du jour et des dés choisis | [Écrire un stockage](writing-a-store.md) |
| `Daily\DateRule` | Une date calculée du calendrier de l'objet du jour — une semaine après Pâques, le quatrième jeudi de novembre | [L'objet du jour](item-of-the-day.md) |
| `Pack\SeedSource` | Le germe du tirage d'un paquet : un générateur sûr en production, un germe fixe dans les tests | [Paquets](packs.md#ouvrir-un-paquet) |
| `CollectionObserver` | Ce qui se fait quand un exemplaire est remis ou un ensemble complété : un avis, une récompense | [Les exemplaires et l'album](copies-and-album.md) |
| `Psr\Clock\ClockInterface` | L'horloge que lisent les services : celle du système en production, une horloge fixe dans les tests | [Tests](testing.md) |

Chaque point est passé au constructeur du service qui l'utilise. Les services ne gardent aucun état propre entre deux
appels.

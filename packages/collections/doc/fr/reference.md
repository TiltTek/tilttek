<!-- phil-dev source-sha256=49572cb004cc022bb00c84bb6b6bc2e95e1c0f9e27912208fd3f0488f48758bd -->
# Référence

| Type | Rôle |
|---|---|
| `Collections` | Exemplaires remis, ensembles remis en entier, ensembles de base, déplacements, exemplaires repris, l'album |
| `Catalog`, `CatalogReader` | Les définitions d'une collection ; lues à partir de valeurs simples |
| `CatalogProvider`, `FixedCatalog`, `WritableCatalog`, `DefinitionKind` | La provenance du catalogue ; un catalogue modifié une définition à la fois |
| `Rarity`, `Item`, `CollectibleDie`, `ItemSet`, `SetKind`, `Pack`, `Reward` | Les définitions |
| `Names`, `Texts` | Les codes ; les textes par locale |
| `Odds` | Les chances publiées d'un tirage, et le tirage |
| `CollectionKey`, `Source`, `Copy`, `CopyMove` | La clé d'un exemplaire ; sa provenance ; un exemplaire ; un exemplaire qui change de mains |
| `Album`, `Holding`, `SetProgress` | L'album d'un membre ; les exemplaires d'un objet ; la progression d'un ensemble |
| `SetCompletion`, `SetCompleted`, `CollectionObserver` | Un ensemble complété une seule fois ; ce qui est annoncé ; qui est averti |
| `MemberData`, `Erasure` | L'export et l'effacement d'un membre ; ce qu'il a supprimé |
| `Pack\Packs`, `Pack\PendingOpening`, `Pack\PackOpening` | Les paquets engagés, ouverts et vérifiés ; un paquet engagé ; une ouverture |
| `Pack\SeedSource`, `Pack\SecureSeeds` | La provenance des germes ; des germes sûrs |
| `Pack\OpeningStore`, `Pack\InMemoryOpeningStore` | L'endroit où les ouvertures sont gardées |
| `Daily\DailyCalendar`, `Daily\DailyPick`, `Daily\PickOrigin` | Le calendrier de l'objet du jour ; l'objet d'un jour ; pourquoi |
| `Daily\DateRule`, `Daily\FixedDate`, `Daily\Days` | Une règle du calendrier ; une date fixe ; des jours écrits `Y-m-d` |
| `Daily\Daily`, `Daily\DailyProjection` | L'objet du jour réclamé ; sur l'activité |
| `Daily\DailyStore`, `Daily\InMemoryDailyStore` | L'endroit où le calendrier est gardé |
| `Dice\DiceShelf`, `Dice\DieChoice` | Les dés que possède et qu'a choisis un membre ; un choix |
| `Dice\ChoiceStore`, `Dice\InMemoryChoiceStore` | L'endroit où les choix sont gardés |
| `Store\CopyStore`, `Store\InMemoryCopyStore` | L'endroit où les exemplaires sont gardés |
| `Store\CompletionStore`, `Store\InMemoryCompletionStore` | L'endroit où les ensembles complétés sont gardés |
| `Exception\CollectionsException` et ses trois exceptions | [Exceptions](exceptions.md) |

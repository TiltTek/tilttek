<!-- phil-dev source-sha256=afdd1450a8922fdc358830ed9b613378f54969572ab636355e3392a6c214d858 -->
# Référence

| Type | Rôle |
|---|---|
| `Points` | Soldes, historiques, attributions, révocations, dépenses, remboursements, transferts, expiration, effacement |
| `PointsProjection` | L'activité transformée en points, annulations renversées, reconstructions |
| `Account`, `AccountType` | Un compte du registre et son type |
| `Currency`, `Currencies` | Une devise de points et sa durée de vie ; les devises d'une application |
| `MovementKey` | Ce qui fait qu'un mouvement n'est écrit qu'une fois |
| `MovementKind` | Pourquoi des points ont bougé ; les genres dérivés |
| `Movement` | Des points d'un compte débité vers un compte crédité |
| `LedgerEntry` | Un mouvement à sa position, et quand il a été écrit |
| `Recorded` | Une entrée et si cet appel l'a écrite |
| `Erasure` | Ce qu'effacer un membre a supprimé et détaché |
| `Lot`, `Lots` | Ce qui reste de chaque crédit, premiers entrés, premiers sortis |
| `LotsCheckpoint` | Les lots à une position, gardés pour que l'expiration ne lise que les entrées qui suivent |
| `Ledger\Ledger` | Où le registre est gardé |
| `Ledger\InMemoryLedger` | Le registre en mémoire |
| `Ledger\Reading` | Les bornes d'une lecture |
| `Rule\EarningRule` | Des points pour un geste |
| `Rule\Cap`, `Rule\Period` | Le plus qu'une règle donne à un membre sur une période |
| `Rule\Rules` | Les règles par nom et leur fuseau horaire |
| `Exception\PointsException` et ses six exceptions | [Exceptions](exceptions.md) |

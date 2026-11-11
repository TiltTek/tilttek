<!-- phil-dev source-sha256=6edc58c42f7cfc292eb84a0377902bde4ddca40ea27ada1d49107bd1de332fe3 -->
# Référence

| Type | Rôle |
|---|---|
| `Journal` | L'entrée de l'activité des membres : enregistrer, annuler, trouver, lire, compter, effacer |
| `JournalObserver` | Averti de chaque nouvelle entrée |
| `Activity` | Un fait : membre, verbe, moment, objet, quantité, contexte, clé d'idempotence |
| `Verb`, `VerbPattern`, `Verbs` | Un verbe, un motif de verbes, les verbes déclarés |
| `IdempotencyKey` | Ce qui fait compter un geste une seule fois |
| `Context` | Quelques faits nommés sur un geste |
| `Entry`, `EntryKind` | Une ligne du journal, enregistrée ou annulée, à sa position |
| `Appended` | Une entrée, et si cet appel l'a écrite |
| `Filter` | Des activités par membre, verbes, objet et période |
| `Totals` | Un compte et une somme de quantités |
| `Erasure` | Ce que l'effacement d'un membre a supprimé et détaché |
| `Log\ActivityLog` | L'endroit où le journal est gardé |
| `Log\InMemoryActivityLog` | Le journal en mémoire |
| `Log\Reading` | Les bornes d'une lecture |
| `Projection\Projection` | Un lecteur qui tient son propre état |
| `Projection\Projector` | Rattrapage, reconstruction et retard d'une projection |
| `Projection\Checkpoints`, `InMemoryCheckpoints` | L'endroit où chaque projection s'est arrêtée |
| `Projection\Projections` | Les projections par nom |
| `Projection\LiveProjections` | L'observateur qui tient les projections à jour |
| `Exception\ActivityException` et ses cinq exceptions | [Exceptions](exceptions.md) |

<!-- phil-dev source-sha256=40a9e92d577337f19b4a6546bfc00a34e70dd6ab6a5cf22da0a96c15cba3d88b -->
# Référence

| Type | Rôle |
|---|---|
| `AdventCalendar` | Ouvre les cases une fois, les montre comme un membre les voit, efface un membre |
| `Calendar` | Les cases par jour, leurs surprises, le fuseau horaire, la politique et la fermeture |
| `Surprise`, `SurpriseKind` | La récompense à laquelle renvoie une case ; des points, un insigne, un contenu ou un code |
| `OpeningPolicy`, `PolicyKind` | Combien de temps une case reste ouvrable ; jusqu'à la fermeture, le jour même ou un rattrapage |
| `OpeningWindow` | Quand une case peut s'ouvrir : de, inclus, jusqu'à, exclu |
| `Opening` | Une case qu'un membre a ouverte, et quand |
| `OpenedBox` | Ce que répond `open()` : l'ouverture, la surprise, et si cet appel l'a ouverte |
| `BoxView`, `BoxState` | Une case telle qu'un membre la voit ; verrouillée, ouvrable, ouverte ou manquée |
| `Event\BoxOpened` | Envoyé une fois par première ouverture, pour que l'application accorde la surprise |
| `Contract\OpeningStore`, `Store\InMemoryOpeningStore` | Où se gardent les ouvertures |
| `Exception\AdventCalendarException` et ses deux exceptions | [Exceptions](exceptions.md) |

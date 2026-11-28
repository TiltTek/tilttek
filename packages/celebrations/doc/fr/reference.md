<!-- phil-dev source-sha256=3c06ea1de0f5b5d4d4df1dff0958154d2a7eb804a6005e56e7e8690029e64f23 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Celebrations` | Reçoit, retire, groupe, compte, célèbre, marque comme vu, lit et efface les boîtes des membres |
| `Achievement` | Ce qu'un membre a obtenu : source, genre, sujet, occurrence, moment, paramètres, moment d'arrêter d'être montré |
| `Entry` | Une réussite dans une boîte : séquence, état, moments de réception et de règlement |
| `EntryState` | `Pending`, `Seen`, `Withdrawn` ou `Lapsed` |
| `Group` | Les entrées en attente d'une source et d'un genre : les premières montrées, le compte |
| `Celebration` | Ce que la fenêtre montre ensuite : un groupe, le mouvement, le son, les groupes restants |
| `Preferences` | Si une fenêtre célèbre, son mouvement et son son, pour un membre |
| `Motion` | `Full`, `Reduced` ou `None` |
| `Limits` | Les entrées en attente et les entrées par membre, les entrées montrées par groupe, la page d'historique |
| `CelebrationChanged` | Signalé après chaque acte, identifiants seulement |
| `Change` | Ce qui s'est passé dans une boîte |
| `Contract\CelebrationStore` | L'endroit où les boîtes et les préférences sont gardées |
| `Contract\CelebrationObserver` | Ce que l'application fait des événements |
| `Store\InMemoryCelebrationStore` | Le dépôt en mémoire |
| `Exception\CelebrationException` | Toute exception de la bibliothèque |
| `Exception\InvalidCelebration` | Une valeur hors limites |

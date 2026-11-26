<!-- phil-dev source-sha256=55756a7eea6073c7a5e56e3213af9ac9f6b746ad022a471834f3d5862219d138 -->
# Vues des joueurs

Un navigateur qui détient tout le paquet laisse n'importe qui lire l'ordre de la pioche, quoi que la page montre. La
bibliothèque ne donne jamais la partie au navigateur : `CardTable::view()` — ou `DeckState::seenBy()` — construit, sur
le serveur, ce qu'un joueur peut voir, et cette vue est la seule chose à envoyer.

## Ce qu'un joueur voit

| Carte | Vue comme |
|---|---|
| Dans la pioche | Rien : la zone ne montre que son nombre de cartes |
| Face visible, hors de la pioche | Son identifiant et la face qu'elle montre, par tous |
| Face cachée, dans la main de celui qui regarde | Son identifiant et sa face, par lui seul |
| Toute autre carte | Son dos seulement : ni identifiant ni face |

Un spectateur — une vue sans joueur — voit les cartes face visible et les dos des autres.

```php
$view = $table->view($game, $alice);

echo json_encode($view->toArray());
```

```json
{"viewer": "u-101", "commitment": "59a3…", "version": 3, "finished": false, "zones": [
  {"zone": "draw", "count": 38, "cards": []},
  {"zone": "hand:u-101", "count": 7, "cards": [{"card": "card-12", "face": "QS", "back": "back", "face_up": false}, …]},
  {"zone": "hand:u-102", "count": 7, "cards": [{"card": null, "face": null, "back": "back", "face_up": false}, …]}
]}
```

## Ce qu'une vue ne contient jamais

- L'ordre de la pioche, ni aucune carte qui s'y trouve.
- L'identifiant ou la face d'une carte cachée : un identifiant qu'un navigateur pourrait suivre dirait quelle carte
  cachée est allée où.
- La graine, la graine du client ou le journal des gestes, qui donneraient l'ordre de chaque mélange.

Les tests le vérifient sur des parties au hasard : pour chaque joueur et pour le spectateur, après chaque geste, aucune
carte cachée n'apparaît dans la vue.

## Ce que l'application garde en tête

- Un dos est une information visible : un paquet dont les dos diffèrent — deux paquets mélangés ensemble — montre la
  différence, comme une vraie table. Donnez à chaque carte d'un même paquet le même dos.
- Un geste désigne une carte par son identifiant : un joueur désigne les cartes qu'il voit. Pour prendre une carte
  cachée, un geste désigne sa zone — un `Draw` au hasard dans la main d'un autre joueur, par exemple.
- Poussez une nouvelle vue à chaque joueur après chaque geste — à chacun la sienne, jamais une seule vue pour tous.

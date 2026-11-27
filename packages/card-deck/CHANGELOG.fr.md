<!-- phil-dev source-sha256=b916e146073bac3414f87b9dd8f9c96f0c2d3cbbc25b4f4c6ed77e12c7c0fba9 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/card-deck` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-26

### Ajouté

- `CardId`, `PlayerId`, `GameId` et `Face` : des noms opaques de 1 à 64 lettres, chiffres, points, traits de
  soulignement et traits d'union ASCII.
- `Card`, `Deck` et `StandardDeck` : des cartes avec une identité distincte de leurs 1 à 16 faces et de leur dos, des
  paquets de 1 à 1 024 cartes dans leur ordre déclaré, écrits dans des tableaux et relus, et le paquet de 52 cartes aux
  enseignes françaises avec autant de jokers que demandé.
- `ZoneId` et `ZoneKind` : la pioche, la défausse, la table, les mains des joueurs et des piles nommées, écrites comme
  texte et relues ; au plus 256 zones dans une partie.
- Les gestes `Shuffle`, `Draw` — du dessus, du dessous ou au hasard —, `Deal` face cachée ou face visible, `Discard`,
  `Flip`, `TurnFace`, `Move` et `Collect`, écrits dans des tableaux, et `GestureCodec`, qui les relit.
- `DeckState` : l'état d'une partie, chaque carte dans une seule zone ; un début qui mélange le paquet à partir du
  nombre 0 du germe, distinct d'un `Shuffle` pendant la partie ; des gestes qui laissent l'état tel qu'il était quand
  ils sont refusés ; une zone vide distincte d'une zone de jokers ; la relecture d'un journal et l'empreinte d'un état.
- `Shuffler` : le mélange de Fisher-Yates sur le générateur aléatoire d'un germe de `tilttek/dice` ; des tirages au
  hasard à partir des nombres qui suivent.
- `View\PlayerView`, `View\ZoneView` et `View\SeenCard` : ce qu'un joueur ou un spectateur peut voir — sa main, les
  cartes face visible et le nombre de cartes de chaque zone, jamais l'ordre de la pioche ni une carte cachée.
- `CardTable`, `GameRecord` et `Reveal` : ouvrir une partie et publier l'engagement envers son germe, la lancer avec le
  germe des joueurs, jouer des gestes contre une version, la voir, la terminer et publier ce dont chacun a besoin pour
  la vérifier, avec au plus 10 000 gestes par partie.
- `Contract\GameStore` et `Store\InMemoryGameStore` : le port du stockage, écrit par-dessus la version précédente
  seulement, et le stockage en mémoire pour les tests.
- Des exceptions sous une seule interface `CardDeckException` : `InvalidDefinition`, `IllegalGesture`, `UnknownGame` et
  `ConcurrentChange`.
- Des tests de propriétés sur des parties aléatoires, une cible de fuzzing du codec des gestes et un banc d'essai de la
  relecture.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.

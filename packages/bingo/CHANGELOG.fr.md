<!-- phil-dev source-sha256=f33899d11106ec44647634c847b9cb0f2c2888f1fa2f223cf034a231697e8e7d -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/bingo` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.2.1-alpha - 2026-11-25

### Modifié

- Les prérequis de développement demandent la dernière version mineure de `twig/twig`, 3.31.

### Sécurité

- `twig/twig` antérieur à 3.31.1 refusé, pour les correctifs de sécurité de cette version : son bac à sable vérifie la
  politique à plus d'endroits, et un gabarit ne peut plus remplacer le contenu d'un `Markup`.

## 0.2.0-alpha - 2026-11-14

### Modifié

- Un événement de la partie ne peut plus se perdre quand un écouteur échoue. Une case cochée, un tirage ou une victoire
  est écrit avec son événement, dans la même version de la carte ou de la partie, et l'événement y reste jusqu'à ce
  qu'il ait été distribué sans exception : `Card::undelivered()` et `Card::markDelivered()`, `BingoGame::undelivered()`
  et `BingoGame::markDelivered()`. Un écouteur qui lève une exception laisse son événement non remis, les autres
  événements sont distribués quand même, et la première exception est levée de nouveau à la fin ; le même appel répété —
  le geste enregistré de nouveau, la case tirée de nouveau, la réclamation faite de nouveau — le distribue de nouveau.
  Chaque remise est un changement de plus de la carte ou de la partie.
- La victoire d'une carte prend sa place parmi les winners() de la partie juste avant que son événement soit distribué,
  pour qu'une victoire dont l'étape a échoué soit reprise avec son événement.
- `SquareTicked` porte la clé d'idempotence du geste qui a coché la case, `gesture`, un nouvel argument de son
  constructeur.
- `Activity\BingoActivityObserver` implémente `ResumableObserver` de `tilttek/activity` : le même geste enregistré de
  nouveau, ou la même annulation donnée de nouveau, lui est remis encore une fois.
- Le paquet requiert `tilttek/activity` `^0.1.1@dev`, dont `Activity\BingoActivityObserver` implémente
  `ResumableObserver`.

### Corrigé

- Une cellule cochée par plus de gestes que `Card::MOST_KEYS` pouvait être décochée quand les gestes dont elle se
  souvenait étaient annulés, alors qu'un geste dont elle ne pouvait pas se souvenir tenait encore. Une telle cellule est
  maintenant épinglée : elle reste cochée pour de bon, et `Card::pinned()` l'énumère. Une carte enregistrée avant se lit
  sans cellule épinglée ; rien n'est migré.

### Ajouté

- `Bingo::deliver()` : les tirages non remis de la partie et, avec un numéro de série, les cases cochées et la victoire
  non remises de la carte distribués de nouveau, pour reprendre après un échec.
- `key()` sur `BingoWon`, `SquareTicked` et `SquareCalled` : une clé stable, la même chaque fois que l'événement est
  distribué de nouveau, sous laquelle un écouteur idempotent agit une seule fois.
- La remise des événements documentée : remise au moins une fois, écouteurs idempotents avec la clé de `key()`, reprise,
  et les cartes et les parties enregistrées avant.

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `Bingo` : des parties créées et fermées, une carte par joueur distribuée au premier tirage, des cases tirées pour
  chaque carte, des gestes du journal d'activité enregistrés et annulés — les cases qu'un geste coche sur une carte en
  un seul changement —, des réclamations sur sa propre carte et des vérifications par numéro de série, chaque changement
  refait sur un conflit, jusqu'à huit fois.
- Les lots imprimés : `Bingo::print()` distribue de 1 à 1 000 cartes tenues par aucun joueur dans une partie dont les
  cases sont tirées, leurs numéros de série réservés d'un coup ; `SerialLabel`, le numéro de série et un code de
  contrôle de huit caractères de la base 32 de Crockford tiré du secret, lu avec souplesse à partir d'au plus 32
  octets ; `Bingo::label()` et `Bingo::verifyLabel()`, qui refuse un code qui n'est pas celui du numéro de série avant
  la vérification de `verify()` ; `View\PrintedCardView` et `templates/print.html.twig`, une section par carte avec son
  étiquette, les mots donnés par `labels`, le bloc `footer`.
- `BingoGame` et `TickMode` : un réservoir, une grille, des motifs gagnants et un mode — cochée par l'activité, gagnée
  d'elle-même, ou tirée par l'animateur, gagnée par une réclamation —, jusqu'à 100 000 cartes, les cases tirées et les
  gagnants dans l'ordre.
- `Card` : un numéro de série à partir de 1, un joueur ou aucun pour une carte imprimée, une disposition, les clés
  d'idempotence des gestes qui ont coché chaque cellule, `tickAll()` pour les cases d'un geste en une seule version, une
  cellule décochée quand tous ses gestes sont annulés à moins que la carte ait gagné, et une victoire gardée une seule
  fois avec son motif et son moment.
- `CardDealer` : des dispositions et des étiquettes tirées d'un secret du serveur d'au moins 32 octets, amorcé par le
  HMAC-SHA256 de la partie et du numéro de série, choisies et mélangées par un `Xoshiro256StarStar` avec germe ; des
  empreintes qui gardent les cartes d'une partie uniques, des numéros de série dont la disposition est prise sautés
  jusqu'à 32 fois.
- `Claim` : les cellules cochées, le centre libre compris, et le motif gagné ; une carte qui ne correspond pas à son
  germe refusée, une réclamation sur la carte d'un autre joueur refusée, une carte d'une partie fermée vérifiée et
  jamais gagnée.
- `Square`, `SquarePool` de 1 à 500 cases, `Trigger` par motif de verbe et objet facultatif, et `Grid` de 3 à 9 cellules
  avec un centre libre facultatif.
- `WinPattern` — ligne, colonne, diagonale, carte pleine, quatre coins et ensembles de cellules personnalisés — et
  `WinPatterns`, de 1 à 16 vérifiés dans l'ordre, avec `WinPatterns::line()`.
- `Activity\BingoActivityObserver` : les cartes cochées et décochées à partir des entrées du journal d'activité.
- Des événements qui ne portent que des identifiants : `BingoWon`, avec un joueur nul pour une carte imprimée,
  `SquareTicked` et `SquareCalled`.
- `Mercure\BingoPublisher` : les cases tirées et les cartes gagnées poussées comme mises à jour Mercure privées sur le
  sujet de la partie, les cases cochées sur celui de la carte, sans nommer de joueur ; `symfony/mercure` suggéré.
- `View\CardView`, `View\CellView` et `templates/card.html.twig` : un tableau de cellules dont les cases cochées sont
  dites aux lecteurs d'écran, les mots de la page donnés par `labels`, le bloc `won` ; le contrôleur Stimulus `card` qui
  distribue `bingo:won` sur la fenêtre une seule fois par carte dans un onglet du navigateur, pour la célébration du kit
  PHIL UX.
- `Contract\BingoStore` avec versions et empreintes, et `Store\InMemoryBingoStore`.
- Des exceptions sous `Exception\BingoException` : `InvalidBingo`, `BingoRefused` et `BingoConflict`.
- Des bancs d'essai d'une disposition, de la vérification de chaque motif et d'un geste enregistré.
- Une cible de fuzzing de l'étiquette d'une carte imprimée et de sa vérification.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README ; `CONTRIBUTING.md`, `SECURITY.md` et
  `CODE_OF_CONDUCT.md`.

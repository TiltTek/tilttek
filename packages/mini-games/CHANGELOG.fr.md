<!-- phil-dev source-sha256=df70398b3b4fab4c116f901ec26b3c147e38a0448f30a16e9c818046f2d02bfd -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/mini-games` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-26

### Ajouté

- `Contract\MiniGame` : un jeu de `tilttek/game-engine` joué seul, qui déclare sa notation, ses commandes et la façon de
  partager un résultat.
- `ScoreOrder` et `Scoring` : plus haut ou plus bas est meilleur, déclaré par chaque jeu, avec une comparaison stricte,
  des records établis par de meilleurs pointages seulement et le meilleur de certains pointages.
- `Control` : les actions d'un jeu et les touches qui les font, pour que chaque jeu se joue au clavier seul.
- `Day`, `DailySeed` et `DailyGames` : le jeu du jour, le même pour chaque joueur d'un jour à partir d'un HMAC du jeu et
  du jour sous une clé dérivée du secret du site par HKDF, à une table propre à chaque joueur ; le pointage envoyé une
  seule fois au collecteur de pointages à la fin de la partie ; le journal d'une table finie, germe compris, une fois
  son jour terminé.
- `ShareGrid`, `Cell` et `CellSymbols` : un résultat partagé en texte, en carrés de couleur ou en caractères simples,
  qui dit comment chaque étape s'est passée et jamais ce qu'elle était.
- `Ending` et `Tone` : une victoire célébrée, toute autre fin annoncée avec bienveillance ; un record établi par un
  meilleur pointage seulement.
- `DailyScore`, `DailyHistory`, `Contract\ScoreSink`, `Store\NullScoreSink` et `Store\InMemoryScoreSink` : les pointages
  des jeux du jour envoyés à un port, et l'historique d'un joueur — jours joués, victoires, meilleur pointage,
  répartition des pointages — qui ne compte aucune série.
- `MiniGames` : les mini-jeux d'une application, par nom, et les mêmes jeux pour les `Tables` du moteur.
- `Game\BullsAndCows` et `Game\Code`, `Game\HigherOrLower` : deux jeux traditionnels écrits comme modèles, l'un où moins
  de propositions est meilleur, l'autre où plus de bonnes réponses est meilleur.
- Des exceptions sous une seule interface `MiniGameException` : `InvalidMiniGame` et `SeedNotRevealable`.
- Une cible de fuzzing des propositions de Bulls and Cows et un banc d'essai d'un jeu du jour entier.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.

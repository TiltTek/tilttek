<!-- phil-dev source-sha256=d92b6d63fa6533d05e72fbd1eee7e4617b13dd75715ca097dd282b21a29cea70 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Contract\MiniGame` | Un jeu du moteur joué seul, avec son pointage, ses commandes et son partage |
| `Scoring` | Dans quel sens vont les scores d'un jeu, et la statistique de siège qui porte le score |
| `ScoreOrder` | Plus haut ou plus bas est mieux : comparaison stricte, records, le meilleur de quelques scores |
| `Control` | Une action d'un jeu et les touches qui la font |
| `Day` | Un jour du calendrier du site, lu, comparé et numéroté |
| `DailySeed` | La graine d'un jeu un jour donné, tirée du secret du site |
| `DailyGames` | Les tables des joueurs des jeux quotidiens : démarrer, jouer, voir, partager, fin, et le journal une fois la journée terminée |
| `DailyScore` | Le résultat du jeu quotidien d'un joueur, envoyé au puits de scores |
| `DailyHistory` | Les scores quotidiens d'un joueur pour un jeu, additionnés, sans série |
| `Contract\ScoreSink` | Le port où vont les scores |
| `Store\NullScoreSink`, `Store\InMemoryScoreSink` | Un puits qui ne garde rien, et un puits en mémoire pour les tests |
| `Ending`, `Tone` | Comment une partie s'est terminée pour son joueur : une victoire célébrée, ou une partie terminée racontée avec bienveillance ; un record ou non |
| `ShareGrid`, `Cell`, `CellSymbols` | Un résultat partagé en texte, ses cellules et les symboles avec lesquels elles s'écrivent |
| `MiniGames` | Les mini-jeux d'une application, par nom, et les mêmes jeux pour le moteur |
| `Game\BullsAndCows`, `Game\Code` | Le jeu de devinette d'un code de chiffres différents, et ses codes |
| `Game\HigherOrLower` | Le jeu de dix choix, plus grand ou plus petit |
| `Exception\MiniGameException`, `InvalidMiniGame`, `SeedNotRevealable` | [Exceptions](exceptions.md) |

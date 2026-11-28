<!-- phil-dev source-sha256=23be92597e4e9e64ebb64e72b4fc18e84453a0bc0c6153bd2a5c4e387e5e3860 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Contract\TavernGame` | Un jeu du moteur au tour par tour qui garde ses propres tours et accepte le délai d'un tour |
| `Turns` | À qui est le tour, gardé dans les données du jeu |
| `TavernTables` | Ouvre et joue les tables, informe le minuteur de tour, joue les délais, montre les vues et les fins |
| `Contract\TurnTimer` | L'horloge facultative des tours |
| `Store\NullTurnTimer`, `Store\InMemoryTurnTimer` | Aucune horloge, et les tours en cours en mémoire pour les tests |
| `Ending`, `Tone` | Comment une partie s'est terminée pour un siège : une victoire, une victoire partagée ou une belle partie |
| `TavernGames` | Les jeux de taverne d'une application, par nom, et les mêmes jeux pour le moteur |
| `DiceCup` | Des dés à six faces lancés par `tilttek/dice` avec le hasard d'un coup |
| `Contract\Simulation` | Une position jouée au hasard jusqu'à sa fin |
| `Odds\Estimator`, `Odds\Estimate`, `Odds\Trial` | Des chances sur des fins simulées, victoires, égalités et défaites à part |
| `Odds\CargoChase` | Le tour d'un siège à Navire, Capitaine et Équipage, poursuivi jusqu'à sa fin |
| `Game\Pig` | Le jeu de dés où l'on tente sa chance |
| `Game\ShipCaptainCrew` | Le jeu de dés du navire, du capitaine, de l'équipage et de la cargaison |
| `Game\CrazyEights` | Le jeu de cartes où l'on se défait de sa main, les huit étant des jokers |
| `Exception\TavernGamesException`, `InvalidTavernGame` | [Exceptions](exceptions.md) |

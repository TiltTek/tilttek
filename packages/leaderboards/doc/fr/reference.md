<!-- phil-dev source-sha256=7e2dae39552254045469df898dd9b32b38e96a1840b936ad68baddf3d354617b -->
# Référence

| Type | Rôle |
|---|---|
| `Leaderboards` | Classements, rangs, divisions, valeurs rapportées, titres, équipes, apparences, instantanés, exportation et effacement |
| `LeaderboardProjection` | Les scores tenus à jour à partir du journal, les annulations, les reconstructions, fenêtres glissantes |
| `LeaderboardObserver` | Informé des instantanés pris |
| `Board`, `Boards` | Un classement ; les classements d'une application |
| `Measure`, `Ties` | Ce sur quoi un classement porte ; comment il départage les scores égaux après les critères de départage |
| `TieBreak`, `Aggregate`, `Direction` | Une mesure qui départage les scores égaux ; ce qu'elle réunit ; dans quel sens elle ordonne |
| `Ranking` | L'ordre des scores d'un classement, leurs rangs, et les clés qui trient dans cet ordre |
| `Title` | Un titre détenu à partir d'un seuil |
| `Team`, `TeamRow`, `Chance` | Une équipe que donne l'application ; sa ligne dans un classement d'équipes ; la chance qu'un classement lui ajoute |
| `Contribution`, `Score` | Ce qu'une activité a apporté à un membre dans un classement ; le score d'un membre dans une période |
| `Visibility`, `Appearance` | Comment un membre paraît aux autres ; son choix et son pseudonyme |
| `Circle` | Les membres entre lesquels un classement se fait |
| `Row`, `Standing` | Une ligne telle qu'un spectateur la voit ; un classement dans une période tel qu'un spectateur le voit |
| `Placing`, `Snapshot` | La ligne d'un membre dans un instantané ; un classement figé à la fin d'une période |
| `Erasure` | Ce qu'a retiré l'effacement d'un membre |
| `Names` | Les noms des classements et des périodes |
| `Period\Period`, `Period\PeriodKind` | Une période et son code ; les genres de périodes |
| `Period\Schedule` | Les périodes d'un classement : à un moment, par code, la dernière terminée |
| `Store\ScoreStore`, `Store\InMemoryScoreStore` | Où sont gardés les contributions et les scores |
| `Store\Valkey\ValkeyScoreStore` | Les contributions et les scores dans les ensembles triés de Valkey |
| `Contract\ValkeyConnection`, `Store\Valkey\RawCommandConnection` | Une connexion à Valkey ; une par l'extension redis, Relay ou Predis |
| `Store\SnapshotStore`, `Store\InMemorySnapshotStore` | Où sont gardés les instantanés |
| `Store\AppearanceStore`, `Store\InMemoryAppearanceStore` | Où sont gardés les choix des membres |
| `League\League`, `League\Formation` | Les paliers d'un classement hebdomadaire, la taille d'une division, combien montent et descendent ; comment ses divisions sont formées |
| `League\Leagues` | Divisions occupées, semaines réglées, paliers, exportation et effacement |
| `League\Membership`, `League\Movement` | La division d'un membre dans une semaine ; où il est allé à la fin |
| `League\Division` | La division d'un membre telle qu'il la voit, avec ses zones |
| `League\Settlement`, `League\LeagueObserver` | Une semaine réglée ; informé de chaque semaine réglée |
| `Store\LeagueStore`, `Store\InMemoryLeagueStore` | Où sont gardés les paliers, les appartenances et les semaines réglées |
| `Exception\LeaderboardsException` et ses quatre exceptions | [Exceptions](exceptions.md) |

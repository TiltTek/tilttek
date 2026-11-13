<!-- phil-dev source-sha256=c6a20141b3179f6af497534a6a0fd29b313ba16ecf9780693c53643348bd16a8 -->
# Référence

| Type | Rôle |
|---|---|
| `Contract\Game` | Les règles d'un jeu, derrière un seul contrat |
| `GameDefinition` | Les phases, transitions, sièges, options, coups hors tour, reprise, version, préférences et statistiques d'un jeu |
| `Phase`, `TurnOrder` | Une phase, qui y agit et combien de coups terminent un tour ; les quatre ordres de tour |
| `Transition` | Un changement de phase, les phases qu'il quitte et la phase où il mène |
| `Phases` | Les phases comme machine à états de Symfony Workflow, et son diagramme Mermaid |
| `Option`, `OptionKind` | Une option qu'un jeu offre, son genre, sa valeur par défaut et ses valeurs permises |
| `TableOptions` | Les options d'une table, vérifiées par l'OptionsResolver |
| `Preferences` | Les préférences d'un joueur, vérifiées par l'OptionsResolver, données à la vue de son siège |
| `Statistic`, `StatisticScope` | Une statistique qu'un jeu rapporte à sa fin, pour la table ou pour chaque siège |
| `Statistics` | Les valeurs des statistiques que rapporte une issue |
| `Engine` | Ouvre une table, joue un coup, rejoue un journal ou met un état à jour, le vérifie contre l'engagement sur son germe, reprend un coup, donne la vue d'un siège |
| `Table` | Le journal et l'état d'une table après une étape |
| `Step` | Un coup appliqué : le coup à ajouter et l'état auquel il mène |
| `GameState` | L'état où mène un journal : phase, tour, initiative, données et issue ; écrit et relu en valeurs simples |
| `Turn` | À qui le tour : ronde, ordre, siège qui agit, sièges en attente, coups du tour |
| `Outcome` | Comment une partie s'est terminée : les gagnants, la raison et les statistiques |
| `Move` | Un coup : type, siège et charge utile ; les coups du moteur |
| `LoggedMove` | Un coup tel que le journal le garde, numéroté, avec la mention qu'il a tiré au hasard ou non |
| `GameLog` | La vérité d'une table, écrite et relue sous forme de valeurs simples, avec l'engagement sur son germe |
| `LogHeader` | Ce qu'un journal contient en plus de ses coups : germe, sièges, options, nombre de coups, révision et filiation |
| `Snapshot` | Un état tel qu'un cache le garde, avec la révision et l'engagement dont il est construit |
| `Randomness` | Le hasard d'une étape, tiré du germe de la table et de la place de l'étape ; l'engagement sur un germe |
| `Tables` | Les tables d'une application, conservées, mises en cache, protégées, lues une fois par requête et publiées |
| `Games` | Les jeux qu'une application offre, par nom |
| `TableUpdate` | Ce qu'un éditeur dit aux joueurs : des identifiants et des compteurs seulement |
| `Contract\LogStore`, `Store\InMemoryLogStore` | Où les journaux sont conservés |
| `Contract\StateCache`, `Store\InMemoryStateCache`, `Store\NullStateCache` | Où les états sont mis en cache |
| `Contract\TablePublisher`, `Store\NullTablePublisher` | Comment les joueurs apprennent qu'une table a changé |
| `Scene\RoleplayingScene` | Le jeu prêt à l'emploi pour les phases d'une scène de jeu de rôle |
| `Exception\GameEngineException` et ses cinq exceptions | [Exceptions](exceptions.md) |

<!-- phil-dev source-sha256=6a2e0ce5cae3acac663155b3abda471270785e9bc7a7a0f11e35afd082230130 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Guilds` | Fonde, admet, gère, lit et efface les guildes et leurs membres |
| `Guild` | Une guilde : référence, nom, admission, capacité, métadonnées, moment de la fondation |
| `Admission` | `Open` ou `OnRequest` |
| `Metadata` | Les entrées de l'application sur une guilde, bornées |
| `Role` | `Founder`, `Administrator` ou `Member`, et ce que chacun peut faire |
| `Membership` | Un membre d'une guilde, son rôle et depuis quand |
| `JoinRequest` | La demande en attente d'un membre pour entrer dans une guilde |
| `Limits` | Guildes par membre, demandes en attente par guilde, objectifs ouverts par guilde |
| `Goals` | Ouvre, alimente, lit, ferme et efface les objectifs de la piste de groupe |
| `Goal` | Un objectif : clé, guilde, nom, cible, ouverture, échéance, moments d'atteinte et de fermeture |
| `Contribution` | Les points qu'un membre a apportés à un objectif, sous une clé ou non |
| `GoalProgress` | Où en est un objectif : points, contributeurs, part, reste |
| `GuildTeamMembership` | Les guildes comme équipes de `tilttek/quests` |
| `GuildChanged` | Annoncé après chaque acte sur une guilde, des identifiants seulement |
| `Change` | Ce qui est arrivé à une guilde ou à un membre |
| `GoalReached` | Annoncé quand un objectif atteint sa cible, des identifiants seulement |
| `Refusal` | Pourquoi un acte est refusé |
| `Contract\GuildStore` | Où l'on garde les guildes, les adhésions et les demandes |
| `Contract\GoalStore` | Où l'on garde les objectifs et les contributions |
| `Contract\GuildObserver` | Ce que l'application fait des événements |
| `Store\InMemoryGuildStore` | Le magasin de guildes en mémoire |
| `Store\InMemoryGoalStore` | Le magasin d'objectifs en mémoire |
| `Exception\GuildsException` | Toutes les exceptions de la bibliothèque |
| `Exception\GuildRefused` | Un acte refusé, avec son `Refusal` |
| `Exception\UnknownGuild` | Aucune guilde sous une référence, ou aucun objectif sous une clé |
| `Exception\InvalidGuild` | Une valeur hors bornes |

<!-- phil-dev source-sha256=14028548ac348e1aee41d222b1fd3b4dfb7f7fb913ec0f8c8dc7204caf67b7a6 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/guilds` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-26

### Ajouté

- `Guild`, `Admission` et `Metadata` : une guilde sous la référence de l'application, ouverte ou sur demande, avec un
  nom de 1 à 100 caractères, une capacité de 2 à 1000 membres — 50 par défaut — et au plus 20 entrées de métadonnées.
- `Role`, `Membership` et `JoinRequest` : un seul fondateur, des administrateurs et des membres, chacun depuis un
  moment ; des demandes qui attendent le fondateur ou un administrateur.
- `Guilds` : fonder, rejoindre tout de suite une guilde ouverte et demander à rejoindre une guilde sur demande, retirer
  sa demande, approuver et refuser les demandes, quitter — le fondateur confie d'abord la guilde à un autre —, retirer —
  le fondateur n'importe qui d'autre, un administrateur des membres seulement —, promouvoir, rétrograder, confier la
  guilde, mettre à jour — jamais sous le nombre de membres qu'elle compte — et dissoudre ; personne n'est retiré pour
  inactivité.
- La lecture : une guilde, une adhésion, les membres par rôle puis par arrivée, leurs références pour une équipe ou un
  cercle, les guildes d'un membre ; l'effacement d'un membre, une guilde qu'il a fondée passée à son plus ancien
  administrateur ou membre, ou dissoute avec son seul membre.
- `Limits` : les guildes par membre, demandes comprises, les demandes en attente par guilde et les objectifs ouverts par
  guilde, par défaut 10, 100 et 10.
- `Goals`, `Goal`, `Contribution` et `GoalProgress` : la piste de groupe, un objectif de 1 à 1 000 000 000 de points
  qu'ouvre un administrateur, avant une échéance ou non, que les contributions des membres font avancer ensemble,
  comptées une seule fois sous une clé, jusqu'à ce qu'il soit atteint, fermé ou passé son échéance ; le total, les
  contributeurs et la propre part de chaque membre, sans classement ; les points gardés quand un membre quitte ou est
  effacé.
- `GuildTeamMembership` : les guildes comme équipes des quêtes d'équipe de `tilttek/quests` ; les références des membres
  comme cercle de `tilttek/leaderboards`, suggéré.
- `Contract\GuildStore` et `Contract\GoalStore`, avec `Store\InMemoryGuildStore` et `Store\InMemoryGoalStore`.
- `Contract\GuildObserver`, averti par `GuildChanged` et `GoalReached`, avec des identifiants seulement.
- Des exceptions sous une seule interface `GuildsException` : `GuildRefused` avec son `Refusal`, `UnknownGuild` et
  `InvalidGuild`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.

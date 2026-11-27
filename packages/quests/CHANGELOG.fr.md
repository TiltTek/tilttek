<!-- phil-dev source-sha256=405d72a10f25a56374828738cd728cbbab790a6237d71ca945e0f65db0b4c925 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/quests` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.3.0-alpha - 2026-11-17

### Modifié

- `Contract\TeamMembership`, et `NoTeamMembership`, sous lequel aucun membre n'appartient à aucune équipe : `Quests` en
  prend un comme troisième argument et lui demande, avant qu'un membre rejoigne une quête d'équipe sous une équipe — ou
  l'accepte de nouveau —, si le membre appartient à cette équipe ; un membre qui n'y appartient pas est refusé avec
  `QuestRefused`, et aucune inscription n'est écrite. Sans lui, `Quests` utilise `NoTeamMembership`, si bien qu'une
  quête d'équipe est refusée plutôt qu'acceptée par défaut. Pour migrer une application : implémentez `TeamMembership`
  sur ses dossiers des équipes, en répondant côté serveur, et passez-le à `Quests` ; les quêtes personnelles,
  communautaires et en duel ne changent pas.

## 0.2.0-alpha - 2026-11-14

### Modifié

- `Store\DuelStore::transition()` : un stockage ne garde un duel que si celui qui est sous sa clé a encore le statut
  dans lequel il a été lu, en une seule étape atomique ; un stockage écrit pour 0.1 l'implémente.
- `Duels` accepte, refuse, retire et fait expirer un duel seulement s'il attend encore une réponse — ou, pour une
  expiration, s'il est encore accepté — : de deux réponses données au même moment, l'une est gardée, l'autre est refusée
  avec `QuestRefused`, ou renvoie le duel quand elle a donné la même réponse. Le balayage laisse, sans le signaler, un
  duel qui a reçu une réponse depuis qu'il l'a lu.
- `QuestProjection` ne fait gagner un duel que s'il est encore accepté : un duel qui a expiré depuis sa lecture n'est
  pas gagné, et aucun accomplissement n'est écrit pour lui.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Names`, `Scope`, `StepMeasure` et `Step` : des étapes qui lisent des verbes par motif et des conditions de contexte,
  en comptant les activités ou en additionnant leurs quantités jusqu'à une cible de 1 à 10^12, une étape qui demande une
  seule activité étant une première fois.
- `Reward`, `QuestKind`, `Admission` et `Quest` : des quêtes de 1 à 50 étapes, ordonnées ou libres, de quatre genres —
  personnelle, communautaire, d'équipe, duel —, suivies automatiquement ou sur acceptation, à la suite d'une autre
  quête, qui déclarent jusqu'à 20 récompenses, et pour un duel la durée qu'il dure une fois accepté.
- `Window`, `Schedule`, `Occurrence`, `Permanent`, `Dated` et `ScheduleReader` : des quêtes ouvertes toujours, entre
  deux dates ou à partir d'une date ou jusqu'à elle, ou aux dates que calcule un calendrier propre à l'application,
  chaque occurrence nommée par un code.
- `QuestReader` : des quêtes à partir de valeurs simples — étapes, genre, ordonnée, admission, ouverture et fermeture, à
  la suite, récompenses, durée du duel — lues dans un fuseau horaire, les clés inconnues refusées, des lecteurs de
  calendrier qui ajoutent leurs propres clés.
- `Catalog`, `WritableCatalog`, `InMemoryCatalog` et `Chains` : les quêtes d'une application par code, depuis le code ou
  une configuration, chaque chaîne vérifiée — la quête suivie existe, est du même genre, et aucune chaîne ne boucle.
- `Contribution`, `Enrolment` et `Completion` : ce qu'une activité a apporté à une étape, l'acceptation d'un membre sous
  une équipe et l'abandon, une quête accomplie par occurrence et par sujet — le membre, l'équipe ou `community:<quest>`.
- `StepProgress`, `QuestProgress`, `Finish` et `ProgressCalculator` : une étape faite à la contribution qui atteint sa
  cible, des étapes ordonnées comptées à partir du moment où l'étape précédente a été faite, la quête accomplie avec sa
  dernière étape, et les membres dont l'activité a compté.
- `Evaluator` : le sujet que fait avancer l'activité d'un membre, le temps pendant lequel une quête enchaînée lui est
  ouverte, si l'activité d'un membre compte, et la progression d'un membre, d'une équipe ou de la communauté dans
  l'inscription de chaque membre.
- `QuestCompleted` et `QuestObserver` : une quête accomplie avec ses récompenses et les membres à qui elles vont, un
  duel gagné, un duel expiré.
- `QuestProjection` : une projection de `tilttek/activity` qui apporte chaque activité aux étapes qui l'acceptent tant
  que la quête est ouverte et que l'activité du membre compte, accomplit des quêtes et ouvre celles qui suivent, tranche
  les duels, reprend les contributions d'une activité annulée, et oublie une période avant une reconstruction sans
  annoncer de nouveau ce qui est accompli de nouveau. `QuestProjection::clearReplay()` oublie ce que la dernière
  réinitialisation a gardé pour la rejouer.
- `Quests` et `Erasure` : l'acceptation — sous une équipe pour une quête d'équipe —, l'abandon et la reprise, le statut
  et le tableau d'un membre, le journal des quêtes accomplies, et l'exportation et l'effacement d'un membre, `Erasure`
  disant ce que l'effacement d'un membre a retiré.
- `DuelStatus`, `Duel`, `DuelStanding` et `Duels` : des duels envoyés sous la clé de l'application, acceptés, refusés ou
  retirés, qui durent la durée de leur quête ou jusqu'à sa fermeture, des offres qui expirent, le balayage des duels
  dont le temps est écoulé, et où en est chaque camp.
- `ContributionStore`, `EnrolmentStore`, `CompletionStore` et `DuelStore`, avec `InMemoryContributionStore`,
  `InMemoryEnrolmentStore`, `InMemoryCompletionStore` et `InMemoryDuelStore` : des contributions écrites une seule fois
  par quête, étape et activité, lues par quête, membres et période ; des inscriptions par quête et par membre ; le
  journal des quêtes accomplies ; des duels par clé, ceux qui sont dus et ceux qui sont rouverts avant une
  reconstruction ; chacun avec son effacement d'un membre. Les implémentations en mémoire se vident avec `reset()`, pour
  un processus de longue durée.
- `QuestsException`, implémentée par `InvalidQuest`, `UnknownQuest`, `UnknownDuel` et `QuestRefused`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.

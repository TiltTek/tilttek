<!-- phil-dev source-sha256=6a5d5646ac88c6848367604e59d473c1a19c2bdb21c33ed36ebc2ae7dc0c9188 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/progression` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.2.0-alpha - 2026-11-14

### Modifié

- `ExperienceStore` et `MilestoneStore` gardent l'avis de chaque gain et de chaque jalon tant qu'il est dû : `add()` et
  `reach()` le gardent dû, `undelivered($subject)` les énumère et `markDelivered()` en note un comme donné. Les deux
  donnent aussi `ofMember($member)`, les lignes dont le sujet ou le membre est le membre. Un stockage écrit pour 0.1
  implémente les trois méthodes de chacun.
- `ProgressionProjection` avertit l'observateur au moins une fois : le gain et les jalons qu'il franchit sont écrits
  avec leurs avis dus avant que l'observateur soit averti, et notés comme remis après. Un observateur qui échoue les
  laisse dus ; l'entrée remise de nouveau, la prochaine activité du sujet sur la piste, ou `deliver()` les annonce, les
  niveaux de plusieurs gains dus à la fois. `StreakKeeper` annonce une série brisée avant d'enregistrer l'état, pour
  qu'un observateur qui a échoué soit averti de nouveau. Les observateurs doivent être idempotents.

### Ajouté

- `ProgressionProjection::deliver()` : les avis dus à un sujet donnés à l'observateur, pour reprendre après un échec.
- `Progression::gainsOfMember()` et `milestonesOfMember()` : ce que le membre et ses gestes ont gagné, ceux d'un
  personnage compris, pour une exportation.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Curve` et `Level` : l'expérience à laquelle commence chaque niveau, linéaire, quadratique, exponentielle ou une table
  que l'application écrit, de 2 à 1000 niveaux et au plus 10^15 d'expérience ; où se situe une quantité d'expérience —
  le niveau, l'expérience à laquelle il a commencé, le suivant, ce qui est gagné et ce qui manque, le pourcentage
  parcouru.
- `Period`, `Cap`, `ExperienceRule` et `Gain` : de l'expérience par unité de la quantité d'une activité ou par activité,
  pour des verbes par motif et des conditions de contexte, plafonnée par sujet sur un jour, une semaine ISO, un mois,
  une année ou depuis toujours ; l'expérience qu'une activité a apportée à un sujet sur une piste, par une règle, dans
  une saison.
- `Season`, `SeasonSchedule`, `SeasonLength`, `CalendarSeasons` et `NamedSeasons` : des saisons qui suivent le
  calendrier par mois (`2026-11`), trimestre (`2026-q4`) ou année (`2026`) dans un fuseau horaire, ou des saisons
  nommées et datées qui ne se chevauchent jamais et peuvent laisser du temps entre elles.
- `Subject`, `Milestone`, `Track` et `Tracks` : des pistes dont l'expérience va au membre ou à l'objet du geste, avec
  leurs règles, leur courbe, leurs saisons et leurs jalons d'expérience ou de niveau ; `Tracks::fromArray()` à partir
  d'une configuration, avec le fuseau horaire des plafonds et des saisons civiles ; `Names`, la vérification des noms
  des pistes, des règles, des jalons, des saisons et des séries — de 1 à 64 lettres minuscules, chiffres, `_`, `.` ou
  `-`, commençant par une lettre.
- `StreakUnit`, `Grace`, `StreakRule`, `StreakRules`, `StreakStatus` et `StreakCalculator` : des jours ou des semaines
  ISO d'affilée, une grâce d'un certain nombre d'unités manquées dans une fenêtre, des unités gelées, l'unité en cours
  pas encore finie, la série en cours et la meilleure, comptées sans visiter les unités où rien ne peut changer ;
  `StreakRules::fromArray()` à partir d'une configuration.
- `TimezoneResolver`, `FixedTimezone` et `ContextTimezone` : le fuseau horaire dans lequel se lit la journée d'un
  membre, un pour tous ou celui que porte le contexte de l'activité, écrit par l'application à partir du profil du
  membre.
- `LevelChange`, `ReachedMilestone` et `ProgressionObserver` : ce qu'annoncent la projection et le balayage — un niveau
  gagné ou perdu, un jalon atteint, une série brisée.
- `ExperienceStore`, `MilestoneStore` et `StreakStore`, avec `InMemoryExperienceStore`, `InMemoryMilestoneStore` et
  `InMemoryStreakStore` : des gains écrits une seule fois par piste, règle et activité, des totaux par saison sous forme
  de `Total`, l'expérience qu'une règle a donnée dans une période, les totaux avant une reconstruction ; des jalons
  atteints une seule fois par sujet et par saison ; des gestes de série une seule fois par série et par activité, des
  unités gelées, et l'état qui dit quand une série se brise ; chacun avec son effacement d'un membre. Les
  implémentations en mémoire se vident avec `reset()`, pour un processus de longue durée.
- `StreakState`, `StreakGesture`, `StreakBroken` et `StreakKeeper` : les gestes enregistrés et l'état mis à jour, une
  série brisée annoncée une seule fois — par un geste ultérieur ou par le balayage, dans le fuseau horaire du membre —,
  les annulations recomptées en silence, les unités gelées et dégelées.
- `ProgressionProjection` : une projection de `tilttek/activity` qui apporte l'expérience de chaque activité à ses
  pistes dans leurs saisons jusqu'aux plafonds, annonce les niveaux franchis et les jalons atteints, rend actives les
  unités de série des membres, reprend ce qu'une activité annulée a apporté, et oublie l'expérience et les gestes de
  série d'une période avant une reconstruction, en n'annonçant alors que les niveaux au-dessus de ceux détenus avant.
  `ProgressionProjection::clearReplay()` oublie ce que la dernière réinitialisation a gardé pour la rejouer.
- `Progression`, `Standing`, `SeasonResult` et `Erasure` : la situation sur une piste ou sur toutes, les saisons
  passées, les jalons atteints, les gains pour une exportation, les séries, les jours gelés et dégelés, le balayage des
  séries brisées, et l'effacement d'un membre avec les gains, les jalons et les séries qu'il a retirés.
- `ProgressionException`, implémentée par `InvalidProgression`, `UnknownTrack` et `UnknownStreak`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.

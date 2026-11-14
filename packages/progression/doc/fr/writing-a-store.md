<!-- phil-dev source-sha256=2e2cdb27dd48c6ba3df5726474ba0a74a6db72aa3b43c4e9fde9fc86d1fc909f -->
# Écrire un magasin

La progression est gardée dans trois magasins. Une application qui n'utilise pas le bundle les implémente sur son
stockage ; chacun doit réussir le cas de test de contrat que fournissent les tests de la bibliothèque.

## `ExperienceStore`

| Méthode | Contrat |
|---|---|
| `add(Gain)` | Écrit le gain une fois par piste, par règle et par activité, son annonce due ; faux quand il existe déjà |
| `undelivered(subject)` | Les gains du sujet dont l'annonce est due, dans l'ordre où ils sont survenus |
| `markDelivered(Gain)` | Note l'annonce du gain donnée ; rien quand le gain n'existe pas |
| `remove(activity)` | Retire et retourne chaque gain de l'activité, sous forme de liste |
| `total(track, subject, season)` | La somme des gains du sujet sur la piste dans la saison, le code vide pour aucune saison |
| `lifetime(track, subject)` | La somme de toutes les saisons |
| `seasons(track, subject)` | Un `Total` par saison, ceux en dehors de toute saison omis |
| `earned(track, rule, subject, since, until)` | Ce que la règle a donné de `since`, inclus, à `until`, exclu |
| `totalsSince(since)` | Les totaux complets de chaque piste, sujet et saison ayant un gain à partir de `since` |
| `of(subject)` | Les gains du sujet, dans l'ordre où ils sont survenus |
| `ofMember(member)` | Les gains dont le sujet ou le membre est le membre, dans l'ordre où ils sont survenus, pour une exportation |
| `forget(since)` | Supprime les gains à partir de `since`, tous quand il est null |
| `eraseMember(member)` | Supprime les gains dont le sujet ou le membre est le membre |

## `MilestoneStore`

`reach()` écrit un `ReachedMilestone` une fois par piste, par jalon, par sujet et par saison, son annonce due ;
`undelivered()` énumère ceux d'un sujet dont l'annonce est due et `markDelivered()` en note un donné ; `of()` énumère
ceux d'un sujet dans l'ordre où ils ont été atteints, `ofMember()` ceux dont le sujet ou le membre est le membre, pour
une exportation ; `eraseMember()` supprime ces derniers. Dans une base de données, une colonne booléenne `delivered`
sur chacune des deux tables garde l'annonce : vraie par défaut pour les lignes écrites avant elle, fausse à
l'insertion, et passée à vraie par une mise à jour conditionnelle.

## `StreakStore`

`add()` écrit un `StreakGesture` une fois par série et par activité ; `remove()` retourne et supprime les gestes d'une
activité ; `units()` énumère les unités actives d'un membre, triées, chacune une fois ; `freeze()`, `thaw()` et
`frozen()` gardent les unités gelées ; `state()`, `save()` et `discard()` gardent un `StreakState` par série et par
membre ; `alive(streak, before)` énumère les états qui ont une série en cours dont la dernière unité active est avant
`before` ; `forget()` supprime les gestes à partir d'un moment, et `eraseMember()` tout ce qui concerne un membre.

Les gains et les gestes sont écrits une seule fois même quand deux processus les écrivent au même moment : une clé
unique sur la piste, la règle et l'activité (la série et l'activité), et une insertion qui n'écrit rien sur un doublon.

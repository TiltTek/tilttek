<!-- phil-dev source-sha256=3e4341cbf7a21412bc9f25360d8bd57e24474f1175b81b48128ca567d6125d9b -->
# Écrire un magasin

La progression est gardée dans trois magasins. Une application qui n'utilise pas le bundle les implémente sur son
stockage ; chacun doit réussir le cas de test de contrat que fournissent les tests de la bibliothèque.

## `ExperienceStore`

| Méthode | Contrat |
|---|---|
| `add(Gain)` | Écrit le gain une fois par piste, par règle et par activité ; faux quand il existe déjà |
| `remove(activity)` | Retire et retourne chaque gain de l'activité, sous forme de liste |
| `total(track, subject, season)` | La somme des gains du sujet sur la piste dans la saison, le code vide pour aucune saison |
| `lifetime(track, subject)` | La somme de toutes les saisons |
| `seasons(track, subject)` | Un `Total` par saison, ceux en dehors de toute saison omis |
| `earned(track, rule, subject, since, until)` | Ce que la règle a donné de `since`, inclus, à `until`, exclu |
| `totalsSince(since)` | Les totaux complets de chaque piste, sujet et saison ayant un gain à partir de `since` |
| `of(subject)` | Les gains du sujet, dans l'ordre où ils sont survenus |
| `forget(since)` | Supprime les gains à partir de `since`, tous quand il est null |
| `eraseMember(member)` | Supprime les gains dont le sujet ou le membre est le membre |

## `MilestoneStore`

`reach()` écrit un `ReachedMilestone` une fois par piste, par jalon, par sujet et par saison ; `of()` énumère ceux d'un
sujet dans l'ordre où ils ont été atteints ; `eraseMember()` supprime ceux dont le sujet ou le membre est le membre.

## `StreakStore`

`add()` écrit un `StreakGesture` une fois par série et par activité ; `remove()` retourne et supprime les gestes d'une
activité ; `units()` énumère les unités actives d'un membre, triées, chacune une fois ; `freeze()`, `thaw()` et
`frozen()` gardent les unités gelées ; `state()`, `save()` et `discard()` gardent un `StreakState` par série et par
membre ; `alive(streak, before)` énumère les états qui ont une série en cours dont la dernière unité active est avant
`before` ; `forget()` supprime les gestes à partir d'un moment, et `eraseMember()` tout ce qui concerne un membre.

Les gains et les gestes sont écrits une seule fois même quand deux processus les écrivent au même moment : une clé
unique sur la piste, la règle et l'activité (la série et l'activité), et une insertion qui n'écrit rien sur un doublon.

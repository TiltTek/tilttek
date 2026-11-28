<!-- phil-dev source-sha256=d27a2307af1b4eb12202f9aa4dfebc31fc8557d041b9086cf36c6ca36df8b3bd -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/celebrations` seront consignés dans ce fichier.

Les mises à jour devraient suivre les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-27

### Ajouté

- `Achievement` : ce qu'un membre a obtenu, tel que l'écouteur de l'application le remet — la référence du membre, la
  source et le genre par des clés, ce qui a été obtenu et l'occurrence qui le rend unique (de 1 à 191 caractères ASCII
  visibles), le moment où il a été obtenu, au plus 10 paramètres pour son message, et peut-être le moment après lequel
  il n'est plus montré.
- `Celebrations` : recevoir une réussite une seule fois par membre, source et occurrence, une réussite signalée de
  nouveau étant renvoyée comme déjà gardée et non signalée ; retirer une récompense reprise, sans effet si on le signale
  deux fois ; les entrées en attente en groupes d'une source et d'un genre, la plus ancienne obtenue en premier, les
  égalités par réception ; le compte pour la cloche ; la prochaine célébration ; marquer comme vu par séquence, par
  groupe ou tout à la fois ; un historique lu par pages de 1 à 100 entrées, la dernière reçue en premier ; les
  préférences des membres ; l'effacement de la boîte et des préférences d'un membre.
- `Entry` et `EntryState` : la séquence d'une entrée, jamais réutilisée pour un membre, son état — en attente, vue,
  retirée ou caduque — et ses moments de réception et de règlement ; une entrée en attente devient caduque quand vient
  son moment d'être montrée, alors que la récompense demeure.
- `Group` et `Celebration` : les premières entrées d'un groupe, autant que les limites le permettent, avec son compte ;
  ce que la fenêtre montre ensuite, avec son mouvement, son son et les groupes restants.
- `Preferences` et `Motion` : une fenêtre active par défaut et désactivée par le membre sans que la boîte change,
  mouvement complet, réduit ou nul, un son désactivé par défaut ; un appareil qui demande de réduire les mouvements
  réduit le mouvement complet et n'en ajoute jamais.
- `Limits` : les entrées en attente par membre, 200 par défaut, au-delà desquelles les plus anciennes obtenues
  deviennent caduques ; les entrées par membre, 500 par défaut, au-delà desquelles les premières reçues des entrées
  réglées sont retirées ; les entrées montrées par groupe, 10 par défaut.
- `Contract\CelebrationStore`, avec `Store\InMemoryCelebrationStore`.
- `Contract\CelebrationObserver`, informé de `CelebrationChanged` avec son `Change`, identifiants seulement.
- `Exception\InvalidCelebration`, sous l'interface `Exception\CelebrationException`.
- Un banc d'essai de la boîte la plus pleine que permettent les limites par défaut : le compte, la prochaine
  célébration, une réussite reçue à la limite.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.

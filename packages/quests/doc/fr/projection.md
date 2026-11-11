<!-- phil-dev source-sha256=1ddff61bcf7560f2444b25be38829a26259cc8f7611c17de0918d086f2fc09f7 -->
# La projection

`QuestProjection` est une projection de `tilttek/activity` : le `Projector` lui remet, dans l'ordre, les entrées du
journal qu'elle n'a pas vues.

## Une activité enregistrée

Pour chaque quête dont les étapes acceptent l'activité :

1. la quête doit être ouverte au moment de l'activité, et l'activité du membre doit compter pour elle : toujours pour
   une quête suivie automatiquement, pendant l'inscription du membre pour une quête acceptée, pendant l'un des duels
   acceptés du membre pour un duel ;
2. une `Contribution` est écrite pour chaque étape qui l'accepte, une fois par quête, par étape et par activité, même
   quand le journal remet l'activité deux fois ;
3. la quête est évaluée pour son sujet sur son occurrence, à partir du moment où la quête qu'elle suit a été accomplie ;
   quand sa dernière étape est faite, une `Completion` est écrite et l'observateur est averti, avec les récompenses et
   les membres à qui elles vont ; les quêtes qui la suivent sont évaluées à leur tour ;
4. pour un duel, chacun des duels acceptés du membre dans lequel tombe l'activité est évalué, et le premier côté qui
   accomplit la quête le gagne.

## Une annulation

Les contributions d'une activité annulée sont retirées : une quête en cours recule. **Une quête accomplie reste
accomplie**, ses récompenses données ; l'application décide si elle les reprend.

## Reconstructions

Quand une règle change (une étape, une cible, un calendrier), `Projector::rebuild()` réinitialise la projection à
partir d'un moment : les contributions et les accomplissements à partir de ce moment sont oubliés, les duels gagnés
depuis redeviennent acceptés, et l'activité est rejouée selon le nouveau catalogue. **Une quête accomplie de nouveau
pendant la reconstruction n'est pas annoncée une seconde fois** ; une quête qui ne l'est pas est retirée sans annonce.
Les inscriptions et les duels sont des décisions des membres, pas des dérivés du journal : une reconstruction les garde
tels qu'ils sont maintenant.

## Ce qu'elle annonce

`QuestObserver` reçoit :

| Méthode | Quand |
|---|---|
| `completed(QuestCompleted)` | Une quête est accomplie : la quête, l'accomplissement, les récompenses et leurs membres |
| `duelWon(Duel)` | Un côté a accompli un duel en premier, après son `completed()` |
| `duelExpired(Duel)` | Le temps d'un duel s'est écoulé, ou son offre est restée sans réponse ; annoncé par `Duels` |

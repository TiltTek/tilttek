<!-- phil-dev source-sha256=02d813053f4e9326edb02cfc7d074db2c945c7d9f0b827fa3673012517dfe2bd -->
# Calendriers et enchaînements

## Quand une quête est ouverte

Le `Schedule` d'une quête donne, pour n'importe quel moment, l'`Occurrence` dans laquelle il tombe, ou null quand la
quête est fermée :

- `Permanent` : toujours ouverte, une seule occurrence dont le code est vide ;
- `Dated` : ouverte de son ouverture, incluse, à sa fermeture, exclue ; l'une ou l'autre peut rester ouverte, mais pas
  les deux ;
- un calendrier propre à l'application : des dates qui reviennent chaque année, lues dans la définition par un
  `ScheduleReader` donné à `QuestReader`. `tilttek/quests-bundle` en apporte un sur `phil/almanac`, sous la clé
  `recurs`.

Une occurrence a un code (vide pour une quête ouverte une seule fois, la date d'ouverture pour une quête qui revient),
une ouverture et une fermeture. **Une quête est accomplie une fois par occurrence et par sujet** : une quête qui revient
à chaque Pâques est accomplie de nouveau chaque année, et sa progression d'une année ne compte que l'activité de cette
année-là.

`QuestReader` lit `opens` et `closes` dans son fuseau horaire : minuit à Toronto n'est pas minuit en UTC.

## Enchaînements

`after` nomme la quête qu'une quête suit. **Accomplir la première ouvre la seconde pour le même sujet** : seule
l'activité à partir de ce moment compte pour elle, et la projection l'évalue tout de suite avec l'activité qui l'a
ouverte. Pour une quête qui en suit une qui revient, le plus récent accomplissement l'ouvre.

Un catalogue vérifie chaque enchaînement à sa construction : la quête suivie existe, elle est du même genre (un
enchaînement d'équipe reste un enchaînement d'équipe), et aucun enchaînement ne boucle sur lui-même. Un duel ne suit
aucune quête.

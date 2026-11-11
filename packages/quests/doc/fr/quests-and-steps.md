<!-- phil-dev source-sha256=3434f790a02145a72dc8e5dc1fa043d346a57f510d4505d845a18ebd77369427 -->
# Quêtes et étapes

Une `Quest` est une liste de `Step` qu'un sujet accomplit. Chaque étape lit le journal de `tilttek/activity`.

## Étapes

```php
new Step('play', new Scope(['game.played'], ['system' => 'dnd5e']), 3);
new Step('read', new Scope(['book.read']), 300, StepMeasure::Total);
new Step('publish', new Scope(['sheet.published'])); // a first time
```

- **La portée** nomme les verbes par motif (`game.played`, `game.*` ou `*`) et les faits que doit contenir le contexte
  de l'activité, comparés strictement : `true` n'est pas `1`.
- **La mesure** dit ce qui compte : `Count`, chaque activité acceptée compte pour un ; `Total`, sa quantité compte.
- **La cible** va de 1 à 10^12 ; une étape qui demande une seule activité est une première fois.
- **Le code d'une étape** compte 1 à 64 lettres minuscules, chiffres, `_`, `.` ou `-`, commence par une lettre et est
  unique dans sa quête.

## Ordonnées ou libres

Une quête a de 1 à 50 étapes. Les étapes libres se font dans n'importe quel ordre, et la quête est accomplie au moment
où sa dernière étape est faite. **Les étapes ordonnées** ne comptent une activité pour une étape qu'à partir du moment
où l'étape précédente a été faite ; une étape dont la précédente n'est pas faite ne compte rien. Une même activité peut
compter pour deux étapes à la fois quand elle tombe dans les deux.

## Récompenses

Une quête déclare jusqu'à 20 `Reward` : un genre, ce qu'elle nomme dans ce genre, une quantité :

```php
new Reward('points', 'xp', 50);
new Reward('badge', 'explorer');
new Reward('item', 'die:crystal-d20');
```

Elles ne sont jamais exécutées. Quand la quête est accomplie, `QuestCompleted` les porte avec les membres à qui elles
vont, et l'application les donne (un mouvement de `tilttek/points`, une attribution manuelle de `tilttek/badges`, un
objet d'une collection) par les paquets qu'elle a.

## Une définition à partir de valeurs simples

`QuestReader` lit une quête telle qu'une configuration ou une base de données la contient :

```yaml
winter-reading:
    kind: personal            # community, team or duel
    ordered: true
    admission: accept         # or automatic, for a personal or community quest
    opens: '2026-12-21'       # and closes, read in the reader's timezone
    closes: '2027-03-20'
    after: autumn-reading
    steps:
        - { code: start, verbs: ['book.opened'] }
        - { code: read, verbs: ['book.read'], total: 300, context: { genre: rpg } }
    rewards: [{ kind: points, code: xp, amount: 50 }]
```

Une étape sans `count` ni `total` demande une seule activité ; une étape sans `code` est nommée d'après sa place,
`step-1`, `step-2`… Une clé qu'aucune quête n'a est refusée : une faute de frappe ne passe donc pas en silence. Un duel
prend `duel: { length: P7D }`.

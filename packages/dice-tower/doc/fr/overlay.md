<!-- phil-dev source-sha256=65faaf41f8067b3a8635eb9480d708d42fef75cae46cbf6d0eb8a46c36866857 -->
# La surcouche

```twig
{{ dice_tower('table:7', {'member:42': 'Aelis', 'member:43': 'Brom'}) }}
{{ dice_tower(table, names, title: 'The tower', announce: false) }}
```

`dice_tower(table, names, title, announce)` dessine la tour d'une table pour un spectateur que le `TableAccess` de
l'application autorise, et rien pour un autre. La table est une `ResourceReference` ou sa chaîne. Les noms sont ceux
des lanceurs que la page peut montrer, par référence : ils n'atteignent que la page du spectateur. Le titre remplace
celui qui est traduit. Avec `announce` à faux, la liste n'est pas une région dynamique : une interface de dés
accessible sur la même page annonce plutôt les jets.

Dessiner la surcouche donne au spectateur l'abonnement au sujet de la table, dans le témoin d'autorisation de la
réponse.

## Ce qu'elle dessine

Une carte du kit UX, son titre, et les dernières entrées de la tour sous forme de liste ordonnée — une région `log`
que les lecteurs d'écran lisent à mesure qu'elle grandit, sauf si `announce` est faux. Chaque entrée donne le nom du
lanceur quand la page le connaît, l'expression de chaque instruction avec son total ou ce qu'elle a donné, et chaque
dé : la face d'un dé personnalisé par son libellé, les mots que dit un lecteur d'écran, et un dé qui n'a pas compté
suivi de « not counted » dans la langue de la page, pas seulement d'un style. Une tour vide le dit, et une ligne
d'état dit quand la connexion est perdue.

Les classes sont des crochets pour la feuille de style de l'application :

| Classe | Élément |
|---|---|
| `tilttek-dice-tower` | La carte |
| `tilttek-dice-tower__entries` | La liste |
| `tilttek-dice-tower__entry` | Une entrée, avec son `data-position` |
| `tilttek-dice-tower__roller` | Le nom du lanceur |
| `tilttek-dice-tower__result` | Une instruction |
| `tilttek-dice-tower__dice`, `tilttek-dice-tower__die`, `is-dropped` | Les dés, un dé, un dé qui n'a pas compté |
| `tilttek-dice-tower__empty`, `tilttek-dice-tower__status` | La tour vide, l'état de la connexion |

## Le contrôleur Stimulus

Le contrôleur `tilttek--dice-tower--tower` s'abonne au sujet de la table, avec le témoin du spectateur, et ajoute
chaque nouvelle entrée une seule fois et dans l'ordre. Quand l'abonnement s'ouvre — la première fois, et chaque fois
qu'il revient — il lit les entrées qui suivent la dernière position qu'il montre depuis la route des entrées
manquées, en retenant les entrées en direct jusqu'à ce qu'elles soient lues ; puis il les ajoute toutes, sans doublons.
Il garde les 200 dernières entrées sur la page.

| Valeur | Ce qu'elle contient |
|---|---|
| `hub` | L'URL où s'abonner ; aucune sans hub |
| `entries-url` | La route des entrées manquées ; aucune sans la route |
| `last` | La dernière position que la page montre |
| `faces` | Les libellés des faces des dés personnalisés, par dé et par face |
| `names` | Les noms des lanceurs, par référence |
| `dropped`, `offline` | Les mots d'un dé qui n'a pas compté, et d'une connexion perdue |
| `limit` | Combien d'entrées la page garde, 200 par défaut |

## Son événement

Chaque nouvelle entrée est émise sur l'élément sous le nom `tilttek--dice-tower--tower:drop`, qui remonte jusqu'à la
fenêtre, avec :

| Détail | Ce qu'il contient |
|---|---|
| `entry` | L'entrée, telle que le hub l'a poussée |
| `view` | Ce que la surcouche en montre : le nom, et chaque instruction avec ses dés par libellé |
| `text` | L'entrée en une phrase : `Aelis: 2d6+1 = 8 (4, 3)` |

Une interface de dés accessible l'annonce, une table de sons joue un son, un compteur compte :

```html
<div data-action="tilttek--dice-tower--tower:drop@window->my-listener#handle"></div>
```

<!-- phil-dev source-sha256=19c89707adff5cf6d974f2fa74a0ae98e0b4eb419843e0d0eb2413614827ac3f -->
# Gabarits

## Fonctions Twig

| Fonction | Renvoie |
|---|---|
| `tilttek_quests_board('member:44')` | L'état de chaque quête, sauf les duels, pour le membre : une liste de `QuestStatus` |
| `tilttek_quests_status('member:44', 'winter-reading')` | Le `QuestStatus` d'une quête |
| `tilttek_quests_duel('duel:812')` | Un duel et l'avancement de chaque camp : un `DuelStanding` |
| `tilttek_quests_domain()` | Le domaine de traduction des gabarits |

## La liste

```twig
{{ include('@TilttekQuests/list.html.twig', {statuses: tilttek_quests_board('member:44')}) }}
```

Chaque quête ouverte ou accomplie, avec son nom, son genre, son état, les étapes faites et la date de fermeture. Les
quêtes fermées sans avoir été accomplies et les quêtes verrouillées sont omises.

## Le détail

```twig
{{ include('@TilttekQuests/detail.html.twig', {status: tilttek_quests_status('member:44', 'winter-reading')}) }}
```

Le nom et la description de la quête, son état, son équipe, chaque étape avec une barre de progression (dans une liste
numérotée quand les étapes sont ordonnées), ses récompenses et le moment où elle a été accomplie.

## Traductions

Les gabarits sont fournis avec leurs textes en anglais et en français, sous le domaine `tilttek_quests`. L'application
écrit les noms de ses quêtes dans le même domaine : `quests.<code>.name`, `quests.<code>.description`,
`quests.<code>.steps.<step>`, et les récompenses sous `rewards.<kind>` avec `%code%` et `%amount%`. Les gabarits ne
fournissent aucun CSS : leurs classes suivent `tilttek-quests-list__…` et `tilttek-quests-detail__…`.

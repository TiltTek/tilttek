<!-- phil-dev source-sha256=0dc11ea9ea0be0aa1085076b660872cff9d91812bad8c58c37594869c1b94cf6 -->
# Suivre une quête

## Admission

- **`Automatic`** : chaque membre suit la quête dès qu'elle est ouverte, sans le demander ; toute son activité dans
  l'occurrence compte.
- **`OnAcceptance`** : seule l'activité après l'acceptation de la quête par le membre compte, et seule l'activité des
  membres qui l'ont acceptée est gardée.

Une quête d'équipe et un duel se rejoignent toujours par acceptation : la première sous une équipe, le second quand un
membre accepte un défi.

```php
$quests->accept($member, 'winter-reading');
$quests->accept($member, 'raid', ResourceReference::of('team', 'red'));
$quests->abandon($member, 'winter-reading');
```

L'acceptation est refusée quand la quête est un duel, fermée, verrouillée derrière la quête qu'elle suit, déjà accomplie
dans son occurrence, une quête d'équipe sans équipe ou une autre quête avec une équipe. Accepter une quête déjà suivie
retourne l'inscription telle quelle ; un membre d'équipe qui veut une autre équipe abandonne d'abord la quête.

## Abandon

Abandonner fait cesser de compter l'activité du membre à partir de ce moment. Abandonner une quête suivie
automatiquement écrit une inscription pour elle ; l'accepter de nouveau la reprend avec tout ce qu'elle avait compté.
Abandonner une quête acceptée, puis l'accepter de nouveau, compte à partir de la nouvelle acceptation. Une quête
accomplie ne peut plus être abandonnée.

## Où en est une quête

`Quests::status($member, $code)` lit un `QuestStatus` : la quête, son `QuestState`, son occurrence ouverte maintenant,
la progression de son sujet (le membre, l'équipe du membre ou la communauté), l'accomplissement qui l'a terminée, et
l'équipe.

| État | Quand |
|---|---|
| `Closed` | Pas ouverte maintenant, et pas accomplie dans sa dernière occurrence |
| `Locked` | Ouverte, mais la quête qu'elle suit n'est pas encore accomplie |
| `Available` | Ouverte, en attente de l'acceptation du membre, ou de son arrivée sous une équipe ; un duel ouvert maintenant |
| `UnderWay` | Suivie, ses étapes avancent |
| `Abandoned` | Le membre l'a abandonnée |
| `Completed` | Accomplie dans son occurrence en cours, ou dans sa dernière une fois fermée |

`Quests::board($member)` lit l'état de chaque quête, sauf les duels ; `Quests::completions($subject)` lit le journal
des quêtes qu'un membre, une équipe ou la communauté a accomplies.

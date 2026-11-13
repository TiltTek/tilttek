<!-- phil-dev source-sha256=d146a1cba023d26b85b5df1d8e248622830c37a396e914dc7084b974bff92f81 -->
# Pendant la partie

## Les signaux

Chacun, à la table, lève un signal à tout moment, sans expliquer pourquoi :

| Signal | Outil | Sens |
|---|---|---|
| `SignalKind::XCard` | carte X | retirer ce qui vient d'arriver |
| `SignalKind::Pause` | Script Change | arrêter la scène un moment |
| `SignalKind::Rewind` | Script Change | revenir en arrière et rejouer le moment autrement |
| `SignalKind::FastForward` | Script Change | passer la scène |
| `SignalKind::OpenDoor` | porte ouverte | quitter la partie |
| `SignalKind::CheckIn` | point d'étape | le meneur de jeu demande comment va chacun |
| `SignalKind::AllGood`, `Unsure`, `NotOkay` | point d'étape | les réponses : on continue, on ralentit, on arrête |

```php
$signal = $safety->raise($table, $participant, SignalKind::XCard, $isGameMaster, $now);
```

Un signal d'un outil que la table n'utilise pas lève `ToolNotEnabled` ; un point d'étape demandé par quelqu'un d'autre
que le meneur de jeu lève `NotGameMaster`.

## Anonymes par défaut

Un signal dit qui l'a levé seulement à une table qui a choisi des signaux nominatifs, et jamais une réponse à un point
d'étape ni le point d'étape lui-même. Un signal anonyme n'a pas d'auteur : le stockage n'en garde aucun, l'observateur
n'en diffuse aucun. Son identifiant est tiré au hasard, de sorte qu'il ne dit rien de qui l'a levé ni de quand.

## Qui voit un signal

| Signal | Vu par |
|---|---|
| la porte ouverte, les réponses à un point d'étape | le meneur de jeu seul |
| le point d'étape | toute la table |
| la carte X et Script Change | toute la table, ou le meneur de jeu seul quand la table a choisi le signal discret |

`TableSafety::signals()` donne au meneur de jeu tous les signaux de la table, et aux autres ceux que voit toute la
table, les 100 derniers ou ceux qui suivent un moment donné, dans l'ordre où ils ont été levés.

## Vu par le meneur de jeu

Le meneur de jeu marque un signal comme vu avec `TableSafety::acknowledge()` ; la méthode répond faux pour un signal que
la table n'a pas, ou déjà vu.

## L'observateur

Un `SignalObserver` donné à `TableSafety` est informé de chaque signal une fois gardé, et de chaque signal marqué comme
vu, pour le diffuser aux écrans de la table. `Signal::toArray()` est ce qu'il diffuse : les identifiants, le genre,
l'auditoire et les moments — la référence de l'auteur seulement pour un signal nominatif.

## Points d'étape

`TableSafety::checkIn()` compte les réponses données depuis le dernier point d'étape, sans dire qui a donné quoi :

```php
$tally = $safety->checkIn($table);

$tally->askedAt;           // when the game master asked, or null
$tally->allGood;           // 3
$tally->needsAttention();  // true when someone is unsure or not okay
```

Une réponse compte une fois par signal ; un point d'étape demandé de nouveau recommence le décompte.

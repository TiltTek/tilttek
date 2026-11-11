<!-- phil-dev source-sha256=68a3b48ca5d28c6cf81e610cf241319feaf3afbbdb3e843094e7c29c6ab6a886 -->
# Enregistrer l'activité

## Une activité

Une `Activity` est un fait : *ce membre a fait ce geste, sur cet objet, à ce moment*.

| Partie | Type | Règle |
|---|---|---|
| `key` | `IdempotencyKey` | De 1 à 255 octets, sans espace blanc ni caractère de contrôle |
| `member` | `ResourceReference` | Le membre, tel que l'application le référence : `member:44` |
| `verb` | `Verb` | Des segments en minuscules faits de lettres, de chiffres et de `_`, commençant chacun par une lettre, joints par des points ; 128 octets au plus |
| `occurredAt` | `DateTimeImmutable` | Le moment du geste, qui n'est pas toujours celui de son enregistrement ; entre les années 1970 et 9999, en UTC |
| `object` | `?ResourceReference` | Ce sur quoi portait le geste — une partie, un commentaire, une commande, un autre membre — ou rien |
| `quantity` | `int` | La quantité du geste : une partie, trois pages, une commande de 42 ; de 1 à 1 000 000 000 |
| `context` | `Context` | Jusqu'à 16 entiers, booléens ou chaînes de 100 octets au plus, nommés, que les règles peuvent lire |

```php
use Tilttek\Activity\Activity;

$activity = Activity::of(
    key: 'order:2026-0193:paid',
    member: 'member:44',
    verb: 'order.paid',
    occurredAt: $paidAt,
    object: 'order:2026-0193',
    quantity: 42,
    context: ['channel' => 'web', 'first_order' => true],
);
```

`Activity::of()` prend des valeurs simples ; le constructeur prend les objets-valeurs.

**Une activité ne porte aucun texte du membre.** Un commentaire est l'objet de l'activité, par référence. Le contexte
contient des faits que fixe l'application — un système de jeu, un niveau, un indicateur —, jamais ce que le membre a
tapé. Le journal reste ainsi à l'écart d'une modération ou d'une modification, et son export reste court.

## La clé d'idempotence

**La même clé compte une seule fois.** Enregistrer une activité dont la clé est déjà dans le journal renvoie l'entrée
déjà présente, avec `Appended::$new` à faux, et n'avertit aucun observateur. Construisez la clé à partir de ce qui rend
le geste unique, jamais à partir du moment de son enregistrement :

| Geste | Une bonne clé |
|---|---|
| Un commentaire publié | `comment:4512` |
| Une commande payée | `order:2026-0193:paid` |
| Une partie jouée par l'un de ses joueurs | `game:812:player:44` |
| Une visite quotidienne | `visit:member:44:2026-11-07` |

La même clé pour un geste **différent** — un autre membre, verbe, objet, quantité ou contexte — lève
`IdempotencyConflict`. C'est un bogue dans la façon dont l'application construit ses clés, et l'enregistrer ferait
perdre l'un des deux gestes. Le moment est exclu de la comparaison, car une tâche relancée peut le dater de nouveau.

## Le moment

**Le journal enregistre un geste daté dans sa fenêtre** : au plus trente jours avant son horloge et cinq minutes après,
par défaut. En dehors, `Journal::record()` lève `InvalidActivity` et n'enregistre rien. Un moment donné par le client,
pour une action faite hors ligne, ne peut alors ni remonter loin pour récolter les plafonds des jours passés, ni se
placer si loin de maintenant qu'une série ou une période compte ses unités par milliards.

```php
use DateInterval;
use Tilttek\Activity\Journal;

$journal = new Journal($log, $clock, past: new DateInterval('P7D'), ahead: new DateInterval('PT1M'));
$importer = new Journal($log, $clock, past: null); // a history imported once, with no bound in the past
```

## Annuler

```php
$journal->cancel('order:2026-0193:paid'); // the order was refunded
```

Une annulation est une entrée distincte, à la position suivante, qui porte l'activité annulée : rien n'est réécrit dans
le journal, et un lecteur la rejoue comme l'inverse de l'activité. Annuler deux fois renvoie la première annulation.
Annuler une clé jamais enregistrée renvoie null. Une activité annulée ne peut pas être enregistrée de nouveau sous la
même clé : un nouveau geste prend une nouvelle clé.

## Verbes déclarés

```php
$journal = new Journal($log, $clock, Verbs::of(['news.commented', 'game.*', 'order.paid']));
```

Avec des verbes déclarés, une activité dont le verbe ne correspond à aucun motif lève `UnknownVerb` avant toute
écriture. Le plus souvent, c'est une faute de frappe qui enregistrerait un geste qu'aucune règle ne lit jamais. Sans
motifs, tous les verbes sont acceptés.

## Observateurs

Un `JournalObserver` est averti de chaque **nouvelle** entrée, enregistrée ou annulée, juste après son écriture, dans
le même processus. Un doublon ne l'avertit pas. Un observateur qui échoue laisse passer son exception, une fois
l'entrée écrite. `LiveProjections` est l'observateur qui tient les projections à jour ([Projections](projections.md)).

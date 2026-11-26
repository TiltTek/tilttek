# At the table

`SessionPreparer` is the service of the preparations. It reads from the store, changes, keeps, then tells the observer
what happened, each fact once. It keeps no state between calls.

Each call that changes a preparation takes the member who asks, `$by`: only the preparation's game master changes it,
and anyone else is refused with `PreparationRefused`. A call that changes nothing — a secret already revealed marked
revealed, a ready preparation marked ready — returns the preparation as it is, keeps nothing and tells nothing.

## Creating and changing

`create()` keeps a new preparation: a draft at version 0, whose identifier no preparation has. Its parts start as they
are when written — every secret hidden, every clue not found, every scene planned, every item not checked — whatever the
preparation given says.

`change($preparation, $by)` replaces the parts of a preparation with a change made on the version read. The game master
may hand it to another member; it stays in its campaign. The session, the status and the facts of the table stay as
they were, and a part added starts as it does in `create()`: each is changed by its own method, so that the observer
hears of it. A change made on a version that is no longer the store's raises `PreparationConflict`: read it again and
make the change anew.

```php
$preparation = $preparer->preparation('prep-12');

$preparer->change(
    $preparation
        ->withScene(new Scene('belfry', 'The belfry', characters: ['mara']))
        ->moveScene('belfry', 'docks')
        ->withoutNote('old-idea'),
    'member-1',
);
```

## Linking to a session

`link($id, $session, $by)` links the preparation to a session by its identifier, or to none with null. A preparation
can be written before its session is scheduled, and moved to another session when the date changes.
`PreparationQuery` reads the preparations of a session.

## The facts of the table

| Method | Marks |
|---|---|
| `markScene($id, $scene, $status, $by)` | a scene played, skipped, or planned again |
| `markSecret($id, $secret, $revealed, $by)` | a secret revealed, or hidden again |
| `markClue($id, $secret, $clue, $found, $by)` | a clue found, or not; the secret stays as it is |
| `markItem($id, $item, $checked, $by)` | an item of the checklist checked, or not |

A key that no part has raises `InvalidPreparation`, even when the value asked is the part's start.

## Ready, closed, reopened

A preparation is a `Draft` while it is written. `ready()` marks it `Ready` once every required item of its checklist is
checked; otherwise it is refused, and the message lists the keys of the items missing. `close()` marks it `Closed`,
from a draft or ready, once the session is over: nothing changes a closed preparation but `reopen()`, which brings it
back to a draft, and `delete()`. `reopen()` also brings a ready preparation back to a draft.

## Carrying over

`carryOver($from, $into, $by)` carries into a preparation what another one of the same campaign left over:

- its secrets not revealed, with their clues as they are;
- its scenes still planned, with the characters and places they feature.

A part whose key the preparation holds already is left out, so that carrying again adds only what is new; when
nothing is new, the preparation is returned as it is. The game master is the game master of both; the preparation
carried from may be closed, the one carried into may not. The parts carried keep their order, after the preparation's
own.

## Reading and deleting

`preparation($id)` gives one preparation, or raises `PreparationNotFound`. `preparations($query, $limit)` gives the
preparations a `PreparationQuery` matches, at most 1 to 1000, by identifier:

```php
use Tilttek\SessionPrep\PreparationQuery;
use Tilttek\SessionPrep\PreparationStatus;

$preparer->preparations(new PreparationQuery(session: 'session-31'));
$preparer->preparations(new PreparationQuery(campaign: 'campaign-7', status: PreparationStatus::Ready), 20);
```

A criterion left null matches every preparation. An application shows the preparations of a game master to them
alone: the service checks who changes a preparation, and the application checks who reads one.

`delete($id, $by)` deletes a preparation in any status.

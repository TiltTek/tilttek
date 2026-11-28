# Corrections

A member proposes a correction of an entry on any field; the administration accepts or refuses it. Until then, the
correction is kept beside the entry and changes nothing.

```php
$corrections->propose('c-1', 'tide', 'm-104', $detailsAsTheMemberWouldHaveThem);
$corrections->waiting('tide');                    // the corrections waiting, in the order they were proposed
$corrections->accept('c-1', 'admin-1');
$corrections->refuse('c-2', 'admin-1', 'The box says 2026.');
$corrections->revert('tide', Field::Year, 'admin-1');
```

## Proposing

The member sends the whole `Details` as they would have them, read from the details shown. The correction keeps the
details the member read and the fields they changed; one that changes nothing raises `CatalogRefused`. A member has
at most 20 corrections waiting; the observer is told of each one proposed.

## Accepting

Accepting gives the fields of the correction the values the member proposed. When a field it changes was changed on
the entry since the member read it — by another correction, or by the source — to a third value, accepting would
overwrite that change unseen: `CorrectionConflict` names the fields, and the administration reads both, then accepts
with `force: true` or refuses. A field changed since to the value the member proposed is no conflict.

The values of a correction are kept beside the values of the source, which stay as the source sent them: the source
sending the entry again changes its own values, and the corrected fields keep theirs. The history notes the fields,
the administrator and the correction; the observer is told, for the member.

Accepting saves the correction, then the entry: an application that keeps both in a database runs the call in a
transaction.

## Refusing

Refusing keeps the correction with a note of at most 1000 characters for the member, and changes nothing of the
entry. A correction is decided once: deciding it again raises `CatalogRefused`.

## Returning a field to its source

`revert()` returns a corrected field to the value of the source, and notes it in the history; a field that is not
corrected raises `CatalogRefused`.

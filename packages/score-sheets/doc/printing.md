# Printing a blank sheet

`BlankSheet` gives the blank sheet of a game as neutral data, for the application to lay out in HTML, in a PDF, or on
a screen at the table:

```php
$blank = new BlankSheet($sheet, columns: 5)->toArray();
```

```php
[
    'game' => 'lantern-harbor',
    'columns' => 5,
    'lines' => [
        ['key' => 'boats', 'label' => 'Boats moored', 'entered' => true, 'counted' => false, 'min' => 0, 'max' => 12],
        ['key' => 'harbor', 'label' => 'Harbour', 'entered' => false, 'counted' => true, 'operation' => ['type' => 'sum', 'of' => ['boats', 'lanterns']]],
        // …
    ],
    'total' => ['label' => 'total', 'of' => ['harbor', 'set_points', 'fleet', 'lighthouse']],
    'tiebreakers' => [['category' => 'coins', 'prefer' => 'most'], ['category' => 'boats', 'prefer' => 'fewest']],
]
```

A sheet has 1 to 100 empty columns, one per participant. A computed line carries its operation, from which the
application prints the rule — "4 per set", "6 / 3 for the largest fleet" — in the reader's language. The labels are
text the application escapes when it renders them.

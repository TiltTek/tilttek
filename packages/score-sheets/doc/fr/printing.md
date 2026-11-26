<!-- phil-dev source-sha256=332ccd19ac8f6d14913c06834e4d8896999c03b772d193d573dbcc0c8c09129a -->
# Imprimer une feuille vierge

`BlankSheet` donne la feuille vierge d'un jeu en données neutres, que l'application met en page en HTML, en PDF, ou sur
un écran à la table :

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

Une feuille a de 1 à 100 colonnes vides, une par participant. Une ligne calculée porte son opération, à partir de
laquelle l'application imprime la règle — « 4 par ensemble », « 6 / 3 pour la plus grande flotte » — dans la langue du
lecteur. Les libellés sont du texte que l'application échappe quand elle les affiche.

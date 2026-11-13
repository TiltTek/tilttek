<!-- phil-dev source-sha256=9e1167b683af0decf927c046a90890fcc399cdfcb7703683fcdd5ef4329f1b87 -->
# Réglages

Les `TableSettings` d'une table disent ce qu'elle a choisi :

| Réglage | Valeur par défaut | Sens |
|---|---|---|
| `tools` | tous les outils | les outils que la table utilise ; les autres n'acceptent aucune réponse et ne lèvent aucun signal |
| `namedSignals` | `false` | si un signal dit qui l'a levé |
| `signalsSeenBy` | `Audience::Table` | qui voit la carte X et Script Change : la table, ou le meneur de jeu seul |
| `customThemes` | aucun | les thèmes que la table ajoute, par clé, avec leurs étiquettes |
| `sessionZero` | le gabarit `campaign` | la séance zéro de la table |

```php
$safety->configure($table, TableSettings::defaults()
    ->withTools([SafetyTool::LinesAndVeils, SafetyTool::XCard, SafetyTool::OpenDoor])
    ->withSignalsSeenBy(Audience::GameMaster)
    ->withCustomThemes(['table-clowns' => 'Clowns']));
```

`configure()` vérifie les thèmes ajoutés contre le catalogue. Une table qui n'a gardé aucun réglage utilise les valeurs
par défaut données au constructeur de `TableSafety`, ou `TableSettings::defaults()`.

`toArray()` et `fromArray()` stockent les réglages en JSON.

## Les outils

`SafetyTool` les énumère dans l'ordre d'une partie — lignes et voiles, liste de consentement, séance zéro, carte X,
Script Change, porte ouverte, points d'étape, étoiles et souhaits. `credit()` nomme les personnes ou la communauté dont
chacun vient, et `signals()` les signaux qu'il lève.

Nommer les outils d'une table dans son annonce est recommandé, jamais obligatoire : une application montre la liste et
laisse le meneur de jeu la laisser vide.

<!-- phil-dev source-sha256=5fc4fdcf1596ed21697f77a6ec0acb850b7db0c97016b58a78c4284a1bf922e7 -->
# Difficulté

La bibliothèque ne connaît aucun jeu : chaque système de jeu déclare la difficulté d'une rencontre, comme un
`Contract\DifficultyRules`. Les règles prennent les participants d'une rencontre et donnent une
`Difficulty\Assessment` :

| Valeur | Ce que c'est |
|---|---|
| `cost` | Ce que coûtent les adversaires, comme les règles le comptent. |
| `bands` | Les paliers de difficulté, du plus facile, chacun une clé et le seuil — le coût à partir duquel la rencontre est de ce palier — pour ces personnages. |
| `difficulty` | La clé du dernier palier dont le coût atteint le seuil, ou null quand il n'en atteint aucun. |
| `characters`, `adversaries` | Pour combien de personnages sont les seuils, et pour combien d'adversaires est le coût, chaque copie comptée. |

`threshold()` donne le seuil d'un palier, `reached()` si le coût l'atteint, et `remainingTo()` ce que le meneur de jeu
peut encore ajouter avant que la rencontre l'atteigne — 0 une fois atteint. `toArray()` la donne en données simples,
pour l'afficher.

Les règles refusent une cote qu'elles ne connaissent pas avec `InvalidEncounter`, et une rencontre qu'elles ne peuvent
pas évaluer — aucun personnage, un adversaire au-delà de ce qu'elles cotent — avec `EncounterRefused`. Elles acceptent
jusqu'à 100 participants.

La bibliothèque apporte deux sortes de règles qu'un système remplit de ses propres chiffres — d'ordinaire les nombres de
difficulté de son système de `tilttek/rpg-systems` ([Les nombres du système](#les-nombres-du-système)) ; un système dont
les règles n'entrent dans aucune est une classe de l'application qui implémente `Contract\DifficultyRules`.

## Par budget

`Difficulty\BudgetRules` : chaque personnage apporte un montant à chaque palier selon son niveau, et chaque copie d'un
adversaire coûte un montant selon son défi. Les seuils sont les sommes des montants des personnages ; le coût est la
somme des coûts des adversaires, multipliée par le `Difficulty\Multiplier` qui s'applique à leur nombre, en pourcentage,
arrondie à l'entier inférieur.

```php
new BudgetRules(
    'my-system',
    ['low', 'moderate', 'high'],                                          // the bands, from the easiest
    ['1' => ['low' => 50, 'moderate' => 75, 'high' => 100],               // by a character's level
     '2' => ['low' => 100, 'moderate' => 150, 'high' => 200]],
    ['1/4' => 10, '1/2' => 25, '1' => 50, '2' => 100],                    // by an adversary's challenge
    [new Multiplier(2, 150), new Multiplier(4, 200)],                     // ×1.5 from two adversaries, ×2 from four
);
```

Deux personnages de niveaux 1 et 2 contre trois loups de défi 1/2 ont des seuils de 150, 225 et 300 ; les loups coûtent
75, ×1,5 pour leur nombre : 112, sous chaque palier. Les alliés ne comptent d'aucun côté.

Chaque rangée de budgets donne tous les paliers, ses montants croissant à partir du palier le plus facile ; les
multiplicateurs sont dans l'ordre du nombre à partir duquel ils s'appliquent. Jusqu'à 12 paliers, 1 000 cotes dans
chaque table, 20 multiplicateurs, des montants de 0 à 1 000 000 000.

## Par niveau

`Difficulty\LevelRules` : chaque palier a un budget pour un groupe de taille standard, augmenté d'un ajustement pour
chaque personnage au-delà de cette taille et diminué pour chacun en deçà, jamais sous 0. Le niveau du groupe est la
moyenne des niveaux des personnages, arrondie à l'entier inférieur ; chaque copie d'un adversaire coûte un montant selon
le nombre de niveaux dont il dépasse le groupe — négatif en dessous.

```php
new LevelRules(
    'my-system',
    ['trivial' => 40, 'moderate' => 80, 'severe' => 120],   // the budgets for the standard party, from the easiest
    4,                                                       // the standard size of a party
    ['trivial' => 10, 'moderate' => 20, 'severe' => 30],    // by band, for each character over or under it
    [-2 => 20, -1 => 30, 0 => 40, 1 => 60, 2 => 80],         // by level difference, every one from the lowest to the highest
);
```

Un adversaire plus loin sous le groupe que l'écart le plus bas ne coûte rien ; un adversaire plus loin au-dessus que
l'écart le plus haut est refusé. Les budgets croissent à partir du palier le plus facile. Les cotes sont des niveaux
entiers, de -99 à 999. Jusqu'à 12 paliers, un groupe standard de 1 à 20, des écarts de -50 à 50, des montants de 0 à
1 000 000 000.

## Les nombres du système

Un système de `tilttek/rpg-systems` déclare la difficulté d'une rencontre comme ses nombres de difficulté `encounter` :
leur `method`, leurs paliers du plus facile, et leurs tables. `Difficulty\Declaration::fromSystem()` les lit pour en
faire l'une ou l'autre sorte de règles :

| Méthode | Règles | Tables |
|---|---|---|
| `budget` | `BudgetRules` | `budgets` : selon le niveau d'un personnage, un montant pour chaque palier ; `costs` : selon le défi d'un adversaire, son coût ; `multipliers` (facultatif) : selon le nombre d'adversaires à partir duquel un multiplicateur s'applique, son pourcentage |
| `level` | `LevelRules` | `party-size` ; `budgets` et `adjustments` : par palier ; `costs` : selon l'écart de niveau |

```php
use Tilttek\Encounter\Difficulty\Declaration;

$rules = Declaration::fromSystem($srd);                 // the numbers `encounter` of the SRD 5.2: by budget
$rules = Declaration::fromSystem($pathfinder);          // those of Pathfinder Second Edition: by level
$rules = Declaration::fromSystem($system, 'threat');    // numbers of another key
```

Les profils d'importation de `tilttek/rpg-systems` donnent ces nombres pour la SRD 5.2 — le budget de PX d'un
personnage selon son niveau pour une difficulté faible, modérée ou élevée, et les PX d'une créature selon son indice de
dangerosité — et pour Pathfinder deuxième édition — les budgets d'un groupe de quatre, de trivial à extrême, leurs
ajustements par personnage et les PX d'une créature selon son niveau comparé à celui du groupe. La SRD 5.1 ne donne
aucune règle de difficulté de rencontre.

Un système sans nombres de la clé est refusé avec `UnknownSystem` ; des nombres d'une autre méthode, une table que la
méthode ne lit pas ou une table dont elle a besoin qui manque, avec `InvalidEncounter`, puis chaque vérification de
`read()`.

## Des règles en données

Une application qui apporte ses propres règles — pour un système sans nombres de difficulté, ou à la place de ses
nombres — les donne comme des données simples, décodées de JSON ou de YAML. `Difficulty\Declaration::read()` les lit
pour en faire l'une ou l'autre sorte de règles, selon sa `method` :

```json
{
    "method": "budget",
    "system": "my-system",
    "bands": ["low", "high"],
    "budgets": {"1": {"low": 50, "high": 100}, "2": {"low": 100, "high": 200}},
    "costs": {"1/2": 25, "1": 50},
    "multipliers": [{"from": 2, "percent": 150}]
}
```

```json
{
    "method": "level",
    "system": "my-system",
    "partySize": 4,
    "budgets": {"low": 60, "high": 120},
    "adjustments": {"low": 15, "high": 30},
    "costs": {"-1": 30, "0": 40, "1": 60}
}
```

Chaque clé est obligatoire, sauf `multipliers`. Une clé qu'il ne connaît pas, une valeur d'un autre type, une table trop
longue ou une valeur hors de ses bornes est refusée avec `InvalidEncounter`, avant que rien soit construit. Le lecteur
est soumis au fuzzing.

## Les livres de règles

`Rulebooks` contient les règles de chaque système de jeu que l'application offre, jusqu'à 1 000, une par système.
`for()` les trouve par la clé du système ou par le `GameSystem` de `tilttek/rpg-systems`, et refuse un système sans
règles avec `UnknownSystem` ; `has()` le demande sans refuser.

`Rulebooks::fromSystems()` lit les règles de chaque système qui déclare des nombres de difficulté `encounter` — de
`Systems` ou de n'importe quelle liste de `GameSystem` —, et prend les règles que donne l'application : une règle donnée
pour un système prend la place de celle que déclarent ses nombres, et donne des règles à un système qui n'en a pas.

```php
$rulebooks = Rulebooks::fromSystems($systems);
$rulebooks = Rulebooks::fromSystems($systems, Declaration::RECORD, Declaration::read($houseRules), new MyRules());
```

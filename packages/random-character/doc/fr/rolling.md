<!-- phil-dev source-sha256=5704ef7d30be65caa20109a032d08f6a82a9ad3322bfbc36ead8a5ff5691d1e4 -->
# Tirage

`CharacterRoller` est le service. Il reçoit les `Systems` de `tilttek/rpg-systems`, le `Generator` de
`tilttek/generator` où les champs tirent — aucun pour des règles qui ne tirent d'aucun —, un `DiceRoller` de `tilttek/dice`
avec les limites de l'application, le `Random\Randomizer` qui tire les graines, et un observateur facultatif.

```php
use Tilttek\RandomCharacter\CharacterRoller;

$roller = new CharacterRoller($systems, $generator);

$roller->check($rules);                     // [] when the rules can be rolled
$character = $roller->roll($rules);         // a seed is drawn
$again = $roller->roll($rules, $character->seed);
```

## La vérification

`check()` liste, en phrases, ce qui empêche de tirer des règles, et une liste vide quand rien ne l'empêche :

- le système n'est pas l'un des `Systems` ;
- un attribut des statistiques n'est pas l'un de ceux du système ;
- une valeur d'un tableau fixe tombe hors des bornes d'un attribut ;
- un champ tire d'un générateur que le `Generator` n'a pas, ou le *roller* n'a aucun `Generator`.

Un générateur nommé par des marqueurs dépend des valeurs tirées et est vérifié quand il est tiré. `roll()` vérifie
d'abord les règles et lève une `InvalidRules` qui liste chaque problème.

## Les graines

Une graine est un texte de 1 à 256 caractères en UTF-8 : un numéro de table, un choix de joueur, l'identifiant d'une
session. Un personnage tiré sans graine en reçoit une de 32 chiffres hexadécimaux, tirée par le `Randomizer` du *roller*,
un moteur sécurisé à moins que l'application n'en passe un autre. Chaque personnage porte sa graine dans
`RandomCharacter::$seed` ; la même graine avec les mêmes règles, le même système et les mêmes tables donne le même
personnage.

Chaque partie du personnage tire d'une graine qui lui est propre, le SHA-256 de la graine du personnage et du nom de la
partie : les statistiques à partir de l'une, chaque champ à partir d'une autre. Ajouter un champ aux règles, ou en changer
un, laisse les statistiques et les autres champs tels quels ; un champ dont le générateur est nommé par un marqueur change
quand le champ qu'il lit change. Changer une table change les champs qui en sont tirés.

## Le tirage

Les statistiques sont tirées d'abord. `Each` lance les attributs dans l'ordre des règles ; `RolledArray` lance une valeur
par attribut et les mélange ; `Array` mélange les valeurs fixes. Une valeur hors des bornes de son attribut lève une
`RollFailed`, tout comme un lancer qui ne donne aucun nombre ou qui dépasse les limites du `DiceRoller`.

Les champs suivent, dans l'ordre. Un champ lancé lance ses dés `count` fois. Un champ tiré remplace ses marqueurs par les
valeurs qui le précèdent, puis demande au générateur `count` résultats, différents les uns des autres quand il est unique,
avec une graine qui lui est propre. Un générateur qui échoue, un champ unique à qui l'on donne trop peu de résultats
différents, ou un nom qui n'est pas celui d'un générateur du catalogue lève une `RollFailed` qui garde la cause comme
exception précédente.

## Le personnage

`RandomCharacter` contient la clé des règles et du système, la graine, les valeurs des attributs par clé dans l'ordre des
règles, les valeurs des champs par clé dans l'ordre des règles, les attributions des tables où les champs ont tiré —
chacune une seule fois, dans l'ordre où le tirage les a d'abord utilisées — et les valeurs changées à la main.

```php
$character->stat('grit');               // 3
$character->field('traits');            // ['patient', 'curious']
$character->attributions;               // list of Tilttek\Generator\Definition\Attribution
$character->toArray();                  // plain arrays, for JSON or a database column
```

## La modification

Un personnage tiré est un point de départ. `withStat()` et `withField()` donnent un nouveau personnage dont une valeur est
changée, la graine conservée, et le nom de la valeur ajouté une seule fois à `$edited` : `stats.grit`, `fields.name`.
`isEdited()` dit si la graine seule redonne encore ce personnage. Un attribut accepte tout entier de -1 000 000 à
1 000 000 — les bornes du système sont à la fiche de les imposer lors d'une modification manuelle — ; un champ accepte un
entier, un texte d'au plus 4 096 caractères en UTF-8, ou une liste d'au plus 16 d'entre eux. Un attribut ou un champ
inconnu, ou toute autre valeur, lève une `InvalidEdit`.

## L'observateur

Un `Contract\CharacterObserver` passé au *roller* est informé de chaque personnage tiré, une fois complet, par un
`Event\CharacterRolled` : la clé des règles, la clé du système, et si l'appelant a donné la graine. La graine et les
valeurs restent à l'écart — un appelant a pu écrire la graine, et le personnage appartient là où l'application garde ses
fiches. Des règles dont le tirage échoue ne communiquent rien.

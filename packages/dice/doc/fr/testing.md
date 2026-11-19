<!-- phil-dev source-sha256=e7f96b264f9b23f0fe4d4f9361ae04ea848c988ccccaf1895e8c8ceea3a20f86 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite couvre chaque construction de la notation et ses erreurs, les validateurs, la forme canonique et son aller-
retour, les limites, les lancers dé par dé avec chaque modificateur, l'arithmétique entière dans chaque mode d'arrondi,
les dés personnalisés, les lancers vérifiables et rejouables, et les dialectes. Dans le monodépôt, Infection maintient
la bibliothèque à 100 % des mutants couverts.

`ParserPropertyTest` vérifie des propriétés sur des expressions générées à partir de germes fixes : chacune s'analyse
dans les limites ou est refusée avec `InvalidExpression` ou `LimitExceeded`, ses lancers restent dans les dés, les
explosions, les relances et les faces des limites ou sont refusés avec une `DiceException`, toute entrée faite des
caractères de la notation est analysée ou refusée par la bibliothèque, et la forme canonique s'analyse de nouveau en
elle-même et lance les mêmes totaux avec le même germe. Un échec nomme l'expression, et son germe la rejoue.

Les tests scénarisent les dés avec un moteur qui renvoie les nombres qu'on lui donne : `Randomizer::getInt($min, $max)`
renvoie alors `$min` plus chaque nombre, ce qui permet à un test de dire quelle face montre chaque dé.

## Bancs d'essai

`DiceRollerBench` analyse et lance un lancer simple, des dés gardés et explosifs, un lot de plusieurs expressions, une
expression de modificateurs — relances répétées, explosions composées, planchers, échecs, occurrences, un nombre lancé
d'abord, un tri —, et mille dés. Depuis la racine du monodépôt :

```sh
composer bench -- packages/tilttek-dice/tests/Benchmark
```

## Fuzzing

`tests/Fuzz/dice-parser.php` remet à l'analyseur des entrées que personne n'a écrites : chacune s'analyse ou est
refusée par une `DiceException`, la forme canonique de ce qui s'analyse s'analyse de nouveau en elle-même, et le lot se
lance avec un générateur aléatoire à germe ou est refusé. Ses germes sont dans `tests/Fuzz/corpus/dice-parser`.
`tests/Fuzz/rolisteam-reader.php` fait de même pour le lecteur de la notation de DiceParser : chaque commande se lit ou
est refusée par une `DiceException` située dans la commande, ce que le dialecte `diceparser` écrit en entier se relit
en le même texte, et le lot se lance ou est refusé ; ses germes sont dans `tests/Fuzz/corpus/rolisteam-reader`. Depuis
la racine du monodépôt :

```sh
composer fuzz -- dice-parser
composer fuzz -- rolisteam-reader
```

## Tester du code qui lance des dés

Passez un `Randomizer` à germe — `new Randomizer(new Mt19937(42))` — et comparez avec un second lancer du même germe,
ou scénarisez le moteur comme le font les tests de la bibliothèque :

```php
use Random\Engine;

final class ScriptedEngine implements Engine
{
    public function __construct(private array $numbers) {}

    public function generate(): string
    {
        return pack('V', array_shift($this->numbers));
    }
}
```

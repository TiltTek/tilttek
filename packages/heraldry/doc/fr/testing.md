<!-- phil-dev source-sha256=90bad51baa534bd8c1ad54461c29e370f6820ebf0742754013929d268b5b4137 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau ni base de données : le modèle et ses refus, pièce par pièce
et nombre par nombre ; la règle de couleur sur chaque combinaison de champ, de pièce et de meubles ; le générateur sur
3 000 germes — chaque écu respecte la règle, chaque partie du modèle est atteinte, les parts correspondent à la
documentation —, des germes connus qui donnent des armes connues, et le même germe qui donne le même écu ; plus de
cinquante blasons en anglais et en français, chaque cas grammatical ; et le dessin — structure, identifiants,
accessibilité, emplacements, palette, fourrures — avec 400 écus générés passés intacts dans `phil/svg-sanitizer` ; et
les armes, les blasons et les dessins de mille germes fixes, tenus par leur SHA-256, de sorte qu'un changement dans ce
qu'un germe donne se voit tout de suite. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

`tests/Fuzz/drawing.php` est la cible de fuzzing du générateur et du dessin : n'importe quelle entrée donne des armes
qui respectent la règle et une image que l'assainisseur garde entière. `tests/Fuzz/seed.php` est celle des germes :
n'importe quel texte est refusé ou se réécrit en ses chiffres. `composer fuzz` exécute les deux.

`tests/Benchmark/HeraldryBench.php` mesure des armes tirées d'un germe, un blason français et une image SVG.

## Tester une application

Fixez le germe, et les armes sont fixées :

```php
$coat = new CoatGenerator()->generate(Seed::fromHex(str_repeat('2a', 32)));
self::assertSame('Purpure, on a bend sinister Or a lozenge azure', new Blazonry()->write($coat));
```

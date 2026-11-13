<!-- phil-dev source-sha256=dfc043e69217a23ae01022454cdff5ca1413ce25473549e14fffd37cb32926c0 -->
# Générer des armes

`CoatGenerator::generate()` tire un écu d'un `Generator\Seed` :

```php
use Tilttek\Heraldry\Generator\CoatGenerator;
use Tilttek\Heraldry\Generator\Seed;

$seed = Seed::generate();              // 32 bytes from the system's secure source
$hex = $seed->hex();                   // 64 hexadecimal digits, to keep with the arms

$coat = new CoatGenerator()->generate(Seed::fromHex($hex));
```

## Reproductible

Le germe alimente un générateur `Xoshiro256**` avec son SHA-256, et chaque tirage en vient dans un ordre fixe : le même
germe donne le même écu sur n'importe quel serveur et n'importe quelle version prise en charge de PHP. Le germe vient du
serveur : un client qui envoie un germe ne fait que demander des armes déjà tirées. `Seed::fromHex()` prend 64 chiffres
hexadécimaux et lance `Exception\InvalidSeed` pour tout le reste.

## Ce qu'il tire

| Partie | À quelle fréquence |
|---|---|
| Un champ uni | 3 écus sur 5 ; les métaux et les couleurs deux fois plus probables que les fourrures |
| Un champ divisé | 2 écus sur 5, entre un métal ou une fourrure et une couleur, dans un ordre ou dans l'autre |
| Une pièce | un peu plus de la moitié des écus |
| Des meubles sans pièce | 85 % de ces écus, de un à trois ; trois groupes de deux ou trois sur dix prennent une disposition |
| Des meubles avec une pièce | la moitié de ces écus, sur elle ou à côté selon ce que la pièce permet |
| Une brisure | un écu sur dix |

Chaque écu respecte [la règle de couleur](tincture-rule.md) ; sur un champ divisé, l'émail de ce qui s'y trouve n'est
aucun de ceux du champ, et ce qui est côte à côte ne partage jamais un émail. `new CoatGenerator(furs: false)` laisse
l'hermine et le vair hors des champs.

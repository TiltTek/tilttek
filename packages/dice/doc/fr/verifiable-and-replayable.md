<!-- phil-dev source-sha256=27f733c96c14f94960aa31b86cbb5eb3dbf02b38574a33a9fe3319c084ff826a -->
# Lancers vérifiables et rejouables

## Lancers vérifiables

Un lancer vérifiable permet à un joueur de vérifier que le résultat n'a pas été choisi :

1. Le lanceur tire une `Seed` d'une source sûre et publie son `Commitment`, le SHA-256 du germe.
2. Le joueur envoie l'expression et un germe de son choix, le germe du client.
3. Le lanceur lance avec `FairRoller::roll($pool, $seed, $clientSeed, $nonce)` — le *nonce* numérote les lancers faits
   avec le même germe — et obtient un `VerifiableRoll`.
4. Une fois les lancers de ce germe terminés, le lanceur révèle le germe. `VerifiableRoll::toArray()` est ce qu'il
   publie, l'arrondi des divisions du lancer compris.
5. Quiconque vérifie avec `FairRoller::verify()` que le germe correspond à l'engagement et que relancer, avec le même
   arrondi, donne le même résultat.

Le lanceur n'a pas pu choisir le résultat : son germe était fixé avant que celui du joueur soit connu. Tout le langage
est couvert : les instructions, les dés des instructions, les groupes, les éléments tirés des listes de textes, les
branches, les répétitions et les phrases sont lancés à partir du même germe, et leurs résultats font partie de ce qui
est publié et comparé. Les dés viennent de `Xoshiro256**`, dont le germe est le HMAC-SHA256 du germe du client et du
*nonce* sous le germe.

```php
use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\Dice\Fairness\FairRoller;
use Tilttek\Dice\Fairness\Seed;

$seed = Seed::generate(new Randomizer(new Secure()));
$commitment = $seed->commitment()->hex;              // published now
$roll = new FairRoller()->roll($pool, $seed, 'chosen by the player', 1);
$published = $roll->toArray();                        // published with the seed, later
```

## Lancers rejouables

Un lancer rejouable garde un identifiant — 32 chiffres hexadécimaux — et un germe, tous deux tirés d'une source sûre,
et lance à partir du germe. `Replayer::replay($id, $pool, $seed, $rounding)` le relance, dé pour dé ; l'arrondi, gardé
dans `PoolResult::$rounding`, est repassé quand il n'est pas celui par défaut. Un journal garde l'identifiant,
l'expression, le germe et le résultat ; `tilttek/dice-bundle` les garde dans Doctrine.

Les deux reposent sur la stabilité des moteurs à germe de PHP et de `Randomizer::getInt()`, qui donnent les mêmes
nombres pour le même germe d'une version de PHP à l'autre.

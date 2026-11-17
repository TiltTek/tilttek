<!-- phil-dev source-sha256=a41802f66022b58f9a87787ee42f863a2472c489218cbe5671378e2af753952a -->
# Lancers vérifiables et rejouables

## Lancers vérifiables

Un lancer vérifiable permet à un joueur de vérifier que le résultat n'a pas été choisi. La bibliothèque donne les
primitives de chaque étape du protocole ; en garder l'ordre revient à l'application hôte.

1. **Germe et *nonce*.** Le lanceur tire une `Seed` d'une source sûre et réserve le *nonce* du lancer : le numéro du
   lancer sous ce germe, que l'application distribue une seule fois, de façon atomique — une clé unique sur le germe et
   le *nonce*, ou un compteur avancé dans la même transaction —, si bien que deux requêtes ne partagent jamais un
   *nonce*.
2. **Engagement, avant le germe du client.** Le lanceur publie `$seed->commitment($nonce)`, un `Commitment` au germe
   et à ce *nonce*. Il vaut pour ce lancer seulement : l'engagement d'un autre *nonce* du même germe est une autre
   valeur.
3. **Germe du client.** Ayant vu l'engagement, le joueur envoie l'expression et un germe de son choix, le germe du
   client : vide, ou d'au plus 256 octets, `Seed::MAX_CLIENT_SEED`. Un germe plus long est refusé avec
   `InvalidDefinition`, si bien qu'un germe envoyé dans une requête publique coûte un HMAC borné. Un germe du client
   que le lanceur connaît ou choisit ne donne aucune équité.
4. **Lancer.** Le lanceur lance avec `FairRoller::roll($pool, $seed, $clientSeed, $nonce)` et obtient un
   `VerifiableRoll`, dont l'engagement est celui publié à l'étape 2.
5. **Révéler, après.** Une fois les lancers de ce germe terminés, le lanceur révèle le germe.
   `VerifiableRoll::toArray()` est ce qu'il publie, l'arrondi des divisions du lancer compris.
6. **Vérifier.** Quiconque vérifie avec `FairRoller::verify()` que le germe et le *nonce* correspondent à l'engagement
   et que relancer, avec le même arrondi, donne le même résultat.

Le lanceur n'a pas pu choisir le résultat : son germe était fixé avant que celui du joueur soit connu, et un engagement
montré pour un lancer ne répond pas d'un lancer fait sous un autre *nonce*. Tout le langage est couvert : les
instructions, les dés des instructions, les groupes, les éléments tirés des listes de textes, les branches, les
répétitions et les phrases sont lancés à partir du même germe, et leurs résultats font partie de ce qui est publié et
comparé. Les dés viennent de `Xoshiro256**`, dont le germe est le HMAC-SHA256 du germe du client et du *nonce* sous le
germe.

```php
use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\Dice\Fairness\FairRoller;
use Tilttek\Dice\Fairness\Seed;

$seed = Seed::generate(new Randomizer(new Secure()));
$nonce = $reservedNonce;                              // reserved once, atomically, by the application
$commitment = $seed->commitment($nonce)->hex;         // published now, before the client seed
$roll = new FairRoller()->roll($pool, $seed, 'chosen by the player', $nonce);
$published = $roll->toArray();                        // published with the seed, later
```

### Le format de l'engagement

Un engagement est fait de 64 chiffres hexadécimaux en minuscules : le SHA-256 de

| Partie | Octets |
| --- | --- |
| `Commitment::FORMAT`, `tilttek/dice commitment v2`, en ASCII | 26 |
| Un octet nul | 1 |
| Le germe | 32 |
| Le *nonce*, un entier signé de 64 bits en gros-boutiste, en complément à deux | 8 |

Chaque partie a une longueur fixe, si bien que deux germes et *nonces* ne donnent jamais la même entrée, et le domaine
nomme le format et sa version : tout outil doté de SHA-256 recalcule un engagement. Avec le germe `000102…1e1f` — les
octets 0 à 31 —, l'engagement du *nonce* 0 est `dc3f2c443dcbc5f1aef5f7c2f1b854110296650bc69659002cf66c9627220bc4`, et
celui du *nonce* 7 `15d153b1c8c2bbb2c7cdad9c1acc90c56ca83a0f9acd6db1a1895f4440a9fb47`.

Un engagement calculé autrement — le SHA-256 du germe seul, par exemple — ne correspond à aucun germe ni à aucun
*nonce* : `verify()` retourne faux pour lui, jamais vrai.

## Lancers rejouables

Un lancer rejouable garde un identifiant — 32 chiffres hexadécimaux — et un germe, tous deux tirés d'une source sûre,
et lance à partir du germe. `Replayer::replay($id, $pool, $seed, $rounding)` le relance, dé pour dé ; l'arrondi, gardé
dans `PoolResult::$rounding`, est repassé quand il n'est pas celui par défaut. Un journal garde l'identifiant,
l'expression, le germe et le résultat ; `tilttek/dice-bundle` les garde dans Doctrine.

Les deux reposent sur la stabilité des moteurs à germe de PHP et de `Randomizer::getInt()`, qui donnent les mêmes
nombres pour le même germe d'une version de PHP à l'autre.

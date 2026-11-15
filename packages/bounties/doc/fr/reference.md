<!-- phil-dev source-sha256=48265d00047f90fcf12f6291f3ca4333035f80feeda332f76b1d24dbf7eaf72d -->
# Référence

| Type | Rôle |
|---|---|
| `Bounties` | Offre les primes, les fait évoluer, règle leurs points et répartit les événements |
| `Bounty` | Une prime et sa machine à états ; ses attributions, ses paiements et son enregistrement écrit puis relu, vérifié |
| `BountyDraft` | Ce que demande un membre : identifiant, sujet, raison, montant, fin |
| `BountyRules` | Les bornes du site : plus courte et plus longue prime, délai de grâce, plus petit et plus grand montant, primes ouvertes par commanditaire |
| `BountyState` | `Open`, `Awarded`, `Expired` ou `Cancelled` |
| `BountyReason` | `Attention`, `Detail`, `Sources`, `Reward`, `Creativity` ou `Other` |
| `Claim` | La réclamation d'un membre : identifiant, réclamant, preuve, heure |
| `Award` | Les points que le commanditaire attribue à une réclamation : identifiant, réclamation, montant, heure |
| `Prize` | Un montant d'une unité de points, jamais de l'argent |
| `BountyHold` | Les points détenus pour une prime : clé, payeur, montant, unité, depuis quand et jusqu'à quand |
| `Payout` | Des points d'une détention payés à un membre |
| `Pseudonym` | Ce qui tient lieu d'un membre effacé : une référence du genre `erased`, tirée pour chaque prime |
| `Contract\BountyStore` | Où les primes sont conservées, une seule prime ouverte par sujet |
| `Contract\BountyEscrow` | Où les points sont détenus : réserver, confirmer, annuler |
| `Escrow\PointsBountyEscrow` | Les points détenus dans le registre de `tilttek/points` |
| `Escrow\InMemoryBountyEscrow` | Les points et les détentions en mémoire |
| `Store\InMemoryBountyStore` | Les primes en mémoire, conservées sous forme d'enregistrements |
| `Event\BountyOffered`, `BountyClaimed`, `AwardGiven`, `BountyAwarded`, `BountyClosed` | Voir [Points d'extension](extension-points.md#les-événements) |

`Instant`, `Check`, `Reader` et `BountyRecord` sont internes : l'heure en UTC, les vérifications de ce que la
bibliothèque reçoit, et la lecture et la vérification d'un enregistrement.

## Exceptions

| Exception | Lancée quand |
|---|---|
| `Exception\BountiesException` | L'interface de toutes les exceptions de la bibliothèque |
| `Exception\InvalidBounty` | Une valeur que la bibliothèque refuse : un identifiant, un montant, une unité, un brouillon hors des règles, une heure, un enregistrement, une référence du genre `erased` donnée par un appelant |
| `Exception\BountyRefused` | Un mouvement que les règles n'autorisent pas : une prime ou une réclamation inconnue, un membre qui n'y a pas droit, un état ou une heure hors de sa limite, une limite atteinte, un montant supérieur à ce qui reste, un sujet pris, un identifiant réutilisé |
| `Exception\BountyConflict` | Un autre auteur a conservé la prime avant, trois fois de suite ; une prime du même identifiant, ou sur le même sujet, est déjà conservée |
| `Exception\EscrowConflict` | Un mouvement de séquestre qui contredit sa détention : une autre détention sous sa clé, des paiements supérieurs à son montant, une détention réglée deux fois |
| `Tilttek\Points\Exception\InsufficientPoints` | Les points du commanditaire ne couvrent pas la prime |

Rien n'est conservé et aucun point ne bouge quand une exception est lancée pendant un mouvement, sauf un échec du
séquestre après que le mouvement est conservé, que `settle()` répare. Chaque message dit ce qui était attendu, comme
`The bounty "01JD4" has 40 points left to award; 41 is too many.`

<!-- phil-dev source-sha256=5392970fbd666070f1ba98cf5dcfb25d25ef6656baa48d88ab9f8680f8ddc369 -->
# Référence

| Type | Rôle |
|---|---|
| `FriendChallenges` | Crée les défis, les fait évoluer, règle leurs lots et répartit les événements |
| `Challenge` | Un défi et sa machine à états ; son enregistrement écrit puis relu, vérifié |
| `ChallengeDraft` | Ce que demande un membre : identifiant, titre, lot, fin, invités, visibilité, validation, conditions, votes nécessaires |
| `ChallengeRules` | Les bornes du site : plus court et plus long défi, examen, plus gros lot |
| `ChallengeState` | `Open`, `Frozen`, `Won`, `Cancelled`, `Expired` ou `Removed` |
| `Visibility` | `Friends` ou `Open` |
| `Validation` | `Creator`, `Vote` ou `Moderator` |
| `Proof` | La preuve d'un participant : identifiant, justificatif, heure, état, approbations et rejets |
| `ProofState` | `Pending`, `Accepted`, `Rejected` ou `Removed` |
| `Prize` | Un montant d'une unité de points, jamais de l'argent |
| `PrizeHold` | Les points détenus pour un lot : clé, payeur, montant, unité, depuis quand et jusqu'à quand |
| `Payout` | Des points d'une détention payés à un membre |
| `Pseudonym` | Ce qui tient lieu d'un membre effacé : une référence du genre `erased`, tirée pour chaque défi |
| `Report` | Le signalement d'un membre aux modérateurs : défi, auteur du signalement, raison, heure, preuve, note |
| `ReportReason` | `Spam`, `Harassment`, `Cheating`, `Unsafe`, `Inappropriate` ou `Other` |
| `Contract\ChallengeStore` | Où les défis sont conservés |
| `Contract\PrizeEscrow` | Où les lots sont détenus : réserver, confirmer, annuler |
| `Contract\Friendship` | Qui est l'ami de qui |
| `Contract\Moderation` | Où vont les signalements, et qui modère |
| `Escrow\PointsPrizeEscrow` | Les lots détenus dans le registre de `tilttek/points` |
| `Escrow\InMemoryPrizeEscrow` | Les lots et les points des membres en mémoire |
| `Store\InMemoryChallengeStore` | Les défis en mémoire, conservés sous forme d'enregistrements |
| `Store\InMemoryFriendship` | Les amitiés en mémoire |
| `Store\InMemoryModeration` | Les modérateurs et les signalements en mémoire |
| `Event\ChallengeCreated`, `MemberInvited`, `ParticipantJoined`, `ProofSubmitted`, `ProofDecided`, `ChallengeWon`, `ChallengeEnded`, `ChallengeFrozen`, `ChallengeUnfrozen` | Voir [Points d'extension](extension-points.md#les-événements) |

`Instant`, `Check`, `Reader` et `ChallengeRecord` sont internes : l'heure en UTC, les vérifications de ce que la
bibliothèque reçoit, et la lecture et la vérification d'un enregistrement.

## Exceptions

| Exception | Lancée quand |
|---|---|
| `Exception\FriendChallengesException` | L'interface de toutes les exceptions de la bibliothèque |
| `Exception\InvalidChallenge` | Une valeur que la bibliothèque refuse : un identifiant, un titre, des conditions, un lot, un brouillon hors des règles, une heure, un enregistrement, une référence du genre `erased` donnée par un appelant |
| `Exception\ChallengeRefused` | Un mouvement que les règles n'autorisent pas : un défi ou une preuve inconnu, un membre qui n'y a pas droit, un état ou une heure hors de sa limite, une limite atteinte, un identifiant réutilisé |
| `Exception\ChallengeConflict` | Un autre auteur a conservé le défi avant, trois fois de suite ; un défi du même identifiant est déjà conservé |
| `Exception\EscrowConflict` | Un mouvement de séquestre qui contredit sa détention : une autre détention sous sa clé, des paiements supérieurs à son montant, une détention réglée deux fois |
| `Tilttek\Points\Exception\InsufficientPoints` | Les points du créateur ne couvrent pas le lot |

Rien n'est conservé et aucun point ne bouge quand une exception est lancée pendant un mouvement, sauf un échec du
séquestre après que le mouvement est conservé, que `settle()` répare. Chaque message dit ce qui était attendu, comme
`member:eve is not invited to the challenge "01JD3", which is between friends.`

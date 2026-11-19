<!-- phil-dev source-sha256=67f5cfe71ff7ce4a26141e60eda2a84fc9bd82c60679be5c5ec8fc3b983eee5c -->
# drand

drand est la balise d'aléa distribuée de la League of Entropy : un réseau d'opérateurs indépendants qui publie, à
heure fixe, une ronde dont aucun d'eux ne peut calculer seul la signature, et dont l'aléa est le SHA-256 de cette
signature. `Drand\DrandBeacon` la lit par HTTP.

## Chaînes

| Chaîne | `DrandChains::` | Période | Genèse | Schéma |
|---|---|---|---|---|
| quicknet | `quicknet()` | 3 s | 2023-08-23 | `bls-unchained-g1-rfc9380` : signatures sur G1, chaque ronde signée seule |
| Le premier réseau | `mainnet()` | 30 s | 2020-07-22 | `pedersen-bls-chained` : signatures sur G2, chaque ronde signant celle d'avant |

La ronde `r` d'une chaîne est publiée à `genesis + (r − 1) × period`. Toute autre chaîne est un `Beacon\BeaconChain`
construit avec son empreinte, sa genèse, sa période et son schéma. La clé publique n'est pas écrite dans la
bibliothèque : `chain()` la lit sur les relais, vérifie que chaque relais décrit la chaîne attendue — la même
empreinte, la même genèse, la même période et le même schéma — et qu'ils donnent la même clé.

## Les relais et le quorum

```php
$beacon = new DrandBeacon(
    $httpClient,
    DrandChains::quicknet(),
    DrandChains::RELAYS,     // api.drand.sh, api2.drand.sh, api3.drand.sh, drand.cloudflare.com
    signatures: null,        // a BeaconSignatureVerifier, or null
    quorum: null,            // 2 without a verifier, 1 with one
    timeout: 5.0,            // per relay by the transport, and for the whole read on the clock
    events: $dispatcher,     // told of each read through Event\BeaconRelaysRead; null to dispatch nothing
    clock: $clock,           // a PSR-20 clock; the system's by default
);
```

Les relais sont de 1 à 8 adresses HTTPS sans requête ni fragment, lues par `phil/url`. Une lecture interroge tous les
relais à la fois — `GET {relay}/{chain hash}/info` ou `GET {relay}/{chain hash}/public/{round}` —, ne suit aucune
redirection et ne garde une réponse que si elle arrive entière, avec le statut `200`, dans le délai et en 4 096 octets
au plus. Le délai borne chaque relais par les options `timeout` et `max_duration` du transport, et la lecture entière
par l'horloge : une fois le délai écoulé, les relais qui répondent encore sont annulés. Une ronde est retenue quand :

- son numéro est celui de la ronde demandée, sa signature a la longueur du schéma — et, pour un schéma chaîné, celle de
  la ronde précédente aussi —, et son aléa est le SHA-256 de sa signature ;
- tous les relais qui ont répondu donnent la même signature ;
- au moins le quorum de relais a répondu.

Moins de réponses que le quorum lance `BeaconUnavailable` : la ronde n'est pas encore publiée, ou les relais sont
injoignables, et le tirage est tenté de nouveau. Une réponse qui ne se vérifie pas, ou deux relais en désaccord,
lancent `BeaconRefused` : rien n'est tiré d'une balise qui se contredit. S'accorder sur la signature, c'est s'accorder sur l'aléa, son SHA-256.

## Ce que chaque relais a donné

Chaque lecture — d'une ronde ou des renseignements de la chaîne — donne un `Beacon\RelayReport` : le résultat de chaque
relais, par son adresse dans l'ordre configuré, le quorum et si la lecture a été retenue. Il est envoyé par
`Event\BeaconRelaysRead`, avec l'empreinte de la chaîne, après chaque lecture, retenue ou non, et c'est le `$report` de
la `BeaconUnavailable` ou de la `BeaconRefused` qui refuse la lecture.

| `Beacon\RelayOutcome` | Le relais |
|---|---|
| `Agreed` | a donné une réponse qui se vérifie, avec la signature que la plupart des relais ont donnée |
| `Disagreed` | a donné une réponse qui se vérifie, avec une autre signature ; en cas d'égalité, on compare à celle du premier relais |
| `Invalid` | a donné une réponse entière qui ne se vérifie pas |
| `Status` | a répondu avec un statut autre que `200` |
| `TooLarge` | a donné plus de 4 096 octets |
| `TimedOut` | est resté silencieux au-delà du délai, ou répondait encore quand la lecture a manqué de temps |
| `Unreachable` | n'a pas pu être joint, ou sa réponse s'est brisée |
| `Incomplete` | a terminé sa réponse avant qu'elle soit entière |

Un relais qui échoue sans contredire les autres est signalé, mais ne pèse pas contre la lecture : le quorum est là pour
l'absorber. Un écouteur qui compte les fautes de chaque relais en voit un se dégrader avant que le quorum soit perdu :

```php
$dispatcher->addListener(BeaconRelaysRead::class, function (BeaconRelaysRead $read) use ($metrics): void {
    foreach ($read->report->faults() as $relay => $outcome) {
        $metrics->increment('drand.relay.fault', ['relay' => $relay, 'outcome' => $outcome->value]);
    }
});
```

## Vérifier une signature

Une signature de drand est une signature BLS sur la courbe BLS12-381. La bibliothèque ne la vérifie pas elle-même :
PHP n'a pas d'appariement sur cette courbe, et la bibliothèque n'exige aucune extension ni bibliothèque pour cela. Une
application qui en a une — une liaison vers une bibliothèque BLS par FFI, un service de vérification — implémente
`Contract\BeaconSignatureVerifier` et le passe à `DrandBeacon` et à `RaffleVerifier`.
`DrandScheme::message($round, $previousSignature)` donne les 32 octets qu'une ronde signe : le SHA-256 de la ronde
écrite sur 8 octets gros-boutistes, précédé, pour un schéma chaîné, des octets de la signature précédente. L'étiquette
de séparation de domaine de quicknet est `BLS_SIG_BLS12381G1_XMD:SHA-256_SSWU_RO_NUL_`.

Sans vérificateur, la ronde est confirmée par au moins deux relais tenus par des opérateurs différents, et la preuve
dit `"verification": "relay-quorum"` : n'importe qui vérifie ensuite la signature avec les clients de drand, à partir de
la ronde, de la signature et de la chaîne contenues dans la preuve. Avec un vérificateur, elle dit `"signature"`.

## La limite connue

Sans `BeaconSignatureVerifier`, la bibliothèque ne vérifie pas la signature BLS d'une ronde : elle compte que les relais
configurés, tenus par des opérateurs différents, ne mentent pas tous de la même façon. Des relais qui s'accorderaient
sur une ronde falsifiée passeraient le quorum. La preuve garde tout ce qu'il faut pour le découvrir après coup — la
ronde, sa signature et la clé publique de la chaîne —, et un joueur qui vérifie la signature avec les outils de drand
voit la falsification ; le tirage ne peut pas être jugé honnête tant que personne ne l'a fait. Pour combler l'écart,
configurez des relais d'opérateurs distincts, fixez le quorum à tous pour un tirage de valeur, et branchez un
vérificateur.

## Derrière un mandataire ou un pare-feu

Les relais sont configurés par l'application, jamais donnés par un visiteur. Sur un serveur qui ne doit pas joindre
son propre réseau, enveloppez le client dans le `NoPrivateNetworkHttpClient` de Symfony.

<!-- phil-dev source-sha256=e72c5bbda326ce374f89d62952eb50c5aadcfcbb61f93eeff189da2276c9d208 -->
# Le tirage et sa preuve

## Les empreintes

Chaque empreinte est le SHA-256, en 64 chiffres hexadécimaux minuscules, d'un JSON canonique : le `json_encode()` de
PHP avec `JSON_UNESCAPED_SLASHES` et `JSON_UNESCAPED_UNICODE`, sans espace.

| Valeur | Empreinte de |
|---|---|
| L'empreinte des participants, annoncée à la fermeture des inscriptions | `["tilttek-raffle/1", raffle, [entrants in byte order]]` |
| L'engagement d'un tirage par engagement-révélation, publié à son ouverture | les 64 chiffres hexadécimaux du germe |
| Le germe du tirage | `["tilttek-raffle/1", raffle, entrants hash, randomness]` |

L'aléa est celui de la ronde de la balise — le SHA-256 de sa signature — ou le germe que l'organisateur a révélé.
L'ordre des octets est celui du `sort($entrants, SORT_STRING)` de PHP : `B` avant `a`, `10` avant `9`.

## Le mélange

Les noms tirés sont les `min(winners + alternates, entrants)` premiers d'un mélange partiel de Fisher-Yates des
participants dans l'ordre des octets. À l'étape `i`, à partir de 0, on échange le participant `i` avec le participant
`i + u`, où `u` est tiré uniformément sous le nombre de participants restants, `n - i` :

1. calculez le SHA-256 des 32 octets du germe suivis de `i` et d'un compteur de tentatives, à partir de 0, tous deux
   écrits sur 4 octets gros-boutistes ;
2. lisez ses 6 premiers octets comme un entier gros-boutiste `v`, inférieur à 2⁴⁸ ;
3. si `v` est inférieur au plus grand multiple de `n - i` qui ne dépasse pas 2⁴⁸, `u` est `v` modulo `n - i` ; sinon,
   ajoutez un au compteur de tentatives et recommencez.

Rejeter les valeurs au-delà du dernier multiple entier garde chaque participant également probable ; avec 100 000
participants, une valeur est rejetée moins d'une fois par milliard de tirages. Les noms tirés sont les gagnants
d'abord, puis les substituts.

Un tirage de plusieurs prix — un podium — est un seul tirage : le premier nom tiré reçoit le premier prix, le deuxième
le deuxième, et ainsi de suite, puis viennent les substituts dans l'ordre où ils remplacent un gagnant.
`RaffleProof::place($entrant)` donne cette place, 1 pour le premier prix, ou null pour un participant non tiré. L'ordre
fait partie de la preuve : un joueur qui échange deux places ou retire un nom la brise.

## La preuve

`RaffleProof::toJson()` écrit :

```json
{
  "version": "tilttek-raffle/1",
  "raffle": "door-prize",
  "stake": "prize",
  "winners": 3,
  "alternates": 2,
  "entrants": ["member:1042", "member:1077"],
  "entrants_hash": "…",
  "source": {
    "kind": "beacon",
    "chain": {"hash": "52db9b…", "genesis": 1692803367, "period": 3, "scheme": "bls-unchained-g1-rfc9380", "public_key": "…"},
    "beacon": {"round": 1234567, "randomness": "…", "signature": "…", "previous_signature": null, "verification": "relay-quorum"}
  },
  "seed": "…",
  "drawn": ["member:1077", "member:1042"],
  "sealed_at": "2026-11-12T20:00:00.000000Z",
  "drawn_at": "2026-11-12T20:01:00.000000Z"
}
```

Une preuve par engagement-révélation a `"source": {"kind": "commit-reveal", "commitment": "…", "seed": "…"}`.
`fromJson()` lit 16 Mio au plus, sur 8 niveaux de profondeur, chaque champ vérifié, et lance `InvalidRaffle` pour tout le
reste.

## Vérifier une preuve

`RaffleVerifier::problems($proof)` liste ce qui casse la preuve, une phrase par défaut ; `holds()` est vrai quand il
n'y en a aucun ; `problemsOfJson($json)` lit d'abord le texte publié, une preuve qui ne se lit pas comptant pour un
défaut. Il vérifie que :

- un tirage à prix a tiré d'une ronde de balise ;
- les participants sont chacun listés une fois, dans l'ordre des octets, et ont pour empreinte celle des participants ;
- la ronde de la balise est la première publiée après la fermeture, ou une ronde ultérieure, ou le germe révélé
  correspond à l'engagement ;
- le germe du tirage est l'empreinte du tirage, de l'empreinte des participants et de l'aléa ;
- le mélange à partir de ce germe donne les noms tirés, dans le même ordre.

Avec un `BeaconSignatureVerifier`, il vérifie aussi la signature de la ronde avec la clé publique de la chaîne. Sans
lui, un joueur vérifie la signature avec les clients de drand : la ronde, sa signature et la chaîne sont dans la
preuve. Un joueur vérifie aussi que l'empreinte des participants et la ronde sont ceux que l'organisateur a annoncés
avant le tirage, et que sa propre référence figure parmi les participants.

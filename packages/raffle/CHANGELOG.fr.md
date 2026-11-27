<!-- phil-dev source-sha256=cacdde9b481adcb4ae75ade45d4f31e2071422fa9f5c8d391dbad98f321c8e2f -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/raffle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-24

### Modifié

- Requiert la dernière version mineure de chaque dépendance : `symfony/http-client-contracts` 3.8.

## 0.1.0-alpha - 2026-11-20

### Ajouté

- `Raffles` : des tirages au sort ouverts avec un enjeu, des participations de références opaques, une chance par
  participant et jusqu'à 100 000 participants, les participations scellées et hachées avec l'annonce publiée, le tirage
  de 1 à 1 000 gagnants et de 0 à 1 000 suppléants, la preuve, et `Event\RaffleDrawn` distribué avec l'identifiant du
  tirage au sort seulement.
- `Stake`, `SeedSource` et `RaffleStatus` : un tirage au sort avec un prix toujours amorcé par une ronde d'une balise
  publique, un tirage au sort sans enjeu par le germe de l'organisateur engagé à l'ouverture et révélé au tirage, ou par
  la balise sur demande.
- `Raffle` : le tirage au sort tel que le stockage le garde, à une version, écrit et relu en valeurs simples champ par
  champ.
- `Announcement` : le nombre et l'empreinte des participants, l'engagement ou la ronde de balise à venir et l'heure de
  sa publication.
- `RaffleProof` et `RaffleVerifier` : la preuve en JSON, relue dans la limite de 16 Mio, la place de chaque nom tiré —
  le podium, puis les suppléants —, et sa vérification — la source qu'exige l'enjeu, les participants énumérés une seule
  fois dans l'ordre des octets et hachés, la ronde publiée après la clôture ou le germe qui correspond à son engagement,
  le germe du tirage, et les noms tirés de nouveau par le mélange partiel de Fisher-Yates documenté avec échantillonnage
  par rejet —, avec un vérificateur de signature facultatif.
- `Beacon\Beacon`, `Beacon\BeaconChain`, `Beacon\BeaconVerification`, `Contract\RandomnessBeacon` et
  `Contract\BeaconSignatureVerifier` : des rondes de balise dont le hasard est le SHA-256 de leur signature, des chaînes
  et les heures de leurs rondes, et les ports d'une balise et d'un vérificateur de signature.
- `Drand\DrandBeacon`, `Drand\DrandChains` et `Drand\DrandScheme` : drand lu par Symfony HttpClient à partir de 1 à 8
  relais HTTPS vérifiés par `phil/url`, chaque relais borné en temps et en taille par le transport et la lecture entière
  sur une horloge PSR-20, la clé publique de la chaîne lue et comparée, une ronde gardée quand un quorum de relais
  s'entend sur elle, refusée quand un relais en contredit un autre ; quicknet et le premier réseau ; les quatre schémas
  de signature et le message que signe une ronde ; la signature BLS laissée à un vérificateur facultatif, une limite que
  la documentation énonce.
- `Beacon\RelayReport`, `Beacon\RelayOutcome` et `Event\BeaconRelaysRead` : ce que chaque relais a donné à chaque
  lecture — d'accord, en désaccord, invalide, un statut, trop grand, délai dépassé, injoignable ou incomplet —,
  distribué après chaque lecture et porté par le `BeaconUnavailable` ou le `BeaconRefused` qui la refuse.
- `Contract\RaffleStore` et `Store\InMemoryRaffleStore` : des tirages au sort et des participants gardés par version,
  une participation qui fait la course avec la clôture refusée comme un conflit, des participants changés après la
  clôture détectés avant un tirage ou une preuve.
- `Exception\RaffleException`, avec `InvalidRaffle`, `RaffleRefused`, `RaffleConflict`, `BeaconUnavailable` et
  `BeaconRefused`.
- Des cibles de fuzzing pour une preuve publiée et pour les réponses d'un relais drand, et des bancs d'essai d'une
  participation, d'un tirage parmi 100 000 participants et de la vérification d'une preuve de 10 000 participants.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.

<!-- phil-dev source-sha256=119de1d742a1f5b5793cbd883e2e9330b20026de1ef436cf2611a19f4be3ce5f -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce qu'une application donne | Valeur par défaut |
|---|---|---|
| `Limits` | La taille des listes qu'elle accepte | 2 000 participants, 5 000 offres, 100 par participant, 500 souhaits par offre, 100 offres par groupe, 250 000 offres souhaitées, 8 Mio de JSON |
| Les identifiants | Ses propres identifiants de participants, d'offres et de jeux | Aucune |
| Le germe | Le texte qui départage les égalités, annoncé ou tiré par l'application | Aucune |

La bibliothèque n'a aucune interface à implémenter : la liste d'échange, les limites et le résultat sont des valeurs,
et `TradeSolver` et `TradeListJson` ne gardent aucun état entre deux appels.

## Limites

Le temps du solveur croît avec le nombre d'offres et d'offres souhaitées — voir [Tests](testing.md#complexité) —, si
bien que chaque liste est vérifiée contre ses limites avant d'être résolue, et un document JSON contre sa longueur
avant d'être décodé. Une application qui résout de plus grands échanges les relève ; une application qui accepte des
téléversements de n'importe qui les abaisse.

```php
use Tilttek\MathTrade\Limits;

$limits = new Limits(
    participants: 500,
    offers: 2_000,
    offersPerParticipant: 20,
    wishesPerOffer: 200,
    groupSize: 50,
    wishedOffers: 100_000,
    jsonBytes: 1_048_576,
);
```

| Limite | Compte | Plafond |
|---|---|---|
| `participants` | Les participants de la liste | 100 000 |
| `offers` | Les offres de la liste | 100 000 |
| `offersPerParticipant` | Les offres d'un participant | 100 000 |
| `wishesPerOffer` | Les lignes de la liste de souhaits d'une offre, un groupe compté une fois | 10 000 |
| `groupSize` | Les offres d'un groupe de souhaits | 10 000 |
| `wishedOffers` | Les offres souhaitées de toute la liste, chaque offre d'un groupe comptée | 5 000 000 |
| `jsonBytes` | La longueur d'un document JSON d'où la liste est lue | 256 Mio |

Chaque limite va de 1 à son plafond ; une autre valeur est refusée par une `InvalidTradeList`.

## Ce qui reste à l'application

- **Recueillir les listes.** Un formulaire, une importation de tableur ou un générateur de listes de souhaits construit
  la `TradeList`, ou le document JSON du [format](json-format.md).
- **Le germe.** L'application l'annonce avant la fermeture des listes, ou le tire ensuite en public, et le publie à
  côté du résultat.
- **Les noms et les adresses.** La bibliothèque travaille sur des identifiants. L'application montre à chaque
  participant l'adresse de celui à qui il envoie une fois l'échange confirmé, jamais avant.
- **Les colis.** Suivre ce qui a été envoyé et reçu, et les participants qui n'ont pas envoyé, revient à
  l'application.

<!-- phil-dev source-sha256=9d416dea6e41a04e0ec6ffe9e009a02768c218667c812e55bb41e8fbce123797 -->
# Mener un échange mathématique

Un échange mathématique se déroule en quatre étapes : les participants listent leurs offres et leurs souhaits, les
listes se ferment, l'organisateur les résout avec un germe annoncé d'avance, et chaque participant lit ce qu'il envoie
et ce qu'il reçoit. Cette bibliothèque fait la seconde moitié du travail : elle vérifie les listes et trouve les
échanges. Recueillir les listes, publier le germe, montrer les adresses et suivre les colis reviennent à
l'application.

## La liste d'échange

Une `TradeList` contient tous les `Participant` ; chaque participant fait une `Offer` ou plus, un exemplaire d'un jeu
qu'il met dans l'échange. Une offre liste ses souhaits, du plus souhaité au moins souhaité : les offres contre
lesquelles elle serait échangée.

```php
use Tilttek\MathTrade\Offer;
use Tilttek\MathTrade\Participant;
use Tilttek\MathTrade\TradeList;
use Tilttek\MathTrade\Want;
use Tilttek\MathTrade\WantGroup;

$list = new TradeList([
    new Participant('alice', [
        new Offer('alice-azul', [new Want('bob-catan'), new WantGroup(['carol-wingspan', 'dave-wingspan'])], 'azul'),
        new Offer('alice-ark', [new WantGroup(['carol-wingspan', 'dave-wingspan'])], 'ark-nova'),
    ]),
    new Participant('bob', [new Offer('bob-catan', [new Want('alice-ark'), new Want('dave-wingspan')], 'catan')]),
    new Participant('carol', [new Offer('carol-wingspan', [new Want('alice-azul')], 'wingspan')]),
    new Participant('dave', [new Offer('dave-wingspan', [new Want('bob-catan'), new Want('alice-ark')], 'wingspan')]),
]);
```

Chaque ligne de la liste de souhaits a une priorité : 1 pour la première ligne, 2 pour la deuxième, et ainsi de suite.
Un `Want` nomme une seule offre ; un `WantGroup` nomme deux offres ou plus à une seule priorité — « n'importe laquelle
de celles-ci » —, dont l'offre est échangée contre une au plus.

Les identifiants sont ceux de l'application : un participant, une offre et un jeu ont chacun de 1 à 100 octets d'UTF-8
sans caractère de contrôle. Un identifiant fait de chiffres vaut n'importe quel autre.

### Ce qu'une liste refuse

La liste est vérifiée à sa construction, et refusée par une `InvalidTradeList` quand :

- deux participants, ou deux offres, ont le même identifiant ;
- un participant ne fait aucune offre ;
- une offre souhaite une offre qui est elle-même, une offre que personne ne fait, ou une offre du même participant ;
- une offre souhaite deux fois la même offre, sur deux lignes ou dans un groupe ;
- un groupe nomme moins de deux offres.

Elle est refusée par une `LimitExceeded` quand elle est plus grande que ne le permettent ses `Limits` : voir
[Points d'extension](extension-points.md#limites).

## Les exemplaires d'un même jeu

Le troisième argument d'une offre nomme le jeu dont l'exemplaire est la copie : deux exemplaires de Wingspan partagent
le jeu `wingspan`. Il fait une seule chose : un participant qui souhaite un jeu à partir de deux de ses offres ou plus,
quand deux exemplaires ou plus de ce jeu sont souhaités, reçoit ce jeu **une seule fois au plus**. Plus haut, Alice
souhaite Wingspan à partir de ses deux offres, et deux exemplaires sont offerts : elle reçoit un seul Wingspan, jamais
deux.

Le solveur donne à un tel participant un nœud à lui pour le jeu, entre ses offres et les exemplaires, par lequel on ne
peut passer qu'une fois. Les exemplaires d'un jeu sont alors interchangeables pour ce participant : chacune des offres
qui souhaitent le jeu peut recevoir n'importe quel exemplaire que souhaite l'une de ses offres, à la meilleure
priorité que cette offre a donnée à un exemplaire.

Une offre sans jeu est un jeu à elle seule : elle n'est jamais gardée par ce mécanisme. Un participant qui souhaite un
jeu à partir d'une seule offre, ou dont un seul exemplaire est souhaité, n'a pas besoin de garde : une offre reçoit un
seul jeu.

## Résoudre

```php
use Tilttek\MathTrade\TradeSolver;

$result = new TradeSolver()->solve($list, 'spring-trade-2026');
```

Le solveur trouve, dans cet ordre :

1. le plus d'offres échangées ;
2. parmi les résultats qui échangent autant, la plus petite somme des priorités satisfaites : les premiers souhaits
   avant les derniers ;
3. parmi les résultats égaux sur les deux, celui que choisit le germe.

Une offre n'est jamais échangée contre quelque chose qu'elle n'a pas souhaité, ne change jamais de mains deux fois, et
personne n'échange avec soi-même.

### Le germe

Le germe est un texte de 1 à 256 octets d'UTF-8 sans caractère de contrôle. Il mélange les offres avant leur
résolution, ce qui départage les résultats de même valeur. La même liste et le même germe donnent le même résultat,
quel que soit l'ordre dans lequel les participants et les offres ont été donnés : annoncez le germe avant la fermeture
des listes — ou tirez-le ensuite en public, avec des dés ou un tirage de loterie —, puis publiez-le à côté du
résultat, pour que n'importe qui puisse résoudre de nouveau les listes publiées et vérifier les échanges.

Un germe invalide est refusé par une `InvalidSeed`.

## Le résultat

Un `TradeResult` contient :

| Membre | Ce qu'il donne |
|---|---|
| `seed` | Le germe avec lequel il a été résolu, à publier à côté de lui |
| `cycles` | Les boucles d'échanges, chacune un `TradeCycle` de `Trade`, ordonnées par l'identifiant de leur première offre |
| `unmatched` | Les offres qu'aucune boucle ne prend, par identifiant |
| `trades()` | Chaque `Trade` — offre, donneur, receveur —, boucle après boucle |
| `tradeCount()` | Le nombre d'offres qui changent de mains |
| `swaps()` | Chaque `Swap`, par identifiant de l'offre envoyée |
| `forParticipant($id)` | Ce que le participant envoie et reçoit : un `Swap` par offre à lui qui est échangée |
| `swapOf($offer)` | Le `Swap` dans lequel l'offre est envoyée, ou null quand elle est gardée |

Un `Swap` est la ligne que lit chaque participant : il envoie `sent` à `sendTo`, et reçoit `received` de
`receiveFrom`.

```php
foreach ($result->forParticipant('alice') as $swap) {
    printf("Send %s to %s, receive %s from %s\n", $swap->sent, $swap->sendTo, $swap->received, $swap->receiveFrom);
}
```

Avec la liste ci-dessus et le germe `spring-trade-2026`, chaque offre est échangée, dans une boucle de cinq :

```text
Send alice-ark to dave, receive carol-wingspan from carol
Send alice-azul to carol, receive bob-catan from bob
```

Alice reçoit le Catan de Bob, son premier souhait, et un seul exemplaire de Wingspan, même si elle l'a souhaité à
partir de ses deux offres ; Carol reçoit Azul, Dave reçoit Ark Nova, et Bob reçoit le Wingspan de Dave, son deuxième
souhait. Si Bob avait reçu Ark Nova, son premier, Alice aurait eu besoin des deux exemplaires de Wingspan pour tout
échanger : la garde l'interdit, et échanger cinq offres passe avant les priorités. La boucle se trouve dans `cycles` :

```php
foreach ($result->cycles as $cycle) {
    foreach ($cycle->trades as $trade) {
        printf("%s sends %s to %s\n", $trade->giver, $trade->offer, $trade->receiver);
    }
}
```

## De bout en bout

Les listes des participants arrivent d'ordinaire en JSON, sur [le format de cette bibliothèque](json-format.md).
L'organisateur les lit, les résout avec le germe annoncé, publie le résultat, et dit à chaque participant sa part :

```php
use Tilttek\MathTrade\Exception\MathTradeException;
use Tilttek\MathTrade\Limits;
use Tilttek\MathTrade\TradeListJson;
use Tilttek\MathTrade\TradeSolver;

try {
    $list = new TradeListJson()->decode($uploadedJson, new Limits(offers: 3_000));
} catch (MathTradeException $exception) {
    // Show the participant why their list was refused: the message names the offer and the rule.
    return $exception->getMessage();
}

$result = new TradeSolver()->solve($list, $announcedSeed);

printf("%d offers trade hands in %d loops; %d are kept.\n", $result->tradeCount(), \count($result->cycles), \count($result->unmatched));

foreach ($list->participants as $participant) {
    foreach ($result->forParticipant($participant->id) as $swap) {
        // The application looks the participants up, and shows the address of sendTo once the trade is confirmed.
        $notify($participant->id, $swap->sent, $swap->sendTo, $swap->received, $swap->receiveFrom);
    }
}
```

La bibliothèque ne voit jamais un nom ni une adresse : elle travaille sur des identifiants, et l'application décide du
moment où un participant peut voir l'adresse de celui à qui il envoie.

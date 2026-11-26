<!-- phil-dev source-sha256=632a9ea0b7d2ebe28fec90195b538d8956999985a39f7f04e462cd6cf263e160 -->
# Appariements

Chaque appariement de la bibliothèque est calculé à partir des seuls résultats, du premier ordre et de la graine
publiée : le même tournoi donne les mêmes appariements chaque fois, et quiconque détient la graine peut les vérifier.
Chaque table porte ses raisons, sous forme de valeurs `PairingReason` d'un `ReasonKind` qu'une application traduit,
chacune avec le participant qu'elle concerne et, quand elle en a, l'autre participant et la ronde à laquelle elle renvoie.

## Rondes suisses

`Swiss\SwissPairer` apparie une ronde par groupes de points. Il prend les règles suisses de la FIDE comme référence
écrite, non comme une implémentation certifiée de ces règles : un tournoi qui doit suivre à la lettre le système
néerlandais de la FIDE, pour un événement coté, utilise un programme d'appariement certifié et publie sa ronde avec
`publishRound()`.

### Ce qu'il respecte

- **Pas deux rencontres.** Deux participants qui se sont affrontés à l'échiquier ne sont pas appariés de nouveau tant
  qu'un autre appariement de la ronde l'évite. Un forfait n'est pas une rencontre.
- **Une seule exemption.** Avec un nombre impair de participants, l'exemption va au participant le plus bas classé qui
  n'en a jamais eu, et à un participant ayant moins de points avant un autre en ayant plus. Une deuxième exemption n'est
  donnée que si tous ceux qui restent en ont eu une.
- **Groupes de points.** Les participants sont appariés avec d'autres de leurs points ; quand un groupe ne peut pas être
  apparié en entier, un participant descend ou monte au groupe le plus proche, et un grand écart pèse plus que deux
  petits.
- **Moitié haute contre moitié basse.** Dans un groupe de points, le premier de la moitié haute rencontre le premier de
  la moitié basse, et ainsi de suite, dans la mesure où les autres règles le permettent.
- **Côtés alternés.** Un participant qui a joué second plus souvent, ou en dernier, joue en premier. Quand deux
  participants se rencontrent de nouveau, chacun joue le côté qu'il n'a pas joué la fois d'avant. Entre deux participants
  qui doivent le même côté, celui qui le doit davantage l'obtient ; avec le même historique, le mieux classé obtient le
  premier côté aux tables impaires.

L'exemption, ses points et sa raison figurent sur la ronde préparée, avant sa publication, pour qu'un organisateur
montre pourquoi la ronde a une exemption et qui l'a.

### Comment il fonctionne

Les participants sont classés par points, puis par le premier ordre. Chaque paire possible a un coût, et la ronde est
l'ensemble de paires de coût total le plus bas. Du plus lourd au plus léger, les coûts pèsent :

1. une revanche ou une deuxième exemption — plus que tous les autres coûts d'une ronde réunis ;
2. l'exemption donnée à un participant ayant plus de points ;
3. l'écart de points entre les deux d'une paire, mis au carré ;
4. deux participants qui doivent jouer le même côté, davantage quand les deux le doivent ;
5. dans un groupe de points, une paire autre que moitié haute contre moitié basse ; entre deux groupes, une paire très
   éloignée au classement ; pour l'exemption, un participant mieux classé.

Jusqu'à 16 participants — l'exemption comptant pour un — le coût total le plus bas est trouvé exactement, par un
programme dynamique sur les ensembles de participants restants ; son temps croît comme 2ⁿ, ce qui explique qu'il
s'arrête là. `new SwissPairer(exactLimit: 20)` relève la limite à 20 au plus. Au-delà, une recherche bornée apparie
tour à tour chaque participant avec le moins coûteux qui reste, puis échange les partenaires de deux paires tant qu'un
échange abaisse le coût, pendant 20 passes au plus — `new SwissPairer(passes: 100)` en permet davantage —, chaque passe
lisant une fois chaque couple de paires.

| Participants | Appariement | Temps d'une ronde, mesuré |
|---|---|---|
| 16 | exact | environ 2 ms |
| 20, avec `exactLimit: 20` | exact | environ 20 ms |
| 64 | borné | environ 2 ms |
| 512 | borné | environ 90 ms |

### Limites

- **Une ronde à la fois.** Chaque ronde est appariée à son coût le plus bas sans regarder en avant : un appariement peut
  laisser à une ronde ultérieure aucun appariement sans revanche, quand une étape a presque autant de rondes que de
  participants — six participants sur cinq rondes, par exemple. L'apparieur apparie alors la revanche de moindre coût et
  le dit par des raisons `Rematch`. Un champ d'au moins deux fois plus de participants que de rondes n'a jamais connu ce
  cas dans les tests de propriétés.
- **Recherche bornée au-delà de la limite exacte.** Les échanges de deux paires manquent un meilleur appariement qui
  exige que trois paires ou plus changent à la fois. Le résultat reste le même chaque fois ; dans les tests de
  propriétés, des champs jusqu'à 64 participants avec jusqu'à 7 rondes ont respecté chaque règle ci-dessus.
- **Côtés simplifiés.** Les règles de côtés sont celles énumérées plus haut, non chaque critère de couleur des règles
  de la FIDE ; il n'y a pas d'historique de descente au-delà de l'écart de points.
- **512 participants par ronde au plus.**

### Raisons

| Raison | Donnée quand |
|---|---|
| `Bye` | Le participant a l'exemption : le plus bas classé qui n'en a jamais eu. |
| `ByeRepeated` | Le participant a une deuxième exemption : tous ceux qui restent en ont eu une. |
| `FirstPlayerAlternated` | Le participant joue en premier : il a joué second plus souvent, ou en dernier. |
| `FirstPlayerRepeated` | Le participant joue de nouveau en premier : les deux devaient jouer en premier, l'autre moins. |
| `FirstPlayerAlternatedRematch` | Le participant joue en premier : il a joué second quand les deux se sont rencontrés avant. |
| `FirstPlayerByRank` | Le même historique de côtés : le mieux classé joue en premier aux tables impaires. |
| `RematchAvoided` | Le participant n'est pas apparié avec un autre de mêmes points qu'il a déjà rencontré, à la ronde donnée. |
| `Rematch` | Le participant rencontre de nouveau un adversaire : aucun appariement de la ronde ne l'évitait. |
| `Floated` | Le participant rencontre un adversaire d'autres points : son groupe de points n'a pu être apparié en entier. |

## Poules

`RoundRobin\BergerTable` construit les rondes d'une poule par les tables de Berger, avec la méthode du cercle : le
dernier numéro reste en place et les autres tournent d'un cran à chaque ronde. Chaque paire se rencontre une fois par
tour, chaque ronde a le même nombre de tables, et chaque participant joue en premier dans la moitié de ses matchs, à un
près. Une poule de taille impaire a un repos à chaque ronde, chaque participant à tour de rôle ; le repos est une table
d'une seule personne, avec `Rest`, qui ne marque rien et figure dans le calendrier du participant. Un deuxième tour
rejoue les rondes du premier avec les côtés échangés, avec `ReturnMatch`.

`RoundRobin\Pools` répartit les participants sur les poules en serpentin, selon le premier ordre : les premières têtes de
série dirigent les poules A, B, C dans l'ordre, les suivantes les remplissent en sens inverse à partir de la dernière,
pour que les poules soient aussi égales que les têtes de série le disent. Les rondes des poules se jouent côte à côte :
la ronde n contient les tables de la ronde n de chaque poule, numérotées à la suite d'une poule à l'autre, chaque table
avec sa poule. Leur raison est `Scheduled`.

## Tableaux

`Elimination\Bracket` place les têtes de série pour que les deux premières se rencontrent en finale au plus tôt, les
quatre premières en demi-finales, et que chaque tête de série rencontre la plus faible qui reste à la première ronde :
1 contre 8, 4 contre 5, 2 contre 7, 3 contre 6 dans un tableau de huit. La taille d'un tableau est la puissance de deux
suivante ; les têtes de série manquantes sont des exemptions, de sorte que les premières têtes de série passent la
première ronde sans jouer, montrées comme des tables d'une seule personne. Chaque match suivant prend les gagnants de
deux matchs précédents. Les tables sont numérotées par match, et leur raison est `Seeded`.

Un tableau fait pour une deuxième étape est préparé avant que ses qualifiés soient connus : ses places sont des valeurs
`Elimination\Slot` qui se lisent `swiss#1`, `pools/A#1`, `pools/B#2` — le rang d'une étape ou d'une poule — jusqu'à ce
que l'étape précédente soit terminée, puis contiennent les participants. `Tournaments::bracket()` le donne à tout
moment, rempli de ce qui est connu.

Un match a un gagnant : un nul est refusé, et se décide par les règles propres du jeu avant d'être saisi. Un participant
qui se retire avant de jouer un match le perd par forfait ; un match que les deux côtés ont perdu laisse la place
suivante vide, et le participant qui s'y trouve passe.

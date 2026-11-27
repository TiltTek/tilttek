<!-- phil-dev source-sha256=c09f740e9308ef1d44bf409df2cf0d712c51f1d2b17b3288c01217adae5b6ea9 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/dice` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.3.1-alpha - 2026-11-20

### Corrigé

- La forme canonique écrit entre crochets les succès qui suivent une explosion sans seuil ni limite, `1d10!pc[>=4]` :
  écrite seule, `1d10!p>=4`, la comparaison se relisait comme le seuil de l'explosion, et la forme canonique de
  `1d10>=4!p` ou de `3d6c[>=5]!` lançait d'autres totaux que l'expression dont elle venait.

## 0.3.0-alpha - 2026-11-17

### Modifié

- Un engagement lie le nonce d'un lancer en plus du germe : `Seed::commitment($nonce)` est le SHA-256 de
  `Commitment::FORMAT` — `tilttek/dice commitment v2` —, d'un octet nul, des 32 octets du germe et du nonce sur 8 octets
  gros-boutistes. Un engagement montré pour un lancer ne garantit plus un lancer fait sous un autre nonce du même germe.
  `FairRoller::roll()` donne l'engagement de son nonce, et `FairRoller::verify()` et
  `Commitment::matches($seed, $nonce)` vérifient ensemble le germe et le nonce. Le format, le protocole — engagement
  publié avant le germe du client, nonce réservé une seule fois par l'hôte, germe révélé après — et des vecteurs de test
  fixes sont dans la documentation des lancers vérifiables.
- Le nonce de `Seed::commitment()` et de `Commitment::matches()` vaut 0 quand il n'est pas donné, comme pour
  `Seed::randomizer()` : les appels écrits pour 0.2 restent valides et donnent l'engagement du lancer numéro 0.
- Un engagement au format 0.2, le SHA-256 du germe seul, est refusé : il ne correspond à aucun germe et `verify()`
  renvoie false pour lui. Il ne lie pas le nonce, et le lire garderait la faiblesse que cette version retire. Pour
  migrer un hôte : passez le nonce de chaque lancer à `commitment()` avant de le publier ; les lancers publiés sous 0.2
  se vérifient avec 0.2, ou en recalculant le SHA-256 de leur germe révélé.

## 0.2.0-alpha - 2026-11-14

### Corrigé

- Un germe du client n'avait pas de longueur maximale, alors qu'il entre en entier dans le HMAC de chaque lancer : un
  germe envoyé dans une requête publique pouvait faire coûter à un lancer autant de travail et de mémoire que sa
  longueur. `Seed::randomizer()`, et donc `FairRoller` et `DiceRoller::rollVerifiable()`, refusent un germe du client de
  plus de 256 octets, `Seed::MAX_CLIENT_SEED`, avec `InvalidDefinition` ; la borne est celle des germes du client de
  `tilttek/collections`.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- Le modèle d'expression : `Pool` d'instructions avec leurs commentaires, `Dice`, `ComputedDice` dont le nombre ou les
  faces sont lancés d'abord, `ValueList`, `PreviousDice`, `Constant`, `Operation` avec `Operator` — `+ - * / % ^` —,
  `Negation`, `FunctionCall` avec `MathFunction` — `floor ceil round abs min max repeat`, chaque fonction sauf les trois
  qui arrondissent gardant l'arrondi du lancer —, et les dés `StandardDie`, `FudgeDie` pondéré ou non, `RangeDie` et
  `CustomDie`, les dés numériques lançant leurs propres faces.
- Le langage des instructions : `Reference` au résultat d'une instruction précédente ou à son élément, `InstructionDice`
  qui reprend les dés d'instructions, liés ou fusionnés, `Group` qui juge comme des dés les totaux de ses expressions,
  `TextList` avec ses `ListItem` tirés au poids ou par plage, uniques ou joints, `Sentence` avec ses `Placeholder` et
  `PlaceholderKind`, `Conditional` et `Choice` avec ses `SwitchCase`, exclusifs ou non, avec une branche par défaut et
  la portée de leurs conditions.
- `Modifiers`, appliqués dans un ordre fixe : `Reroll` répété ou une seule fois (`Repetition`), dés uniques, `Explosion`
  étalée, composée ou pénétrante et limitée par un nombre ou un lancer, dés tous identiques, planchers et plafonds,
  `Transform`, filtres, `Selection` avec `SelectionMode`, succès et échecs, `Occurrences`, groupes qui atteignent une
  somme, marques `Critical`, jetons `Look` et `Sort` ; seuls les dés numériques prennent des modificateurs, les
  modificateurs contradictoires sont refusés, et un lancer qui ne finirait jamais est refusé.
- Les validateurs : `Validator`, `Comparison` avec `Comparator` — `!=` compris —, `Range`, `Modulo`, `Composite` avec
  `Junction` — `&`, `|`, `^` —, et `Condition` avec sa `Scope` : chaque valeur finale, chaque dé, tous, n'importe
  lequel, le total.
- Les dés personnalisés : `Face` avec son libellé, sa valeur, sa couleur, sa `Shape` et son image — jamais reconnue à sa
  couleur seule —, `DieDefinition` et `CustomDice` ; un lancer compte les faces par symbole et les additionne quand
  elles portent des valeurs.
- `Notation` et `Notations` : les lettres d'une langue, l'allemand, le danois, le norvégien et le suédois intégrés,
  trouvées par locale avec un repli sur la langue.
- `Lexer` et `DiceParser` : la notation lue sans expressions régulières, lettres dans l'une ou l'autre casse, `d%`, `dF`
  et `dF.1`, `d[a..b]`, `d{name}`, nombres et faces entre parenthèses, listes `[…]`, `@`, `kh kl dh dl`, `k` et `d`
  seuls, `adv` et `dis`, `r` et `ro`, `u` et `uo`, `!`, `!!`, `!p` et leur limite, `a`, `t`, `min`, `max`, `t[…]`,
  `f[…]`, succès par comparaison ou `c[…]`, échecs `f`, `o`, `g`, `cs`, `cf`, `look[…]`, `s`, `sa`, `sd`, validateurs
  entre crochets avec leur portée, fonctions, arithmétique entière avec parenthèses, négation, reste et puissance ;
  instructions séparées par `;` ou `,`, `$1` et `$1[0]`, `@1`, `b[…]` et `m[…]`, groupes `{…}`, listes de textes `L[…]`
  avec `u`, `n`, poids et plages, textes entre guillemets droits avec leurs échappements et leurs espaces réservés,
  `i[…]{…}{…}`, `S[…]{…}` et `S^`, `repeat()`, commentaires `#`, `//` et entre crochets, macros `$name` lues comme un
  seul jeton et résolues par `Contract\Macros` en leur arbre — une macro de plusieurs instructions écrite seule étant le
  lot entier — ; un texte ou une liste refusés dans l'arithmétique, une référence seulement à une instruction
  précédente ; des erreurs avec leur position et leur raison ; une imbrication bornée ; une cible de fuzzing.
- `RolisteamReader` : une commande du DiceParser de Rolisteam lue dans l'arbre de la bibliothèque — `k`, `kl`, `K`, `r`,
  `R`, `e` et sa limite, `a`, `c`, `s`, `sl`, `T[…]{…}`, `p[…]`, `o2,7`, les portées `*`, `.`, `:` et `?` d'un
  validateur, et chaque construction que les deux notations écrivent de la même façon —, ses jetons réécrits dans la
  notation de la bibliothèque, macros comprises, des erreurs placées dans la commande telle qu'elle est écrite, `y` et
  la fusion ou la liaison de chaque instruction refusés ; une cible de fuzzing.
- `Limits` : dés, faces, explosions, relances, répétitions, instructions, longueur et caractères d'un texte, refusés
  plutôt que tronqués ; nombres et faces lancés vérifiés une fois lancés, chaque opérateur répété compté, les éléments
  tirés des listes de textes comptés comme des dés, chaque branche comptée à l'analyse.
- `NotationWriter` et `ExpressionWriter` : la forme canonique de chaque construction, avec chaque nombre écrit, les
  modificateurs dans l'ordre où ils s'appliquent, pas plus de parenthèses que nécessaire, les éléments d'une liste de
  textes entre guillemets quand il le faut — et écrits seuls par `writeItem()` — et les échappements dont un texte a
  besoin.
- `Roller`, `Arithmetic` et les résultats `PoolResult`, `ExpressionResult`, `DiceResult`, `ConstantResult`,
  `OperationResult`, `NegationResult`, `FunctionResult`, `TextResult`, `ListResult`, `BranchResult`, `ReferenceResult`
  et `RolledDie` avec `DieOrigin` et `Mark` : un résultat est un nombre, un texte ou une liste, écrit par `Outputs` ;
  chaque dé gardé avec ses lancers, chaque modificateur appliqué, explosions, relances et répétitions comptées sur tout
  le lot, sélections stables, occurrences énumérées, groupes qui atteignent une somme : le plus grand nombre que les dés
  forment jusqu'à 12 dés, trouvés de façon gloutonne au-delà avec une `Notice`, instructions lancées dans l'ordre et
  instructions fusionnées sans total, seule la branche qui tient lancée, arithmétique entière dont les divisions
  arrondissent comme le dit l'appelant — vers le bas par défaut — à moins que `floor()`, `ceil()` ou `round()` disent
  autrement, dépassement refusé, tableaux simples pour un journal.
- `Seed`, `Commitment`, `FairRoller` et `VerifiableRoll` : lancers vérifiables par engagement d'un germe, un germe du
  client et un nonce, sur `Xoshiro256**` amorcé par un HMAC-SHA256, vérifiés avec leur arrondi, tout le langage couvert.
- `Replayer` et `ReplayableRoll` : des lancers avec un identifiant, un germe et un arrondi, rejoués dé pour dé.
- `Dialect`, `Translation`, `Unsupported` et `Dialects`, avec `Roll20`, `FoundryVtt`, `Avrae` et `RolisteamDiceParser` :
  un lot écrit dans la notation d'une table — relances, explosions, planchers, succès et échecs, marques critiques, tri,
  listes, fonctions, restes et puissances là où la table les écrit de la même façon —, une commande par expression ou,
  pour DiceParser, toutes les instructions dans une seule commande avec références, branches, listes de textes, phrases,
  groupes et `repeat()`, des divisions écrites arrondies vers le bas avec un avis quand le lancer arrondit autrement, et
  ce qu'un dialecte ne peut pas écrire signalé, constructions des instructions comprises ; `separator()` et `rangeDie()`
  qu'un dialecte peut redéfinir.
- `DiceRoller`, la bibliothèque en un seul objet : l'analyse avec les macros données, la lecture d'une commande de
  DiceParser, le lancer et la traduction avec l'arrondi que donne l'appelant.
- `DiceException`, implémentée par `InvalidExpression` avec son entrée, sa position et sa raison, `LimitExceeded`,
  `CannotRoll`, `InvalidDefinition` et `UntranslatableExpression`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.

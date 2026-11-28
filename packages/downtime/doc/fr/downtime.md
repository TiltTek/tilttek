<!-- phil-dev source-sha256=33649d7746297d7a1020d8fdc9228f04902def23cf9459fe8234c812db2dcb14 -->
# Temps libre

## Les activités

Les règles d'un système de jeu déclarent ce qu'un personnage peut faire entre les séances. Une `Activity` se trouve par
sa clé dans les règles de son système, et contient :

| Partie | Contient |
|---|---|
| `name`, `description` | Une ligne, et des lignes que lisent les joueurs, éventuellement vides. |
| `days` | Les jours qu'elle prend, de 1 à 365. |
| `costs` | Jusqu'à 8 `Cost` : une ressource que nomme le système — pièces d'or, provisions, une faveur — et un montant, payé une fois (`new Cost()`) ou chaque jour (`Cost::perDay()`). |
| `outcomes` | De 1 à 16 `Outcome` : une clé, un libellé, un texte, et jusqu'à 8 `Effect`, chacun un gain ou une perte dans une ressource. |
| `resolution` | Comment une issue est choisie : une `Resolution`. |

`Activity::totals()` donne ce qu'elle coûte sur ses jours, par ressource. La bibliothèque compte les coûts et les
effets ; l'application les débite et les applique, dans les ressources qu'elle tient.

Une activité est vérifiée d'un bloc à sa construction : chaque issue une fois, la ressource de chaque coût une fois,
la ressource de chaque effet une fois dans son issue, et toute issue que nomme la résolution déclarée.

`SystemRules` contient les activités d'un système, de 1 à 64, chaque clé une fois, dans l'ordre donné.

| Borne | Valeur |
|---|---|
| Activités d'un système | 64 (`SystemRules::MAX_ACTIVITIES`) |
| Jours d'une activité | 1 à 365 (`Activity::MAX_DAYS`) |
| Issues d'une activité | 1 à 16 (`Activity::MAX_OUTCOMES`) |
| Coûts d'une activité, effets d'une issue | 8 (`Activity::MAX_COSTS`, `Outcome::MAX_EFFECTS`) |
| Montant d'un coût | 1 à 1 000 000 (`Cost::MAX_AMOUNT`) |
| Changement d'un effet | −1 000 000 à 1 000 000, jamais 0 (`Effect::MAX_DELTA`) |
| Nom d'une activité, libellé d'une issue | une ligne de 1 à 120 caractères |
| Description d'une activité, texte d'une issue | 4000 et 2000 caractères |
| Identifiant d'une entrée, d'une campagne, d'un personnage, d'un membre ou d'un système | 1 à 128 lettres, chiffres, `_`, `.`, `:` ou `-`, commençant par une lettre |
| Clé d'une activité, d'une issue ou d'une ressource | 1 à 64 des mêmes caractères, commençant par une lettre |

Les textes sont de l'UTF-8 valide, sans espaces aux extrémités, et comptés en caractères. Une ligne ne contient aucun
caractère de contrôle ; un texte garde ses sauts de ligne et ses tabulations, ses fins de ligne écrites `\n`. Ni l'un
ni l'autre ne contient les caractères qui inversent le sens d'un texte.

## Les résolutions

| Construite avec | Se termine par |
|---|---|
| `Resolution::fixed('learned')` | Toujours cette issue. |
| `Resolution::rolled('1d20+2', $bands)` | L'issue de la `Band` où tombe le total. |

Une plage couvre les totaux de `from` à `to`, bornes comprises, entre −10 000 et 10 000. Un jet a de 1 à 16 plages,
données dans n'importe quel ordre ; une fois triées, elles se suivent sans chevauchement ni trou, et une même issue
peut revenir dans plusieurs d'entre elles. Un total sous la première plage tombe dans la première, un total au-dessus
de la dernière dans la dernière : un modificateur ne laisse jamais un jet sans issue.

L'expression de dés est celle du système : jusqu'à 64 lettres, chiffres, espaces et signes `+ - * / ( ) ! < > = . ,`,
sans espace à l'une ou l'autre extrémité. La bibliothèque ne la lit jamais ; c'est le `Contract\Roller` de
l'application qui la lit.

## Le journal et la file

Une `Entry` est une activité dans le journal d'un personnage : sa campagne, son personnage, le membre qui l'a choisie,
le système, l'activité telle qu'elle était alors déclarée, les jours écoulés, le jet, l'issue, l'étape (`Stage`) et la version.
Des règles changées plus tard ne changent jamais une entrée.

| Étape | L'entrée |
|---|---|
| `Queued` | A encore des jours à dépenser. |
| `AwaitingRoll` | A dépensé tous ses jours et attend le total obtenu à la table. |
| `Resolved` | A son issue et attend le maître de jeu. |
| `Confirmed` | A son issue confirmée : l'application applique ses effets. |
| `Cancelled` | A été annulée, avec les jours écoulés, le jet et l'issue qu'elle avait. |

Les trois premières étapes sont ouvertes : un personnage a au plus 8 entrées ouvertes (`Downtime::MAX_QUEUE`),
travaillées dans l'ordre où elles ont été choisies.

## Le service

`Downtime` est la seule façon de changer une entrée. Le `Contract\Roster` dit qui peut agir :

| Appel | Qui | Fait |
|---|---|---|
| `choose()` | Le joueur du personnage, ou le maître de jeu | Écrit dans le journal une activité des règles, mise en file après les entrées ouvertes. |
| `advance()` | Le maître de jeu | Dépense de 1 à 365 jours sur chaque file de la campagne. |
| `record()` | Le joueur, ou le maître de jeu | Enregistre le total obtenu à la table pour une entrée qui attend son jet. |
| `confirm()` | Le maître de jeu | Confirme l'issue, ou celle qu'il choisit à la place. |
| `cancel()` | Le joueur tant que l'entrée est en file ; le maître de jeu à toute étape ouverte | Annule l'entrée. |
| `log()` | Le joueur, ou le maître de jeu | Lit les entrées d'un personnage, dans l'ordre choisi, jusqu'à 1000. |
| `entry()` | Quiconque l'application laisse lire | Lit une entrée par son identifiant. |

### Faire avancer une campagne

`advance()` lit les entrées en file de la campagne, celles de chaque personnage, dans l'ordre choisi, jusqu'à 1000 ;
une campagne qui en a davantage est refusée. Il donne à chaque personnage les jours de l'avance, dépensés dans l'ordre
sur la file du personnage : une entrée prend les jours dont elle a encore besoin, et les jours restants vont à la
suivante. Une entrée dont tous les jours sont écoulés :

- se termine par son issue quand sa résolution est fixe ;
- se termine par l'issue de sa plage quand le service a un lanceur, le total étant gardé dans ±1 000 000 ;
- attend son jet sinon.

Toutes les entrées qu'il change sont conservées d'un coup, ou aucune : un magasin qui en trouve une changée entre-temps
lève `DowntimeConflict`, et l'avance se refait sur ce qui est lu de nouveau. Il renvoie les entrées changées, dans
l'ordre choisi.

### Confirmer

`confirm()` prend une entrée résolue, ou une entrée qui attend son jet quand le maître de jeu nomme l'issue : il la
règle sans jet. L'issue qu'il nomme est l'une de celles de l'activité. Le jet enregistré, s'il y en a un, reste dans le
journal.

### Coûts et effets

La bibliothèque ne débite ni n'applique jamais rien. Une application débite les coûts quand elle est avertie par
`ActivityChosen`, rembourse ce que ses règles remboursent sur `ActivityCancelled`, et applique les effets de l'issue
sur `ActivityConfirmed`, en lisant l'entrée dans le magasin. `Entry::costs()` donne les coûts sur les jours de
l'activité ; `Entry::result()` donne l'issue.

### Concurrence

Chaque changement se fait sur la version lue. `choose()` compte les entrées ouvertes du personnage avant d'en
conserver une nouvelle ; deux choix faits au même moment peuvent passer tous deux le compte, et un magasin qui doit
tenir la borne exactement la vérifie quand il conserve une nouvelle entrée.

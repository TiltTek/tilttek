<!-- phil-dev source-sha256=a5e9205d0f97adb021dd659acdb01156068657ff8af2d57ad4893f9f73ba47b1 -->
# Vie privée

Les réponses des outils de sécurité disent ce qui blesse une personne. La bibliothèque en garde le minimum, pour leur
table seulement, et ne laisse rien de ce qu'elle résume dire qui a donné quelle réponse.

## Ce qui reste anonyme

- L'entente d'une table, son pacte, son décompte de points d'étape et le sommaire de ses étoiles et de ses souhaits
  comptent et trient les réponses ; aucun ne porte la référence d'un participant.
- Les sommaires montrés aux gens — le pacte, le `shown()` de l'entente, les étoiles et les souhaits — ne contiennent
  des réponses que par groupes entiers d'un seuil de participants.
- Un signal anonyme n'a d'auteur nulle part : ni dans le stockage, ni dans ce que l'observateur diffuse.
- Un signal nominatif n'existe qu'à une table qui a choisi des signaux nominatifs ; une réponse à un point d'étape est
  toujours anonyme.

## L'entente et le pacte

L'entente d'une table et son pacte sont montrés à ses participants et à son meneur de jeu. À une table de deux, un
décompte de « sombre » et de « léger » dit à chacun ce que l'autre a choisi ; quand un quatrième participant arrive, le
changement entre deux lectures dit ce qu'il a répondu. Les sommaires montrés aux gens ne contiennent donc des réponses
que par groupes entiers d'un seuil de participants — 3 par défaut, choisi par l'application par l'`answersThreshold` de
`TableSafety`, jamais par une table —, pris dans l'ordre de leur première réponse :

- `Pact` : ses décomptes et ses textes ;
- `TableAgreement::shown()`, un `AgreementSummary`, et `TableAgreement::$respondents`.

Sous le seuil, ils ne contiennent rien et disent seulement qu'il n'y a pas assez de réponses ; au-delà, les réponses
d'un groupe incomplet attendent qu'il se remplisse, pour qu'une réponse de plus n'ajoute jamais les seules réponses
d'une personne.

### Ce qui protège la table applique chaque réponse

Une ligne ou un voile que quelqu'un a posé doit protéger la table même quand peu de gens ont répondu.
`TableAgreement::lines()`, `veils()`, `boundary()`, `consent()`, `checklist()`, `themesAt()` et `preferences()`
appliquent donc chaque réponse, quel que soit le seuil. La sécurité passe d'abord, et elle a un prix :

- À une petite table, ou quand un participant de plus répond, ces méthodes disent les réponses les plus strictes du
  petit nombre qui ont répondu : un meneur de jeu qui voit une nouvelle ligne sur les araignées après la réponse d'Ana
  sait que la ligne est la sienne.
- Une application les garde là où elles protègent : le contenu qu'elle filtre avec `preferences()`, et le meneur de jeu
  qui doit conduire la partie autour d'une ligne. Ce qu'elle montre à chaque participant comme sommaire de la table
  vient de `shown()`.

### Ce que le seuil ne cache pas

- Un participant qui a répondu connaît ses propres réponses et peut les mettre de côté : avec un seuil de 3, chacun des
  trois premiers apprend les réponses des deux autres ensemble, pas laquelle vient de qui.
- Répondre de nouveau place le participant à la fin de l'ordre. Au-delà du seuil, quand le dernier groupe est incomplet,
  une modification peut échanger les réponses d'un participant contre celles d'un autre entre deux lectures ; quitter la
  table ou un effacement aussi. Une modification par un participant compté dans un groupe entier change le sommaire par
  sa seule modification.
- Un texte du pacte peut nommer son auteur ou se reconnaître à son style, quel que soit le nombre de réponses.
- Le nombre de réponses en attente n'est pas montré, mais une application qui montre qui a répondu le dit.

## Le sommaire des étoiles et des souhaits

Les étoiles et les souhaits sont du texte libre : dans un petit groupe, un sommaire d'une ou deux réponses dirait qui les
a écrites. Le sommaire d'une séance ne contient donc des réponses que par groupes entiers d'un seuil de participants — 3
par défaut, choisi par l'application par le `feedbackThreshold` de `TableSafety`, jamais par une table —, pris dans
l'ordre où ils les ont données. Sous le seuil, il ne contient rien et dit seulement qu'il n'y a pas assez de réponses ;
au-delà, les réponses d'un groupe incomplet attendent qu'il se remplisse, pour qu'une réponse de plus n'ajoute jamais
les seules réponses d'une personne.

Ce que le seuil ne cache pas :

- Un texte libre peut nommer son auteur ou se reconnaître à son style, quel que soit le nombre de réponses.
- Un participant qui a répondu connaît ses propres réponses et peut les mettre de côté : avec un seuil de 3, chacun des
  trois premiers apprend les réponses des deux autres ensemble, pas qui a écrit quoi.
- Répondre de nouveau place le participant à la fin de l'ordre. Au-delà du seuil, quand le dernier groupe est incomplet,
  une modification peut échanger les réponses d'un participant contre celles d'un autre entre deux lectures ; un
  effacement aussi. Une table qui ne veut rien de cela lit le sommaire une fois, quand tout le monde a répondu.
- Le nombre de réponses en attente n'est pas montré, mais une application qui montre qui a répondu le dit.

## Ce qui est gardé

| Gardé | Où | Avec la référence du participant |
|---|---|---|
| Lignes et voiles, liste, réponses à la séance zéro | `ParticipationStore` | oui, pour qu'il puisse les changer |
| Étoiles et souhaits de chaque séance | `FeedbackStore` | oui, pour qu'il puisse les changer |
| Signaux | `SignalStore` | seulement pour un signal nominatif |

Un participant qui quitte une table retire ses réponses de celle-ci avec `TableSafety::leave()`. Les signaux servent
pendant la partie : une application les purge après un temps avec `SignalStore::purge()`.

## Export et effacement

`TableSafety::export()` donne tout ce qui est gardé sur un participant : ses réponses à chaque table, les signaux
nominatifs qu'il a levés, et ses étoiles et ses souhaits. `TableSafety::erase()` supprime ses réponses et ses étoiles et
ses souhaits, et retire son nom des signaux qu'il a levés, qui restent à sa table comme signaux anonymes ; l'exécuter de
nouveau ne change rien. `tilttek/table-safety-bundle` branche les deux sur `phil/privacy-bundle`.

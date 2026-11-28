<!-- phil-dev source-sha256=e71e1b0fe972a326799bf6e607052d9e2dc6fbd8ca64572300363b7fc8332be4 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Conserver les entrées | Implémentez `Contract\EntryStore`, et passez-le à `Downtime`. |
| Déclarer les activités de chaque système | Passez `Rules\InMemoryRuleBook`, ou implémentez `Contract\RuleBook`. |
| Dire qui mène une campagne et qui joue un personnage | Implémentez `Contract\Roster`. |
| Lancer les dés des systèmes | Implémentez `Contract\Roller`, ou ne passez rien pour enregistrer les jets faits à la table. |
| Agir sur ce qui s'est passé | Implémentez `Contract\DowntimeObserver`, et passez-le à `Downtime`. |

## Le magasin

`Contract\EntryStore` conserve les entrées, chacune à une version, et les lit dans l'ordre où elles ont été choisies :

- `entry()` en donne une par son identifiant, ou null ;
- `save()` conserve une liste d'entrées à leur version suivante, toutes ou aucune, et les renvoie dans l'ordre donné.
  Une entrée à la version 0 est nouvelle, et le magasin n'en a aucune de cet identifiant ; toute autre n'est conservée
  que si le magasin la détient à cette version, et `DowntimeConflict` est levée sinon, rien n'étant conservé. Une
  entrée donnée deux fois dans un même appel est conservée à chacune de ses versions tour à tour ;
- `entriesOf()` donne le journal d'un personnage dans une campagne, `openOf()` ses entrées ouvertes — en file, en
  attente d'un jet ou résolues —, et `queuedIn()` les entrées en file d'une campagne, celles de chaque personnage :
  toutes dans l'ordre choisi, au plus la limite, et `InvalidDowntime` pour une limite sous 1.

« L'ordre choisi » est l'ordre où les entrées ont d'abord été conservées : un magasin garde une colonne de séquence, ou
un horodatage de création avec l'identifiant pour départager, et lit par un index sur la campagne, le personnage et
l'étape.

`Store\InMemoryEntryStore` les garde en mémoire, tant que l'objet dure : il sert aux tests. Une application les garde
dans sa base de données, dans une transaction par `save()`. Une entrée contient des chaînes, des entiers, une
énumération et son activité, dont les coûts, les issues, les effets et les plages sont des chaînes, des entiers et des
énumérations qui tiennent dans une colonne JSON. Le magasin reconstruit l'activité avec `new Activity()` et l'entrée
avec `new Entry()`, qui les vérifient de nouveau.

## Le livre de règles

`Contract\RuleBook::activity()` donne l'activité d'une clé dans les règles d'un système, ou null ; `activities()`
énumère celles qu'un système déclare, dans son ordre, pour la page où un joueur choisit. `Rules\InMemoryRuleBook`
contient les `SystemRules` qu'une application déclare une fois — depuis sa configuration, ou depuis des fichiers à
elle — et partage entre les requêtes. Une application dont les maîtres de jeu écrivent leurs propres activités
implémente le livre de règles sur sa propre requête.

Le service ne lit le livre de règles que lorsqu'une activité est choisie : l'entrée garde l'activité entière.

## Le registre des rôles

`Contract\Roster::isGameMaster()` dit si un membre mène une campagne, et `plays()` si un membre joue un personnage
dans celle-ci. Le service demande la première avant la seconde, une fois par appel ; elles répondent depuis la
session, de mémoire, ou par une seule lecture indexée.

## Le lanceur

`Contract\Roller::roll()` donne le total d'un lancer d'une expression de dés que déclarent les règles. Le service
l'appelle quand une avance dépense le dernier jour d'une activité à jet, une fois par entrée ; il garde le total dans
±1 000 000. Un lanceur lit les expressions de ses systèmes — avec une bibliothèque de dés au choix de l'application — et
tire d'une source sûre au sens cryptographique. Sans lanceur, l'entrée attend `record()`.

## L'observateur et ses événements

`Contract\DowntimeObserver::notify()` est averti de chaque fait une fois les entrées conservées, par identifiants
seulement :

| Événement | Quand |
|---|---|
| `Event\ActivityChosen` | Un personnage a choisi une activité : débitez ses coûts. |
| `Event\ActivityProgressed` | Une avance a dépensé des jours sur une activité, et il en reste. |
| `Event\ActivityCompleted` | Une avance a dépensé le dernier jour d'une activité, qui attend son jet. |
| `Event\ActivityResolved` | Une activité s'est terminée par son issue, par une avance ou par un jet enregistré. |
| `Event\ActivityConfirmed` | Le maître de jeu a confirmé l'issue : appliquez ses effets. |
| `Event\ActivityCancelled` | Une activité a été annulée : remboursez ce que les règles remboursent. |

Tout événement implémente `Event\DowntimeEvent`, dont `entry`, `campaign` et `character` sont des identifiants. Une
application en tire un message — par Messenger, avec les identifiants seuls — et lit le reste dans le magasin : sur
`ActivityCompleted`, on demande son jet au joueur. Une avance annonce un événement par entrée qu'elle a changée, dans
l'ordre choisi.

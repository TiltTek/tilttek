<!-- phil-dev source-sha256=3a2a69c978c18287bca2a133329ecda5fe0b4751c847b663569f3879d309efcd -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Conserver les listes et leur historique | Implémentez `Contract\RosterStore`, et passez-le à `Factions`. |
| Dater les changements | Passez une `Psr\Clock\ClockInterface` à `Factions`. |
| Agir sur ce qui s'est passé | Implémentez `Contract\RosterObserver`, et passez-le à `Factions`. |
| Utiliser une autre échelle | Construisez une `Scale` avec ses propres bornes, son début et ses paliers ; aucun code n'est nécessaire. |

## Le magasin

`Contract\RosterStore` conserve les listes, chacune à une version, avec leur historique :

- `roster()` donne la liste d'une campagne, ou null ;
- `save()` conserve une liste à la version suivante, avec les entrées de l'historique que le changement produit, en une
  seule écriture — une seule transaction —, et la retourne. Une liste à la version 0 est nouvelle, et le magasin n'en a
  aucune pour sa campagne ; toute autre n'est conservée que si le magasin la tient à cette version, et `RosterConflict`
  est levée sinon, rien n'étant conservé, entrées comprises ;
- `delete()` oublie une liste et tout son historique quand le magasin la tient à sa version, et lève `RosterConflict`
  sinon, rien n'étant oublié ;
- `history()` donne les entrées d'une campagne qui concernent une faction, la plus récente d'abord, au plus la limite,
  et lève `InvalidRoster` pour une limite sous 1. Une entrée concerne sa `faction`, et une `History\RelationEntry`
  aussi son `other` ; un magasin les lit par un index sur la campagne et ces deux clés, la plus récente d'abord. Il
  garde les entrées d'une faction retirée de la liste.

`Store\InMemoryRosterStore` les garde en mémoire, aussi longtemps que l'objet dure : il sert aux tests. Une application
les garde dans sa base de données :

- une liste contient des chaînes, un entier, son échelle — trois entiers et une liste de paliers faits d'une clé, d'un
  libellé et d'un entier —, ses factions et ses relations, et tient dans une colonne JSON, ou dans des lignes à part
  gardées dans l'ordre donné ;
- une faction contient des chaînes, un booléen, une liste de rangs faits d'une clé et d'un titre, des positions faites
  d'une clé de sujet et d'un entier, et des appartenances faites de deux chaînes ;
- une relation contient deux clés, une énumération, une note et un booléen ;
- une entrée contient des chaînes, des entiers ou des relations qui peuvent être nulles, une raison faite d'un texte
  et d'un identifiant de session qui peut être nul, et une date.

Le magasin reconstruit chaque valeur avec son constructeur — `Subject::fromKey()` pour un sujet —, qui la vérifie de
nouveau.

## L'horloge

Le service lit `ClockInterface::now()` une fois pour chaque entrée de l'historique qu'il produit. Un test passe une
horloge qu'il règle, comme la `MockClock` de `symfony/clock`.

## L'observateur et ses événements

`Contract\RosterObserver::notify()` est informé de chaque fait une fois la liste conservée, par identifiants seulement :

| Événement | Quand | Porte |
|---|---|---|
| `Event\RosterCreated` | Une liste a été créée. | |
| `Event\FactionAdded` | Une faction a été ajoutée. | la clé de la faction |
| `Event\FactionChanged` | Son nom, son résumé, ses rangs ou son secret ont changé. | la clé de la faction |
| `Event\FactionRemoved` | Une faction a été retirée, avec ses relations. | la clé de la faction |
| `Event\ScaleChanged` | L'échelle a changé. | |
| `Event\StandingChanged` | Une position a bougé. | la clé de la faction, la clé du sujet |
| `Event\RankChanged` | Un personnage est entré, a monté, a descendu ou est parti. | la clé de la faction, le personnage |
| `Event\RelationChanged` | Une relation a été fixée, changée ou terminée. | la paire, la clé la plus basse d'abord |
| `Event\RosterHandedOver` | Elle a été cédée à un autre maître de jeu. | le nouveau maître de jeu |
| `Event\RosterDeleted` | Elle a été supprimée, avec son historique. | |

Chaque événement implémente `Event\RosterEvent`, dont la `campaign` est un identifiant. Une application envoie un message
à partir d'eux — par Messenger, avec les identifiants seuls — et lit le reste dans le magasin : sur `StandingChanged`,
les pages des joueurs rechargent leurs positions, et un message ne contient jamais une raison, une note ou un résumé.

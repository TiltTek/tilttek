<!-- phil-dev source-sha256=063e033b52bb185aa0592b7951090e2d7bbba1123f8d89256f4911c9a67e6d19 -->
# Factions et positions

## La liste

Une `Roster` contient les factions d'une campagne, par son identifiant, et l'identifiant de son maître de jeu. Elle
contient la `Scale` que ses positions utilisent, jusqu'à 64 factions dans l'ordre donné, et jusqu'à 512 relations entre
elles. Elle est vérifiée d'un bloc à chaque construction : chaque clé de faction une seule fois, chaque position sur
l'échelle, chaque relation entre deux factions de la liste, chaque paire une seule fois. Chaque changement retourne une
nouvelle valeur ; le magasin augmente sa version à chaque écriture.

Les identifiants — d'une campagne, d'un membre, d'un personnage, d'une session — comptent de 1 à 128 lettres, chiffres,
`_`, `.`, `:` ou `-`, et commencent par une lettre : ils sont opaques, jamais un nom ni une adresse. Les clés — d'une
faction, d'un rang, d'un palier — suivent la même forme avec au plus 64 caractères, et sont uniques dans leur liste ou
leur faction.

## L'échelle et ses paliers

Une `Scale` va d'un nombre entier le plus bas à un nombre entier le plus haut, tous deux entre -100 et 100, et a un
début : l'endroit où se tient un sujet quand aucun changement ne l'a encore touché. Ses 1 à 12 `Tier` la découpent à
partir du plus bas : le premier commence à la position la plus basse, chaque suivant plus haut, aucun au-delà du plus
haut. Un palier contient chaque position depuis son propre début jusqu'à celui du palier suivant.

`Scale::standard()` va de -3 à 3 et commence à 0 : hostile à -3, peu amical à partir de -2, neutre à 0, amical à partir
de 1, allié à 3. Une campagne qui compte la réputation en centaines de points, ou en cinq paliers nommés, construit la
sienne :

```php
new Scale(0, 100, 20, [
    new Tier('unknown', 'Unknown', 0),
    new Tier('known', 'Known', 20),
    new Tier('trusted', 'Trusted', 60),
    new Tier('honoured', 'Honoured', 90),
]);
```

`Scale::tier()` donne le palier d'une position, `contains()` dit si une position est sur l'échelle, et `clamp()` en
ramène une à l'intérieur. `Factions::changeScale()` ne change l'échelle d'une liste que si chaque position fixée tient
dans la nouvelle : le maître de jeu déplace d'abord les autres, chacune avec sa raison.

## Factions

Une `Faction` se trouve par sa clé et contient :

- un nom d'une ligne, jusqu'à 120 caractères, et un résumé d'au plus 4 000 caractères, possiblement vide ;
- jusqu'à 16 `Rank`, du plus bas au plus haut, chacun avec une clé et un titre ;
- jusqu'à 64 `Standing`, chaque sujet une seule fois ;
- jusqu'à 64 `Membership`, chaque personnage une seule fois, chacun tenant un rang de la faction ;
- si elle est secrète.

Les textes sont de l'UTF-8 valide, rognés, et ne contiennent aucun caractère qui inverse le sens d'un texte ; une ligne
ne contient aucun caractère de contrôle, un résumé peut contenir des sauts de ligne et des tabulations.

`Factions::addFaction()` ajoute une faction après les autres ; elle arrive sans positions ni membres, que seuls les
appels qui prennent une raison fixent. `changeFaction()` change le nom, le résumé, les rangs et le secret d'une faction,
et laisse ses positions et ses membres tels qu'ils sont conservés ; un changement qui retire un rang tenu par un membre
est refusé. `removeFaction()` retire une faction et ses relations ; son historique reste dans le magasin jusqu'à la
suppression de la liste.

## Positions

Un `Subject` est le groupe dans son ensemble, `Subject::party()`, ou un personnage, `Subject::character('char-1')`. Sa
clé, `party` ou `character:` suivi de l'identifiant, est ce qu'un magasin conserve et ce que portent les événements.

`Roster::standingOf()` donne où se tient un sujet auprès d'une faction : la position fixée, ou le début de l'échelle.

- `Factions::shift()` déplace une position de 1 à 200 pas, vers le haut si le nombre est positif, vers le bas s'il est
  négatif. Un déplacement au-delà d'une extrémité de l'échelle s'y arrête.
- `Factions::setStanding()` fixe une position d'emblée ; une position hors de l'échelle est refusée.

Un décalage ou une fixation qui laisse la position où elle était ne conserve rien, n'enregistre rien et n'annonce rien.

## Rangs

`Factions::setRank()` donne à un personnage un rang de la faction, par sa clé : il entre quand il n'en tenait aucun,
monte ou descend quand il en tenait un autre. Un rang nul le sort de la faction. `Faction::outranks()` dit si un rang
est au-dessus d'un autre, d'après leur ordre dans la faction.

## Relations

Une `Relation` dit comment deux factions se tiennent l'une envers l'autre, la même dans les deux sens, avec une
`Stance` — alliée, amicale, neutre, rivale, hostile ou en guerre (`war`) —, une note d'au plus 200 caractères,
possiblement vide, et si elle est secrète. La paire est conservée avec la clé la plus basse d'abord, quel que soit
l'ordre dans lequel elle a été donnée ; `Roster::relation()` la trouve dans l'un ou l'autre ordre.

`Factions::relate()` fixe la relation d'une paire à la place de celle qu'elle avait ; `endRelation()` la termine. Une
faction n'a aucune relation avec elle-même.

## Raisons et historique

Une `Reason` contient les mots du maître de jeu, une ligne de 1 à 500 caractères, et l'identifiant de la session quand
elle est nommée. Chaque appel qui déplace une position, un rang ou une relation en prend une, et le magasin conserve une
entrée de l'historique dans la même écriture que la liste :

| Entrée | Contient |
|---|---|
| `History\StandingEntry` | Le sujet, la position avant (le début de l'échelle quand aucune n'était fixée) et après. |
| `History\RankEntry` | Le personnage, le rang avant et après, nul quand il n'était pas membre ou qu'il est parti. |
| `History\RelationEntry` | La paire, la relation avant et après, nulle quand il n'y en avait pas ou qu'elle a pris fin. |

Chaque entrée contient aussi la campagne, la faction, la raison, le membre qui a fait le changement et le moment, tiré de
l'horloge. `Factions::history()` donne les entrées qui concernent une faction, la plus récente d'abord, jusqu'à 500 à la
fois et 100 par défaut ; l'entrée d'une relation concerne ses deux factions. Les changements d'échelle, de noms, de rangs
offerts et de secret sont annoncés à l'observateur et ne laissent aucune entrée.

`Factions::create()` prend une liste avec les positions, les membres et les relations dont la campagne part : ils sont
son point de départ, et ne laissent aucun historique.

## Secrets et vues

`Factions::view()` montre la liste à son maître de jeu : chaque faction, chacune avec la position du groupe d'abord —
fixée ou au début de l'échelle —, puis chaque position fixée avec un personnage, chaque membre et chaque relation.

`Factions::playerView()` montre ce que les joueurs savent :

- les factions qui ne sont pas secrètes, dans l'ordre donné ;
- pour chacune, la position du groupe et, quand un personnage est nommé, la position et le rang de ce personnage —
  jamais ceux d'un autre personnage ;
- les relations qui ne sont pas secrètes, entre deux factions montrées.

Le service ne sait pas qui joue dans la campagne : l'application vérifie que le lecteur est l'un de ses joueurs, et
nomme son personnage. Les deux vues donnent `toArray()` pour une page, avec la valeur de chaque position, la clé et le
libellé de son palier ; une page qui cache les nombres n'affiche que les libellés.

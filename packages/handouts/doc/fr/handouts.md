<!-- phil-dev source-sha256=c85922b3328142d621d906c6430ae9ed0c2fe5fd9b984fc4197249a2c9de0ea8 -->
# Documents

Un document est une pièce que le meneur de jeu prépare pour les joueurs d'une table. La table est ce dans quoi
l'application joue — une campagne, une séance, une partie —, nommée par sa référence ; la bibliothèque n'en sait rien
d'autre, pas même qui y est assis.

## Créer un document

```php
$handout = $handouts->create(
    ResourceReference::of('handout', '4'),
    ResourceReference::of('campaign', '3'),
    'The miller\'s letter',
    "Meet me at the mill at midnight.\nCome alone.",
    [new Attachment(ResourceReference::of('file', '9'), 'image/jpeg', 'The letter, stained with flour')],
    $gm,                                                  // the acting reference, for the events
);
```

Un document contient :

| Partie | Bornes |
|---|---|
| Son titre | 1 à 150 caractères UTF-8, sans caractère de contrôle ni espace en début ou en fin |
| Son texte, ou aucun | 1 à 20 000 caractères UTF-8, non vide, les sauts de ligne et les tabulations étant les seuls caractères de contrôle |
| Ses pièces jointes | Des fichiers distincts, au plus `Limits::$attachmentsPerHandout` — 10 par défaut, 50 au plus |

Il contient un texte, une pièce jointe, ou les deux. Un nouveau document est à la révision 1 et caché de tous les
joueurs. Une référence ne se crée qu'une fois (`Refusal::AlreadyCreated`), et une table contient au plus
`Limits::$handoutsPerTable` documents — 200 par défaut (`Refusal::TooManyHandouts`).

Le texte est du texte brut : l'application l'affiche comme du texte, ou le lit comme la source de son propre format, et
l'échappe là où elle le montre.

## Pièces jointes et types de média

Une `Attachment` nomme un fichier du stockage de l'application par sa référence, avec son type de média — `type/soustype`
en minuscules, sans paramètres — et une description de 1 à 500 caractères, qui le remplace là où on ne peut ni le voir
ni l'entendre : un lecteur d'écran, une feuille imprimée, une connexion lente.

La bibliothèque ne lit, ne stocke ni ne sert jamais le fichier. L'application vérifie le vrai type du fichier quand elle
le stocke, et ne le sert qu'à un joueur pour qui `canSee()` est vrai.

Les pièces jointes d'un document doivent avoir un type de média que l'application autorise
(`Refusal::MediaTypeNotAllowed`). `MediaTypes` les contient ; par défaut :

| Genre | Types de média |
|---|---|
| Images | `image/png`, `image/jpeg`, `image/webp`, `image/avif`, `image/gif` |
| Documents | `application/pdf`, `text/plain` |
| Sons | `audio/mpeg`, `audio/ogg` |
| Vidéos | `video/mp4`, `video/webm` |

Le SVG est exclu : un fichier SVG peut contenir des scripts. Une application qui assainit ses fichiers SVG avant de les
stocker ajoute `image/svg+xml` à sa propre liste :

```php
$mediaTypes = new MediaTypes([...MediaTypes::DEFAULTS, 'image/svg+xml']);
```

## Modifier un document

```php
$handouts->edit($handout, 'The miller\'s letter, torn', 'Meet me at the mill.', [], $gm);
```

Une modification remplace le titre, le texte et les pièces jointes, et augmente la révision. Les joueurs à qui le
document est révélé voient aussitôt la nouvelle révision ; ceux qui en ont ouvert une antérieure la trouvent périmée
dans leur vue, comme l'explique [ce que les joueurs ont vu](sightings.md). Les mêmes bornes et les mêmes types de média
s'appliquent.

## Supprimer un document

`remove()` supprime le document avec ses divulgations, ses révélations planifiées et ses vues. Les fichiers restent dans
le stockage de l'application, qui les supprime si aucun autre document ne les référence.

## Lire

`handout()` lit un document, ou lève `UnknownHandout`. `handouts($table)` lit tous les documents d'une table, révélés ou
non, le plus ancien créé d'abord : la vue du meneur de jeu.

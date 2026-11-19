<!-- phil-dev source-sha256=f03d538ef2f380ee66fd92fe5a7ea9a11696d2625d3a513594a44a13095197d6 -->
# Écrans

## Routes

| Route | Chemin | Méthode | Ce qu'elle fait |
|---|---|---|---|
| `tilttek_advent_calendar_screen` | `/advent-calendar/{calendar}` | GET | Chaque case telle que le spectateur la voit. |
| `tilttek_advent_calendar_open` | `/advent-calendar/{calendar}/boxes/{day}` | POST | Ouvre une case pour le membre courant. |

Un calendrier inconnu ou non publié répond 404, tout comme un jour au-delà de sa dernière case. Ouvrir une case exige un
membre et un jeton CSRF valide, sinon elle répond 403. Une case qui ne peut pas être ouverte maintenant — avant son jour,
ou après sa fenêtre — répond 409 avec la raison. Chaque réponse est privée et jamais mise en cache.

## L'écran

L'écran montre le titre du calendrier, une phrase sur sa politique d'ouverture, et chaque case sous forme de carte du
kit : son jour et sa date, et selon son état —

- **verrouillée** : quand elle s'ouvre ;
- **ouvrable** : pour un membre, un formulaire simple dont le bouton l'ouvre ; pour un visiteur, qu'elle est prête ;
- **ouverte** : sa surprise ;
- **manquée** : jusqu'à quand elle pouvait être ouverte.

Un visiteur voit les cases dans leur état pour quelqu'un qui n'en a ouvert aucune, et une phrase qui l'invite à se
connecter. Une case non ouverte ne porte jamais sa surprise, ni dans le HTML ni ailleurs.

Sans JavaScript, le formulaire d'une case ouvrable est envoyé tel quel : la case s'ouvre et l'écran revient à cette
case, `303 See Other`. Une ouverture refusée montre de nouveau l'écran avec une alerte qui dit pourquoi, statut 409.

## Le contrôleur Stimulus

`tilttek--advent-calendar--boxes` envoie en arrière-plan le formulaire d'une case ouvrable avec
`Accept: application/json`. Le serveur répond le jour, si cette requête a ouvert la case en premier, s'il faut célébrer,
et le HTML de la case ouverte. Le contrôleur met cette case à la place de la case fermée, déplace le focus sur son
titre et dit dans une région vivante polie qu'elle a été ouverte. Une ouverture refusée ou échouée est dite dans la même
région ; une réponse qui ne tient pas ensemble revient à l'envoi du formulaire. Le contrôleur n'anime rien lui-même.

## La célébration

Avec `celebration.enabled`, l'écran contient la `Celebration` du kit, et une première ouverture la joue :

- sans JavaScript, l'écran auquel revient le formulaire la joue aussitôt, avec le message « Box 3 opened! » ;
- avec le contrôleur, l'événement `celebrate` est distribué sur la fenêtre, et la `Celebration` le joue.

Une case rouverte n'est jamais célébrée : le bundle ne célèbre que les ouvertures que `BoxOpened` a signalées pendant la
requête, et les oublie après chaque requête. Là où le mouvement est réduit, par le système ou par `data-motion="reduce"`
sur un ancêtre, la `Celebration` ne dessine rien et dit seulement son message. L'animation dure quelques secondes au
plus, s'interrompt quand l'onglet est caché, et ne fait aucun son.

## Gabarits

| Gabarit | Ce qu'il dessine |
|---|---|
| `@TilttekAdventCalendar/calendar.html.twig` | L'écran : le titre, la politique, l'alerte d'un refus, les cases, la région vivante et la célébration. |
| `@TilttekAdventCalendar/_box.html.twig` | Une case telle que le spectateur la voit. |
| `@TilttekAdventCalendar/_surprise.html.twig` | La surprise d'une case ouverte, selon son genre. |

Une application en remplace un sous `templates/bundles/TilttekAdventCalendarBundle/` — `_surprise.html.twig` pour montrer
ses propres noms pour les badges et les contenus auxquels ses surprises renvoient.

La fonction `advent_calendar_kit('Card:Title')` nomme un composant du kit tel que l'application l'a installé, dans le
sous-répertoire de sa configuration `ux_toolkit`.

## Accessibilité

Les cases forment une liste ordonnée étiquetée par le titre, chaque case étant un `article` étiqueté par son propre
titre, avec sa date dans un élément `time`. Chaque bouton dit quelle case il ouvre. Le focus se déplace sur la case
ouverte, et chaque ouverture ou refus est dit dans une région vivante polie. Une case verrouillée ou manquée dit quand
elle s'ouvre ou jusqu'à quand elle pouvait être ouverte, avec des mots, jamais par la seule couleur.

## Traductions

Les textes sont dans le domaine `tilttek_advent_calendar`, en anglais et en français, avec des messages ICU : les dates
s'écrivent dans la langue du lecteur.

<!-- phil-dev source-sha256=11f45af85c47ea51dff01619fde687e13669a2bad080864e18da9fa6efb85ab0 -->
# Écrans

Les routes de `config/routes.php` :

| Route | Chemin | Qui |
|---|---|---|
| `tilttek_escape_room_console` | `GET /escape-room/console/{game}` | L'animateur |
| `tilttek_escape_room_console_act` | `POST /escape-room/console/{game}/{action}` | L'animateur |
| `tilttek_escape_room_screen` | `GET /escape-room/screen/{game}` | Qui regarde la partie |
| `tilttek_escape_room_screen_state` | `GET /escape-room/screen/{game}/state` | Qui regarde la partie |
| `tilttek_escape_room_consent` | `POST /escape-room/games/{game}/consent` | L'équipe, ou l'animateur |
| `tilttek_escape_room_leaderboard` | `GET /escape-room/leaderboard/{room}` | Tout le monde |

`EscapeRoomAccess` dit qui est qui ; un spectateur qu'il refuse reçoit un 403, une partie inconnue un 404. Chaque
réponse sauf celle du classement est privée et jamais stockée par un cache ; le classement est public pendant une minute.
Chaque formulaire porte un jeton CSRF — `tilttek_escape_room_console` ou `tilttek_escape_room_consent` — et est refusé
sans lui.

## La console

La page de l'animateur montre la minuterie et ce que montre l'écran d'indices, les totaux — temps joué, pénalités,
indices et sauts —, et chaque énigme avec son statut et ses indices révélés. Pour une énigme ouverte, un bouton envoie
son prochain indice et dit combien il en reste, un autre la saute ; un champ envoie à l'écran d'indices un message d'au
plus 280 caractères ; des boutons effacent l'écran, mettent la minuterie en pause ou la reprennent, et mettent fin à la
partie.

Les actions sont `hint` et `skip`, avec le champ `puzzle` ; `message`, avec le champ `message` ; `pause`, `resume`,
`clear` et `abandon`. Sans script, un formulaire reçoit pour réponse une redirection vers la console, avec la raison d'un
refus montrée dans une alerte. Avec `Accept: application/json`, la réponse est `{"action": "hint", "game": {…}}`, ou
`{"error": "…"}` avec le statut 422.

Le contrôleur Stimulus `tilttek--escape-room--console` envoie en arrière-plan les formulaires marqués
`data-action="tilttek--escape-room--console#send"`, dit ce qui a été fait ou pourquoi c'était refusé dans une région
vive polie, et relit la console sur place ; il fait courir la minuterie entre deux lectures, et relit la console quand le
hub l'informe que la partie a avancé — un indice demandé par les joueurs — ou toutes les quelques secondes sans hub.

## L'écran d'indices

La page montrée dans la salle : le titre de la salle, la minuterie, l'indice envoyé — son énigme, son texte, la pénalité
qu'il a ajoutée — ou le message de l'animateur, l'issue une fois la partie terminée, et la célébration d'une évasion. Un
spectateur qui parle pour l'équipe voit aussi si son résultat est sur le classement public, et un bouton pour le
changer.

Le contrôleur Stimulus `tilttek--escape-room--screen` lit l'état — `GET …/state`, le même sans la mise en page — quand
le hub l'informe que la partie a avancé, ou toutes les quelques secondes sans hub, et ne remplace la copie de la page
que lorsque sa version a changé. Il annonce chaque indice ou message comme une phrase entière et le temps restant à des
repères choisis dans une région vive polie, et envoie sur son élément :

| Événement | Quand | `detail` |
|---|---|---|
| `tilttek-escape-room:moved` | Un nouvel état est montré | aucun |
| `tilttek-escape-room:finished` | La partie a pris fin | `outcome` : `escaped`, `time-up` ou `abandoned` |

La célébration est la recette `Celebration` du kit PHIL UX, avec `autoplay` : elle se joue une fois quand le nouvel état
d'une évasion est montré, son message annoncé dans une région vive polie, des confettis ou des feux d'artifice dessinés
seulement là où le mouvement n'est pas réduit. Une page de l'application peut jouer la sienne sur
`tilttek-escape-room:finished`, ou envoyer l'événement `celebrate` du kit à une `Celebration` qui lui est propre :

```js
document.addEventListener('tilttek-escape-room:finished', (event) => {
    if ('escaped' === event.detail.outcome) {
        document.querySelector('#exit-celebration')?.dispatchEvent(new CustomEvent('celebrate'));
    }
});
```

## Le classement

Un tableau avec sa légende et ses en-têtes — place, équipe, temps final, indices —, lisible sans script. Une équipe est
nommée par le nom que donne `TeamNames`, ou par sa place ; jamais par un membre.

## Les gabarits

| Gabarit | Ce qu'il dessine |
|---|---|
| `@TilttekEscapeRoom/console.html.twig` | La console |
| `@TilttekEscapeRoom/screen.html.twig` | L'écran d'indices |
| `@TilttekEscapeRoom/_screen.html.twig` | L'état d'une partie : la minuterie, l'indice ou le message, l'issue, la célébration ; `celebrate: false` ne dessine aucune célébration |
| `@TilttekEscapeRoom/leaderboard.html.twig` | Le classement public |

Chacun étend le `layout` de la configuration. Les fonctions Twig sont `tilttek_escape_room_kit('Card:Title')`, le nom
d'une recette telle que l'application l'a installée ; `tilttek_escape_room_domain()`, le domaine de traduction ; et
`tilttek_escape_room_clock(seconds)`, une durée sous la forme `42:07` ou `1:02:07`. Les textes sont en anglais et en
français, sous le domaine de traduction de la configuration.

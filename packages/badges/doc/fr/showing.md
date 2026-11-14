<!-- phil-dev source-sha256=b7a9d3ec43b36d13c9c19d188f20957c59b4ddcd0075d631e3ccc2712c5ec6fd -->
# Ce que montre un membre

**Un badge gagné reste privé tant que le membre ne le montre pas.** `Badges` garde les choix de chaque membre dans un
`Profile` :

| Méthode | Ce qu'elle fait |
|---|---|
| `show($member, $code)` | Rend public un badge gagné |
| `hide($member, $code)` | Le rend de nouveau privé, hors de la vitrine et retiré comme titre |
| `feature($member, [$codes])` | Remplace la vitrine par ces badges gagnés, dans cet ordre, chacun rendu public ; au plus la taille de la vitrine, six par défaut |
| `entitle($member, $code)` | Porte un badge gagné comme titre à côté du nom du membre, rendu public ; `null` pour aucun |
| `profile($member)` | Ce que le membre a choisi |
| `showcase($member)` | Ce que tout le monde peut voir : le titre, la vitrine et chaque badge montré, sans ceux que le membre ne détient plus |

Montrer, mettre en vitrine ou porter un badge que le membre n'a pas gagné lève `NotEarned`.

## Partage

`Badges::share($member, $code, $channel)` partage l'attribution du numéro le plus élevé d'un badge gagné — la dernière
gagnée, même quand une activité faite hors ligne et enregistrée plus tard porte une date antérieure — par un
`ShareChannel` — une image, un lien, une publication sur le Fédivers — et renvoie ce que le canal en a fait. La
bibliothèque ne partage jamais d'elle-même : l'application l'appelle quand le membre le demande.

## Une autre application

Un membre permet à une autre application de lire ses badges avec `Badges::grant($member, 'portfolio')` et retire son
consentement avec `withdraw()` ; `clients()` énumère celles qu'il a autorisées. `Badges::readFor($member, 'portfolio')`
donne ses attributions à l'application, ou lève `AccessDenied` quand il ne l'a pas autorisée. L'authentification de
l'application revient à l'application : la bibliothèque ne garde que le consentement du membre.

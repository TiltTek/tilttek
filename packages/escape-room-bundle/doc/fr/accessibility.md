<!-- phil-dev source-sha256=eb72ab1cec352f4c2dc46e3cebc9937ac827a1555acd565c36fe7a9ea09ba6c5 -->
# Accessibilité

Les écrans suivent l'accessibilité de `tilttek/escape-room`.

- **La minuterie** est du texte dans un élément `<time>`, jamais un cadran seul. Elle est annoncée dans une région vive
  polie à des repères choisis — toutes les dix minutes, puis chaque minute des cinq dernières —, non à chaque seconde.
  Une pause est dite en mots, « Paused by the host », et la fin dans une phrase avec le temps final et les indices.
- **Un indice ou un message** reste affiché jusqu'à ce que l'animateur l'efface, de sorte que chaque joueur le lit à son
  rythme, et il est annoncé une fois dans une région vive polie comme une phrase entière, avec la pénalité qu'un indice
  a ajoutée.
- **La console** est une liste de formulaires dont les boutons disent ce qu'ils font et pour quelle énigme — « Push a
  hint for The candle (1 left) » — ; elle fonctionne sans script. Ce qu'un bouton a fait, ou pourquoi il a été refusé,
  est annoncé dans une région vive polie, et le focus reste sur le formulaire envoyé.
- **La célébration** d'une évasion est la `Celebration` du kit PHIL UX : son message annoncé dans une région vive polie,
  des confettis ou des feux d'artifice dessinés seulement là où le mouvement n'est pas réduit, aucun son.
- **Le classement** est un tableau avec une légende et des en-têtes, les indices à côté du temps.
- **Le consentement** est un simple formulaire qui dit où en est le résultat de l'équipe avant que le bouton le change.

Les couleurs, les tailles et le contraste sont ceux des recettes du kit ; une feuille de style de l'application qui
agrandit l'indice pour un écran au fond de la salle garde un rapport de contraste d'au moins 4,5:1.

<!-- phil-dev source-sha256=eb8b7836b059ced872a7328f66a4b686a78c46e3ea5c0e05cd38e25ca3c0b5ea -->
# Accessibilité

La bibliothèque ne dessine rien ; les pages qui montrent une partie — l'écran de l'équipe, l'écran d'indices, le
classement — la gardent utilisable par tous.

## La minuterie

- Le temps restant est du texte, « Il reste 42 minutes 10 secondes », dans un élément `<time>`, jamais un cadran seul.
- Il n'est pas annoncé chaque seconde : une région dynamique polie dit le temps restant à des repères choisis — toutes
  les dix minutes, puis chaque minute des cinq dernières —, et le lecteur d'écran d'un joueur qui le demande le lit
  aussitôt.
- Rien ne clignote, et aucune couleur seule ne dit que le temps presse ; les dernières minutes sont dites aussi en
  mots. Sous `prefers-reduced-motion: reduce`, la minuterie change sans animation.
- Une pause se dit en mots, « En pause par l'animateur », et pas seulement par une horloge figée.

## L'écran d'indices

- Un indice envoyé par l'animateur, ou un message, est annoncé dans une région dynamique polie, en une phrase entière,
  avec sa pénalité quand il en a une : « Indice pour la chandelle : regardez le dessus. Deux minutes ajoutées. »
- Le texte est grand, avec un rapport de contraste de 4,5:1 au moins, et reste à l'écran jusqu'à ce que l'animateur
  l'efface, afin que chaque joueur le lise à son rythme.
- Un son qui annonce un indice ne joue que si la salle le choisit, et le texte dit la même chose.

## L'écran de l'équipe

- Demander un indice est un bouton qui a son propre libellé — « Demander un indice pour la chandelle, deux minutes
  ajoutées » —, si bien que le coût est connu avant l'appui.
- Le champ de réponse et le résultat d'une proposition suivent les règles de `tilttek/puzzle-builder`.

## Le classement

- Le classement est un tableau avec une légende et des en-têtes — position, équipe, temps final, indices —, lisible sans
  script ; les indices sont à côté du temps, pas cachés dans une infobulle.
- Une évasion est célébrée avec le composant de célébration de PHIL UX, qui respecte `prefers-reduced-motion`.

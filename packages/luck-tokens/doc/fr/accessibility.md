<!-- phil-dev source-sha256=fbb490a2d4be54b5de00f6dca6a050917abc532c9d754d332747ee2901fbd647 -->
# Accessibilité

La bibliothèque ne dessine rien ; la page qui affiche les jetons les garde utilisables par tous.

## Les soldes

- Chaque solde est un nombre en texte avec le nom de sa sorte, « Inspiration : 1 sur 1 », jamais une rangée d'icônes
  seules ; les icônes, s'il y en a, sont cachées aux lecteurs d'écran.
- Le plafond est dit avec le solde, pour qu'un joueur sache quand un octroi le dépasserait.
- Le registre est un tableau avec une légende et des en-têtes — quand, qui, quoi, combien, solde, pourquoi —, dans
  l'ordre de ses positions, lisible sans script.

## Les changements

- Un octroi, une dépense ou un transfert est annoncé dans une région vivante polie, en une phrase complète : « Ana a
  dépensé 1 point de destin ; il en reste 2. » Les événements portent ce que la phrase demande : l'entrée, son
  détenteur, son changement et son solde.
- Un refus est dit en mots près du contrôle qui l'a causé, à partir du message de `TokenRefused`, et le focus reste sur
  ce contrôle.
- Rien ne bouge sous `prefers-reduced-motion: reduce`, et aucun son ne joue à moins que le lecteur ne le demande.

## Les contrôles

- Octroyer, dépenser et passer sont des boutons avec leur propre libellé — « Dépenser 1 inspiration » —, d'au moins
  24 sur 24 pixels.
- L'identifiant de chaque opération est dessiné avec le formulaire, pour qu'un second appui sur le bouton soit une
  reprise, jamais une seconde dépense.

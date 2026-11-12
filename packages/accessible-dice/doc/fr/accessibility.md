<!-- phil-dev source-sha256=3b6a93c1dc545f39678fe1f95c7c8821a0aa9c77cb4daf0e985a2b0373374343 -->
# Accessibilité

Ce que le formulaire fait, par défaut, pour chaque façon de l'utiliser.

## Lecteurs d'écran

- Chaque dé est un choix de bouton radio dans un ensemble de champs avec une légende, lu avec son libellé : « 6-sided
  die », ou le nom d'un dé personnalisé.
- Chaque face d'un dé personnalisé est décrite par des mots — « Heads: a circle, worth 1 » — et la liste des faces est
  la description du choix. Une face n'est jamais dite par sa couleur seule : `tilttek/dice` refuse une face qui a une
  couleur sans forme ni image, et la description dit la forme.
- Le nombre de dés et le modificateur sont des champs numériques étiquetés ; les boutons de chaque côté du nombre ont
  leur propre libellé, « One fewer » et « One more », et nomment le champ qu'ils changent.
- L'expression que fait le choix est un `output` lié à ses champs.
- Un résultat est annoncé dans une région `status`, `aria-live="polite"` et atomique : après ce que le lecteur d'écran
  est en train de dire, en une phrase entière. La région est vidée, puis écrite, afin que deux résultats égaux soient
  tous deux annoncés. Le jet dont le formulaire a été répondu est annoncé au chargement de la page, et chaque jet d'une
  tour de dés de la page à mesure qu'il tombe. `politeness: assertive` annonce tout de suite.

## Claviers et pointeurs

- Tout est un contrôle natif, dans l'ordre où on le lit : les dés par les flèches d'un groupe de boutons radio, les
  champs, les boutons, la case et le lancer.
- Chaque cible fait au moins 48 sur 48 pixels, au-delà des 24 pixels de WCAG 2.2 et des 44 de son niveau amélioré ; le
  focus est souligné d'un contour.

## Mouvement et son

- Le lancer ne bouge que sous `prefers-reduced-motion: no-preference` ; pour un spectateur qui a demandé un mouvement
  réduit, rien ne bouge.
- Rien n'est lu à voix haute par défaut. Une case, décochée, permet au spectateur d'entendre chaque résultat par la
  synthèse vocale du navigateur, dans la langue de la page.

## Sans son script

Le formulaire envoie le choix, et le serveur construit et lance son expression : il lance de la même façon sans son
script. Le résultat dont il est répondu est dans la région dynamique de la page.

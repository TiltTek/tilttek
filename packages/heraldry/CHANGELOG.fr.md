<!-- phil-dev source-sha256=3e3f72aefdac966b18fab4e0236f206c1c79872365475e6a68013db677bcd103 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/heraldry` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-20

### Corrigé

- Le préfixe des identifiants qu'une image dérive quand aucun n'est donné prend le titre et sa langue en plus des
  armoiries et de la forme : les mêmes armoiries dessinées sur une page pour deux propriétaires, sous leurs propres
  titres, ne partagent plus leurs identifiants ni les titres vers lesquels pointe leur `aria-labelledby`. Une image
  dessinée sans titre garde le préfixe qu'elle avait.

## 0.1.0-alpha - 2026-11-13

### Ajouté

- Le modèle des armoiries : `Coat`, un `Field` plein ou divisé par une `Partition` — parti, coupé, tranché, taillé,
  écartelé, écartelé en sautoir —, les neuf `Tincture` et leur `TinctureKind`, une `Ordinary` dans son émail sous forme
  de `Piece` — chef, fasce, pal, bande, barre, chevron, croix, sautoir, bordure —, un `ChargeGroup` de un à trois
  `Charge` — étoile, croissant, besant ou tourteau, annelet, losange, macle, billette, fleur de lis — sur le champ ou
  sur la pièce, avec un `Arrangement`, et une `Brisure` — lambel ou cotice ; chaque pièce dit combien de meubles elle
  porte et entoure, et `InvalidCoat` refuse ce que le modèle ne peut pas contenir.
- `TinctureRule` et `Violation` : ni métal sur métal ni couleur sur couleur, les fourrures sur l'un ou l'autre, rien sur
  son propre émail ; appliquée sur un champ plein et sur la pièce, pas sur un champ divisé ni pour une brisure ; chaque
  faute en une phrase.
- `Generator\CoatGenerator` et `Generator\Seed` : des armoiries tirées de 32 octets de la source sûre du système,
  écrites en 64 chiffres hexadécimaux, qui amorcent `Xoshiro256**` avec leur SHA-256 pour que le même germe donne les
  mêmes armoiries partout ; chaque écu respecte la règle des émaux, et ce qui se trouve côte à côte ne partage jamais un
  émail ; les fourrures peuvent être laissées de côté.
- `Blazon\Blazonry`, `Blazon\EnglishBlazon` et `Blazon\FrenchBlazon` : le blasonnement en anglais — l'émail en dernier,
  Or avec une majuscule, un émail partagé écrit une seule fois, « of the field », les besants et les tourteaux nommés
  par leur émail — et en français — articles contractés, « de » élidé, participes accordés avec la pièce et les meubles,
  « du champ », « du même », besants et tourteaux, « plein », « brochant sur le tout » ; `Contract\BlazonWriter` pour
  remplacer l'un ou l'autre.
- `Svg\SvgRenderer` et `Svg\Palette` : les armoiries dessinées avec les propres formes du paquet dans quatre formes
  d'écu, les fourrures en motifs, une image accessible — `role="img"`, un titre et le blasonnement comme description —,
  les identifiants sous un préfixe donné ou dérivé des armoiries, les couleurs remplaçables ; l'image passe
  `phil/svg-sanitizer` sans changement.
- Des cibles de fuzzing pour le générateur et le dessin, et pour les germes ; des bancs d'essai d'une génération, d'un
  blasonnement et d'un dessin.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.

<!-- phil-dev source-sha256=5c8cdc76f3837d10d8c0e16423c38058a0632dc3286acd1cfa489f06ad504794 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/collections` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.2.0-alpha - 2026-11-14

### Modifié

- `Pack\OpeningStore::complete()` : un stockage enregistre les exemplaires d'une ouverture ajoutée sans eux ; un
  stockage écrit pour 0.1 l'implémente.
- `Packs::open()` réserve la clé, en ajoutant l'ouverture sans exemplaires, avant de donner aucun exemplaire : une
  ouverture qui perd la course pour sa clé contre une autre au même moment ne renvoie l'ouverture gagnante qu'une fois
  complète — en donnant, sous les mêmes clés, les exemplaires que la gagnante n'avait pas encore donnés — ou est refusée
  quand la gagnante venait d'un autre germe du client. La même ouverture ouverte de nouveau donne les exemplaires d'une
  ouverture dont le don a été interrompu.

### Ajouté

- `PackOpening::withCopies()`.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Names`, `Texts`, `Rarity`, `CollectibleDie` et `Reward` : les codes des raretés, des objets, des ensembles, des
  paquets de cartes et des types de récompense ; des textes par locale lus avec la langue et l'anglais comme replis ;
  des raretés qui pèsent de 1 à 10^6 ; les faces, la couleur et la matière d'un dé ; des récompenses déclarées, jamais
  exécutées.
- `Item`, `SetKind`, `ItemSet` et `Pack` : des objets avec un nom et une description traduits, une image, une rareté,
  jusqu'à 20 ensembles, liés ou échangeables, et le dé qu'ils sont ; des ensembles donnés objet par objet, à l'arrivée,
  contre des points ou payants, avec jusqu'à 20 récompenses ; des paquets de 1 à 50 objets tirés de certains ensembles
  avec leurs propres poids.
- `Catalog`, `CatalogReader`, `CatalogProvider`, `FixedCatalog`, `WritableCatalog` et `DefinitionKind` : le catalogue à
  partir de valeurs simples, les clés inconnues refusées, chaque référence vérifiée — la rareté et les ensembles d'un
  objet, les ensembles et les poids d'un paquet —, un ensemble autorisé à ne contenir encore aucun objet, fourni fixe ou
  modifié une définition à la fois.
- `Odds` : la chance publiée de chaque rareté et de chaque objet d'un tirage, et le tirage lui-même — une rareté selon
  son poids, puis un objet de cette rareté — à partir d'un générateur aléatoire injecté.
- `CollectionKey`, `Source`, `Copy` et `CopyMove` : des exemplaires identifiés par la clé sous laquelle ils ont été
  donnés, pour qu'une clé donne un seul exemplaire, avec leur source, leur moment, leur liaison et s'ils ont changé de
  mains ; les clés que dérive la bibliothèque portent `#`, que les clés de l'application ne peuvent pas porter.
- `CopyStore`, `CompletionStore`, `Pack\OpeningStore`, `Dice\ChoiceStore` et `Daily\DailyStore`, avec leurs
  implémentations en mémoire : des exemplaires écrits une seule fois par identifiant, réattribués depuis leur
  propriétaire seulement, repris à moins d'avoir été échangés, oubliés par source à partir d'un moment ; des ensembles
  complétés une seule fois par membre ; des paquets ouverts par clé ; des dés choisis par membre et par nombre de
  faces ; le calendrier par jour. Les implémentations en mémoire se vident avec `reset()`, pour un processus de longue
  durée.
- `Collections`, `Album`, `Holding`, `SetProgress`, `SetCompletion`, `SetCompleted` et `CollectionObserver` : des
  exemplaires donnés sous une clé — un objet, un ensemble entier, chaque ensemble de base à l'arrivée —, une clé qui a
  donné autre chose refusée, les mouvements d'un échange tout ou rien, des exemplaires repris, l'album avec les doubles
  et la progression de chaque ensemble, et un ensemble complété pour la première fois annoncé avec ses récompenses.
- `Pack\Packs`, `Pack\PendingOpening`, `Pack\PackOpening`, `Pack\SeedSource` et `Pack\SecureSeeds` : des paquets engagés
  avec un germe secret de 16 à 64 octets tiré de la source, son engagement montré, puis ouverts une seule fois par clé
  avec le germe du client de 1 à 256 octets que le membre donne ensuite, `Xoshiro256StarStar` amorcé par le HMAC-SHA256
  du germe du client et de la clé sous le germe, les deux germes et l'engagement gardés, et l'ouverture rejouée pour la
  vérifier.
- `Daily\DateRule`, `Daily\FixedDate`, `Daily\Days`, `Daily\PickOrigin`, `Daily\DailyPick` et `Daily\DailyCalendar` :
  l'objet d'un jour gardé, sinon mis en avant par la première règle qui en parle — dates fixes chaque année ou une seule
  fois, règles propres à l'application —, sinon tiré par rareté à partir du jour et d'un sel ; le calendrier généré à
  l'avance jusqu'à 3 660 jours, les jours choisis gardés, et libérés.
- `Daily\Daily` et `Daily\DailyProjection` : l'objet du jour réclamé une seule fois par membre et par jour dans le
  fuseau horaire du calendrier, par une projection de `tilttek/activity` sur les verbes que nomme le site, repris quand
  son activité est annulée, oublié avant une reconstruction à moins d'avoir été échangé.
- `Dice\DiceShelf` et `Dice\DieChoice` : les dés que possède un membre, celui qu'il choisit pour chaque nombre de faces,
  et les dés ordinaires de nouveau une fois qu'il a été échangé.
- `MemberData` et `Erasure` : l'exportation des exemplaires, des ensembles complétés, des paquets ouverts et des dés
  choisis d'un membre, et leur effacement.
- `CollectionsException`, implémentée par `InvalidCollection`, `NotFound` et `CollectionRefused`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.

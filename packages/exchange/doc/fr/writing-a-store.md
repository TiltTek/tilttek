<!-- phil-dev source-sha256=35547a9e8cbf2818770cb12334ec6ca42d53d0bc1c22581e9d5d5ce842032efb -->
# Écrire un stockage

Une application qui garde ses échanges ailleurs qu'en mémoire ou dans les tables du bundle implémente les stockages.
Chaque contrat est aussi un cas de test dans les tests de la bibliothèque (`ReservationStoreTestCase`,
`TransferLogTestCase`, `OfferStoreTestCase`) que le test d'un stockage étend.

## `ReservationStore`

- `add()` écrit une réservation une fois par clé (false quand la clé existe déjà). Elle vérifie les unités des
  réservations réservées et confirmées de la récompense selon le stock et la limite du membre **dans la même étape**,
  pour que deux membres n'obtiennent jamais la dernière unité : sous un verrou, ou en une seule instruction.
- `settle()` fait sortir une réservation de `reserved` seulement tant qu'elle est réservée, et dit si cet appel l'a
  fait.
- `discard()` supprime une réservation encore réservée ; `held()`, `due()`, `ofMember()` et `eraseMember()` lisent et
  effacent.

## `TransferLog`

`record()` écrit un transfert une fois par clé ; `given()` additionne ce qu'un membre a donné dans une devise sur une
période ; `ofMember()`, `latest()` et `eraseMember()` lisent et effacent.

## `OfferStore`

- `open()` écrit une offre une fois par clé (false quand la clé existe déjà) et retient ses objets offerts **dans la
  même étape**, en refusant avec `Refusal::AlreadyHeld` un objet que retient une autre offre ouverte.
- `close()` fait sortir une offre de `open` seulement tant qu'elle est ouverte, libère ses objets et dit si cet appel
  l'a fait.
- `holder()`, `made()`, `due()`, `ofMember()` et `eraseMember()` lisent et effacent.

## `Transaction` et `Inventory`

Une `Transaction` exécute les étapes d'une acceptation ou d'une contre-offre comme une seule unité ; avec une base de
données, une de ses transactions. Un `Inventory` dit à qui appartient un objet et s'il peut changer de mains. Il
déplace ensemble les objets d'un échange accepté, en refusant avec `Refusal::NotOwned` un objet qui n'appartient plus à
celui qui le donne.

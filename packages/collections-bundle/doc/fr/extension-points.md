<!-- phil-dev source-sha256=b99161e4ee3f0af4cdea75798877c0a86cbfe2bffb2ea144759302f6493ff7fe -->
# Points d'extension

## Les échanges par `tilttek/exchange`

Quand `tilttek/exchange` est installé, le bundle enregistre `CollectionsInventory`, les exemplaires comme inventaire
que déplace un échange : un objet d'une offre est un exemplaire, référencé sous la forme
`collection-copy:<identifier>` (`Copy::reference()`). Nommez-le dans la configuration de l'échange, et gardez les deux
bundles sur la même connexion pour qu'un échange déplace les exemplaires et les points dans une seule transaction :

```yaml
tilttek_exchange:
    trades:
        enabled: true
        inventory: Tilttek\CollectionsBundle\Infrastructure\Exchange\CollectionsInventory
```

Seuls les exemplaires échangeables changent de mains ; un exemplaire lié, ou un objet que le catalogue marque comme non
échangeable, est refusé par l'échange.

## Les dés pour `tilttek/dice-bundle`

Quand `tilttek/dice-bundle` est installé, le bundle enregistre `CollectionDiceAppearance` : pour un membre et un nombre
de faces, le dé qu'il a choisi dans sa collection — son nom dans la `locale` configurée, sa couleur, sa matière et son
image —, ou les dés ordinaires. Nommez-le dans la configuration du bundle de dés :

```yaml
tilttek_dice:
    appearance: Tilttek\CollectionsBundle\Infrastructure\Dice\CollectionDiceAppearance
```

## Les germes

Les paquets tirent leurs germes engagés de `Tilttek\Collections\Pack\SeedSource`, alias de `SecureSeeds`. Remplacez
l'alias par votre propre source, comme un générateur matériel ; le germe du client du membre est toujours donné à
`Packs::open()` après l'engagement, jamais tiré ici.

## Le catalogue

`Tilttek\Collections\CatalogProvider` est le catalogue que lit chaque service. Avec `catalog: doctrine`, c'est
`DbalCatalog`, qui est aussi le `WritableCatalog` ; remplacez l'alias pour servir un catalogue venant d'ailleurs.

## Les récompenses

Un ensemble complété est réparti comme le `SetCompleted` de la bibliothèque, avec les récompenses que déclare
l'ensemble : écoutez-le et remettez-les par `tilttek/points`, `tilttek/badges` ou tout autre moyen
([Événements](events.md)).

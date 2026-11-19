<!-- phil-dev source-sha256=74834a25f2a6fc7c55859a6a53fd994e2c3c2d20d341e72d911092a2245ad87b -->
<!-- phil-dev source-sha256=0 -->
# Fiche de production

## Ce qui s'imprime

`LootPrint` compose ce qui s'imprime des tirages d'une commande, lu dans `LootResults` :

```php
foreach ($print->compose($order->number) as $printed) {
    $printed->draw;      // the LootDraw: its line, its option and its unit
    $printed->state;     // PrintState::Verified, NotVerified, Physical or Pending
    $printed->picks();   // the entry of each table, with its face, its image and its slot
    $printed->text();    // the text drawn, for a text
    $printed->shopCode;  // the shop code, when its box has one
    $printed->images();  // the images of the entries, in the order of the tables, for the print files of the unit
}
```

| `PrintState` | Le tirage |
|---|---|
| `Verified` | est révélé, et sa preuve se vérifie par rapport à l'engagement montré avant le paiement |
| `NotVerified` | est révélé, et sa preuve ne se vérifie pas |
| `Physical` | est des dés physiques saisis à une caisse |
| `Pending` | n'est pas encore tiré : il n'a ni résultat, ni code de boutique, ni rien à imprimer |

Une application qui envoie des fichiers d'impression à un atelier y lit les images de chaque unité ; la fiche de
production les écrit.

## La fiche

`LootSheetPdf` imprime la composition d'une commande pour qui fabrique la commande, par `phil/paged-media`. Le service
est enregistré quand ce paquet est installé.

```php
$pdf = $sheet->render($order->number, $clock->now());
```

Pour chaque tirage, par ligne, option et unité :

- l'entrée de chaque table, avec son emplacement et sa face, ou, pour un texte, le texte tiré ;
- son code de boutique ;
- s'il est vérifié par rapport à son engagement, saisi à partir de dés physiques à une caisse, ou ne se vérifie pas ;
- `Not drawn yet` pour un tirage ni révélé ni saisi.

Une commande sans tirage imprime une phrase qui le dit. Une fiche plus longue qu'une page se poursuit sur les suivantes.

| Argument | Ce qu'il règle |
|---|---|
| `labels` | Les mots de la fiche, par les clés de `LootSheetPdf::LABELS`, traduits ; l'anglais par défaut |
| `size` | La page ; Letter par défaut |
| `font`, `bold` | Les polices ; Helvetica imprime les langues occidentales, une `TrueTypeFont` toute autre écriture |

Une application qui imprime dans une autre langue enregistre de nouveau le service avec ses étiquettes :

```yaml
services:
    Tilttek\LootTablesBundle\Infrastructure\Pdf\LootSheetPdf:
        arguments:
            $print: '@Tilttek\LootTablesBundle\Application\LootPrint'
            $labels: { title: 'Butin de la commande %s', pending: 'Pas encore tiré' }
```

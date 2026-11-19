<!-- phil-dev source-sha256=7d8a124a3f60f841b71ada796c6ef433b4e3e5a4022d174b5161ec53840341a2 -->
# Écrans

Le bundle dessine deux écrans : les tirages d'une commande sur la page de l'acheteur, et le formulaire des dés
physiques sur la page de la caisse. Leurs mots sont dans le domaine `tilttek_loot_tables`, en anglais et en français.
Aucun des deux n'a besoin de JavaScript.

## Les tirages de l'acheteur

```twig
<twig:TilttekLootTables:Commitments order="{{ order.number }}" />
```

Un composant Twig, `TilttekLootTables:Commitments`, pour la page de la commande et la confirmation. Chaque tirage est
une `Card` du kit UX, les recettes que `ux:install` met dans l'application :

- son titre nomme la ligne, l'option et l'unité qu'il tire ;
- un `Badge` dit où il en est : scellé jusqu'au paiement de la commande, se vérifiant par rapport à son engagement, ne
  se vérifiant pas, ou dés physiques saisis à une caisse ;
- son engagement, pris quand la commande a été passée et montré avant le paiement ;
- une fois révélé, le germe qu'il a publié, l'entrée de chaque table avec sa face, et le code de boutique.

| Propriété | Ce qu'elle contient |
|---|---|
| `order` | Le numéro de la commande |
| `id` | L'identifiant de la section, `tilttek-loot-commitments` par défaut ; son titre est `<id>-title` |

Le composant ne fait que lire : les tirages sont engagés quand la commande est passée, jamais en les montrant. Il ne
décide pas qui peut voir une commande : ne le rendez que sur une page que l'acheteur de la commande peut ouvrir. Une
commande qui n'existe pas, ou qui n'a rien à tirer ici, dit qu'elle n'a rien à tirer.

Les recettes avec lesquelles il dessine sont `Card`, `Card:Header`, `Card:Title`, `Card:Content` et `Badge`, comme les
donne le kit PHIL UX :

```sh
php bin/console ux:install card badge
```

Elles sont nommées d'après le sous-répertoire des composants du Toolkit de Symfony UX, `ui:Card` quand il s'agit de
`ui`, à moins que `kit_subdirectory` n'indique où elles sont.

Les classes, pour le mettre en forme : `tilttek-loot-commitments`, `__empty`, `__intro`, `__draws`, `__draw`,
`__state`, `__commitment`, `__seed` et `__entries`. Chaque tirage porte son identifiant dans `data-draw`.

## Les dés physiques à une caisse

`PhysicalDiceType` est le formulaire des dés qu'un acheteur a lancés devant le caissier, et
`TilttekLootTables:PhysicalDice` le dessine :

```php
$toEnter = $physicalDice->toEnter($order, $line->reference, 'loot'); // the box and the number of units
$form = $this->createForm(PhysicalDiceType::class, null, $toEnter)->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    $physicalDice->enter($order, $line->reference, 'loot', PhysicalDiceType::codes($form));
    $orders->add($order);
}
```

```twig
<twig:TilttekLootTables:PhysicalDice :form="form.createView" />
```

- `toEnter()` refuse, avec `LootDrawRefused`, une ligne que la commande n'a pas ou une option qui n'est pas une boîte qui
  reste à tirer ici, comme le fait `enter()`, avant que le formulaire ne soit montré.
- Un champ par unité, `unit_1`, `unit_2`…, chacun étiqueté de son unité, son aide nommant les tables dans l'ordre des
  dés. Un champ prend un caractère par dé (`1` à `9`, puis `A` pour 10 jusqu'à `K` pour 20, en majuscules ou en
  minuscules) : le navigateur vérifie sa longueur et ses caractères, et le formulaire lit chaque code selon la boîte,
  refusant sur son champ un code qui ne se lit pas avant que rien ne soit saisi.
- `codes()` donne les codes d'un formulaire soumis, dans l'ordre des unités, pour `PhysicalDice::enter()`.
- Un formulaire demande de 1 à 100 unités.

Le composant dessine la légende qui nomme la boîte, les champs avec leurs étiquettes, leur aide et leurs erreurs, et le
bouton. La page de la caisse décide qui peut saisir des dés, comme toute page de la caisse.

## Accessibilité

- Les tirages de l'acheteur forment une section avec son titre, une liste ordonnée de tirages, chacun avec un titre du
  niveau suivant.
- Où en est un tirage est dit en mots dans son badge, jamais par une couleur seule.
- L'engagement et le germe sont dans des éléments `code`, sous les termes d'une liste de description.
- Chaque champ des dés physiques a son étiquette et son aide, et son erreur lui est rattachée.

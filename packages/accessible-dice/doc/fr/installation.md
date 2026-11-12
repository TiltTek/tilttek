<!-- phil-dev source-sha256=5a3c38d9b3154b412fa7fa804b4e8f9407cca26582d22473365a21254893391b -->
# Installation

```sh
composer require tilttek/accessible-dice
```

Le bundle exige PHP 8.5 ou une version plus récente, Symfony 8.2 et `tilttek/dice-bundle`, dont il utilise les dés
personnalisés et les limites. Enregistrez les deux :

```php
// config/bundles.php
return [
    Tilttek\DiceBundle\TilttekDiceBundle::class => ['all' => true],
    Tilttek\AccessibleDice\TilttekAccessibleDiceBundle::class => ['all' => true],
];
```

## Le formulaire

Le formulaire est un gabarit Twig ; son contrôleur est enregistré par StimulusBundle à partir du `assets/package.json`
du paquet, avec sa feuille de style, `@tilttek/accessible-dice/styles/accessible-dice.css` :

```sh
composer require symfony/twig-bundle symfony/stimulus-bundle symfony/translation
```

Avec AssetMapper, le contrôleur et sa feuille de style sont importés par le `controllers.json` de l'application ;
vérifiez que les deux y sont activés après l'installation du paquet.

## Paquets facultatifs

| Paquet | Ce qu'il ajoute |
|---|---|
| `symfony/twig-bundle` | `accessible_dice()` et le formulaire |
| `symfony/stimulus-bundle` | Le contrôleur : l'expression montrée, les annonces, la lecture à voix haute |
| `symfony/translation` | Les mots du formulaire et les annonces dans la langue du lecteur, et les libellés des faces |
| `tilttek/dice-tower` | Une tour de dés dont le formulaire annonce les jets |

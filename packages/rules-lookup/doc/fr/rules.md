<!-- phil-dev source-sha256=ac6e5a583f2d9ef987e6e3dd2280648ccf7d306b8147e0ecb5714cf6a6f61b9f -->
# Règles

## Une règle

```php
$rule = new Rule(
    new RuleId('open-rules', 'combat.cover', '1.0'),
    Translations::of(['en' => 'Cover', 'fr' => 'Abri']),
    $provenance,
    Translations::of(['en' => 'Walls give cover against ranged attacks.']),
    'combat',
    ['condition.blinded'],
);

$rule->title('fr_CA');   // "Abri"
$rule->text('fr');       // the English text: the rule has no French one
```

| Champ | Ce qu'il contient |
|---|---|
| `id` | Son `RuleId` : système, clé et version |
| `titles` | Son titre par locale, obligatoire |
| `provenance` | Sa source : titre, licence, attribution, auteur et adresse, une `Provenance` de `tilttek/rpg-systems` |
| `texts` | Son texte par locale, éventuellement aucun |
| `category` | Son genre, une clé : `condition`, `combat` |
| `references` | Les clés des règles auxquelles elle renvoie, dans le même système et la même version ; 64 au plus, jamais elle-même, chacune une fois |

Les titres et les textes sont des `Translations` de `tilttek/rpg-systems`, lues pour une locale comme elles le sont là-bas :
la locale, sa langue, l'anglais, puis le premier texte. Un texte contient 4 000 caractères au plus : une longue section
d'une source est scindée en plusieurs règles qui renvoient l'une à l'autre.

## Identifiants

`RuleId` nomme une règle par son système, sa clé et la version du système :

```php
$id = new RuleId('srd-5.2', 'condition.blinded', '5.2.1');

$id->toString();                                 // "srd-5.2@5.2.1:condition.blinded"
RuleId::fromString('lanterns:range.near');       // a system without version
$id->sibling('condition.prone');                 // another rule of the same system and version
```

Le système et la clé sont des clés de `tilttek/rpg-systems` : des lettres ASCII minuscules, des chiffres, `_`, `-` et des
points isolés entre eux, 96 caractères au plus. Une version compte de 1 à 32 lettres, chiffres, `.`, `_`, `+` ou `-`, et
commence par une lettre ou un chiffre. Deux versions d'un système sont deux ensembles de règles : une recherche
restreinte à une version ne lit que ses règles.

## Les règles d'un système

`RulesFromSystem` donne les règles d'un système de `tilttek/rpg-systems`, lu d'un document de son format ou d'un paquet
de données :

| Enregistrement | Clé de la règle | Catégorie | Texte |
|---|---|---|---|
| Un terme, `condition.blinded` | `condition.blinded` | La catégorie du terme | Ses descriptions |
| Un attribut, `grit` | `attribute.grit` | `attribute` | Ses descriptions |
| Une expression de dés, `advantage` | `dice.advantage` | `dice` | Sa notation dans chaque locale de ses noms, `2d20kh1`, `2W20kh1` en allemand |

Chaque règle garde la provenance de l'enregistrement, ainsi que la clé et la version du système.

## Renvois

Une règle renvoie à d'autres règles de son système et de sa version par leurs clés. `RulesLookup::references()` les
suit — un renvoi que le dépôt ne contient pas est omis — et `RulesLookup::referencedBy()` donne les règles qui renvoient
à une règle :

```php
$lookup->references(RuleId::fromString('open-rules@1.0:combat.cover'), 'fr');
$lookup->referencedBy(RuleId::fromString('open-rules@1.0:condition.blinded'));
```

<!-- phil-dev source-sha256=c219cd8685c99e3fb4461bfa1d6c431d869d78d35cc7c208f31594a8d1136b23 -->
# Règles de transport et charges

## Ce que compte un système

Les règles de transport d'un système de jeu sont un `CarryRules` : la `Measure` qu'il compte — `Weight` ou `Slots` —, la
capacité d'un porteur quand l'application n'en donne aucune, ses paliers de charge du plus léger au plus lourd, et si un
porteur peut dépasser le dernier palier.

```php
use Tilttek\Inventory\CarryRules;
use Tilttek\Inventory\LoadBand;
use Tilttek\Inventory\Measure;

// Weight, three bands as fractions of the capacity
$variant = new CarryRules(Measure::Weight, 150, [
    new LoadBand('unencumbered', 1, 3),
    new LoadBand('encumbered', 2, 3),
    new LoadBand('heavily-encumbered', 1),
]);

// Slots, never beyond them
$slots = new CarryRules(Measure::Slots, 10, [new LoadBand('fine', 1)], refuseOverCapacity: true);
```

Un palier a une clé et se termine à une fraction de la capacité du porteur, numérateur et dénominateur de 1 à 1000, de
sorte qu'un tiers est exact ; une fraction supérieure à 1 déclare un palier au-delà de la capacité qu'un système permet
quand même. Des règles contiennent de 1 à 10 paliers, sous des clés distinctes, chacun se terminant au-dessus du
précédent ; la capacité par défaut va de 0 à 1 000 000 000 000. Tout calcul se fait en nombres entiers.

## Le livre de règles

`Inventories` lit les règles du système d'un inventaire dans un `Contract\RuleBook`. `Rules\StaticRuleBook` les reçoit
par clé de système ; une application les construit à partir des systèmes qu'elle connaît et des variantes avec
lesquelles joue une table :

```php
use Tilttek\Inventory\Rules\StaticRuleBook;

$book = new StaticRuleBook(['srd-5.2' => $variant, 'knave' => $slots]);
```

Un inventaire sans système, ou dont le système n'a aucune règle dans le livre, n'est jamais pesé : `load()` donne null
et `loads()` une liste vide. Changer le système d'un inventaire ne refuse rien : les charges disent ce que les nouvelles
règles en font.

## Les capacités

La capacité de chaque porteur vient d'un `Contract\CarrierCapacity`, selon la mesure que comptent les règles. La valeur
par défaut, `Rules\DefaultCapacity`, donne la capacité par défaut des règles à chaque porteur ; une application la lit
sur la fiche du personnage :

```php
use Phil\Resource\ResourceReference;
use Tilttek\Inventory\CarryRules;
use Tilttek\Inventory\Contract\CarrierCapacity;
use Tilttek\Inventory\Inventory;

final readonly class SheetCapacity implements CarrierCapacity
{
    public function __construct(private Sheets $sheets) {}

    public function capacity(Inventory $inventory, ResourceReference $carrier, CarryRules $rules): int
    {
        $strength = $this->sheets->score($carrier, 'strength');

        return null === $strength ? $rules->defaultCapacity : 15 * $strength;
    }
}
```

Une capacité va de 0 à 1 000 000 000 000 ; toute autre valeur lance `InvalidInventory`.

## Les charges

`load()` pèse ce qu'un porteur porte dans un inventaire : les objets directement sur lui, chacun avec ce qu'il contient
— le contenu d'un contenant compte, sauf s'il ne pèse rien, à n'importe quelle profondeur :

```php
$load = $inventories->load($inventory, $ada);
$load->carried;      // 46
$load->capacity;     // 150
$load->band?->key;   // 'unencumbered', null over the last band
$load->limit;        // 150, the most the last band allows
$load->room();       // 104 before the last band is passed
$load->isOver();     // false

$inventories->loads($inventory);   // each carrier holding something, in the order of their references
```

Un porteur est dans le palier le plus léger dont sa charge ne dépasse pas la fin. Quand les règles refusent de dépasser
la capacité, un acte qui placerait le porteur de la destination au-delà du dernier palier — ajouter, augmenter,
déplacer, transférer — est refusé avec `Refusal::OverCapacity` ; un acte qui allège un porteur n'est jamais refusé, même
celui d'un porteur déjà au-delà après un changement de ses règles ou de sa capacité. Autrement, une charge au-delà du
dernier palier est signalée, jamais refusée.

Les objets de la réserve ne sont jamais pesés. Un inventaire pèse les porteurs qui sont en lui : un personnage qui
porte des objets dans son propre inventaire et dans celui de son groupe a deux charges, que l'application additionne
quand son jeu les compte ensemble.

## Le remplissage des contenants

`fill()` dit ce qu'un contenant renferme par rapport à ses limites, selon les deux mesures, quoi que compte le système :

```php
$fill = $inventories->fill($inventory, 'pack');
$fill->weight;        // what is directly inside, each container inside with its contents
$fill->slots;
$fill->weightLeft();  // null without a bound by weight
$fill->slotsLeft();
```

`fill()` sur un objet qui n'est pas un contenant est refusé avec `Refusal::NotAContainer`.

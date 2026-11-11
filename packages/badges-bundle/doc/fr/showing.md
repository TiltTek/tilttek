<!-- phil-dev source-sha256=b0c5704ee7c61bdc5422ffd7d2baf7c188560072835888aa2f1ae60efe8a48ea -->
# Afficher les badges

## Dans un contrôleur

```php
use Tilttek\Badges\Badges;

final readonly class ProfileBadges
{
    public function __construct(private Badges $badges) {}

    public function feature(ResourceReference $member, array $codes): void
    {
        $this->badges->feature($member, $codes); // the member's choice, in their order
    }
}
```

`Badges` offre les attributions, la progression et la vue d'ensemble, les attributions manuelles, `show()`, `hide()`,
`feature()`, `entitle()`, `showcase()`, `share()`, et les consentements des autres applications — `grant()`,
`withdraw()`, `clients()`, `readFor()`. Un badge gagné reste privé tant que le membre ne le montre pas.

## Dans Twig

| Fonction | Renvoie |
|---|---|
| `tilttek_badges_showcase('member:44')` | Ce que tout le monde peut voir : le titre, la vitrine, les badges montrés |
| `tilttek_badges_overview('member:44')` | L'état du membre pour chaque badge — pour sa propre page seulement |
| `tilttek_badges_domain()` | Le domaine de traduction des textes des badges |

```twig
{{ include('@TilttekBadges/showcase.html.twig', {showcase: tilttek_badges_showcase('member:' ~ member.id), label: 'Badges'|trans}) }}
{{ include('@TilttekBadges/overview.html.twig', {standings: tilttek_badges_overview('member:' ~ app.user.id)}) }}
```

Les gabarits traduisent les noms et les descriptions dans le domaine configuré. Ils donnent aux images un texte de
remplacement vide à côté du nom, et affichent la progression comme un élément `<progress>` accompagné de son texte,
*7 / 10*. Ils portent des classes et aucun style : l'application écrit sa propre CSS, ou ses propres gabarits.

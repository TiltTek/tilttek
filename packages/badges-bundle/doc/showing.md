# Showing badges

## In a controller

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

`Badges` has the awards, the progress and the overview, manual awards, `show()`, `hide()`, `feature()`, `entitle()`,
`showcase()`, `share()`, and the consents of other applications — `grant()`, `withdraw()`, `clients()`, `readFor()`.
A badge earned is private until the member shows it.

## In Twig

| Function | Returns |
|---|---|
| `tilttek_badges_showcase('member:44')` | What anyone may see: the title, the showcase, the badges shown |
| `tilttek_badges_overview('member:44')` | Where the member stands with each badge — for their own page only |
| `tilttek_badges_domain()` | The translation domain of the badges' texts |

```twig
{{ include('@TilttekBadges/showcase.html.twig', {showcase: tilttek_badges_showcase('member:' ~ member.id), label: 'Badges'|trans}) }}
{{ include('@TilttekBadges/overview.html.twig', {standings: tilttek_badges_overview('member:' ~ app.user.id)}) }}
```

The templates translate names and descriptions in the configured domain, give images an empty alternative text beside
the name, and show the progress as a `<progress>` element with its text, *7 / 10*. They carry classes and no style:
the application writes its own CSS, or its own templates.

# Guards

What members give each other — transfers of points, trades of items — passes through `Guards`, one for each:

| Guard | Rule | Refusal |
|---|---|---|
| Self-exchange | A member never exchanges with themselves; always on. | `Refusal::SelfExchange` |
| New accounts | Both members joined at least the delay before now. | `Refusal::NewMember` |
| Allowance | What a member gives over a period stays within the limit: points for transfers, offers for trades. | `Refusal::AllowanceReached` |

```php
use Tilttek\Exchange\Allowance;
use Tilttek\Exchange\Guards;
use Tilttek\Points\Rule\Period;

$transferGuards = new Guards(new Allowance(500, Period::Week), 'P7D', 'America/Toronto', $memberSince);
$tradeGuards = new Guards(new Allowance(10, Period::Day), timezone: 'America/Toronto');
```

The periods are those of the earning caps of `tilttek/points` — a calendar day, an ISO week from Monday, a calendar
month or year, or ever —, counted in the guards' timezone. The joining dates come from the application's
`MemberSince`; a member it does not know is refused while a delay is set, and `NoMemberSince` knows no one.

The allowance is checked against what the stores hold before the exchange is written: two exchanges of the same member
at the very same moment may both pass. It is a guard against abuse, not a balance — the balance of points itself
never goes negative.

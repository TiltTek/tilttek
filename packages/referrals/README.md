# tilttek/referrals

**Referrals rewarded in points, on both sides, once the newcomer really plays, with a cap per referrer.**

tilttek/referrals lets a member share a code — in a link the application builds — and rewards them, and the friend who
joined through it, in points of `tilttek/points`: never money, a commission or a discount. The reward waits for the
newcomer's first real action — a game played, a character sheet published —, which the application tells through a
port, never the sign-up itself, so that an account created only to collect points brings nothing. A referrer is
rewarded for ten referrals by default, ever; past the cap, the newcomer still receives their points.

The guards on exchanges of `tilttek/exchange` hold for referrals too: a member never refers themselves, nor their
other account when the application tells two accounts belong to one person; a referrer whose account is too new waits,
and only a member who joined recently can be referred, each guard on dates off until the application sets it. The
code is random and tells nothing of its member.

The points are given once, whatever happens: each side's movement is written under a key built from the referral's
random identifier, which the ledger writes once, and the referral is settled in one step of the store that the cap
holds through. Erasing a member leaves no code and no referral that links them to anyone.

It is for a community that wants its members to bring friends without turning friendship into a sale. It is a plain
PHP library whose dependencies are `tilttek/points`, `tilttek/exchange`, `phil/resource` and `psr/clock`.

## Status

| Part | State |
|---|---|
| `Referrals`: code, referral, reward, reading, cap, erasure | Implemented and tested |
| `ReferralCode`, `ReferralId`, `Referral`, `Terms`, `FirstAction` | Implemented and tested |
| `Contract\FirstActions`, `Contract\SamePerson`, `Contract\ReferralStore`, with their defaults | Implemented and tested |
| `Store\InMemoryReferralStore` | Implemented and tested |
| A store on Doctrine DBAL, and a Symfony bundle | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Referring and rewarding](doc/referrals.md) | Codes, guards, the first action, the reward paid once, the cap, reading, erasing. |
| [Extension points](doc/extension-points.md) | The store, the first actions, the same-person signal, joining dates, terms. |
| [Exceptions](doc/exceptions.md) | `ReferralsException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests, and testing code that refers members. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require tilttek/referrals
```

```php
use Tilttek\Referrals\Referrals;
use Tilttek\Referrals\Store\InMemoryReferralStore;

$referrals = new Referrals(new InMemoryReferralStore(), $points, $firstActions, $clock);

$code = $referrals->code($member);              // put in the link the member shares
$referrals->refer($code->value, $newcomer);     // when the newcomer joins through it
$referrals->reward($newcomer);                  // when the newcomer plays a first game: 100 and 50 points
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).

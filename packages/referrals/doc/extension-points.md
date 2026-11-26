# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Default |
|---|---|---|
| `Contract\ReferralStore` | Where the codes and the referrals are kept | `Store\InMemoryReferralStore`, for tests |
| `Contract\FirstActions` | The newcomer's first real action | None: the application gives it |
| `Contract\SamePerson` | Whether two accounts belong to one person | `Contract\NoSamePersonSignal` |
| `Tilttek\Exchange\MemberSince` | When a member joined, for the guards on dates | `Tilttek\Exchange\NoMemberSince` |
| `Terms` | The points, the cap, the currency, the source and the guards on dates | 100 and 50 points, ten rewards |
| `Random\Randomizer` | The source of the codes and identifiers | A `Randomizer` on the secure engine |

The library keeps no state between two calls; the implementations an application gives keep none either.

## The store

`Contract\ReferralStore` keeps the codes and the referrals. A store backed by a database holds:

- a unique constraint on the code, and one on the member of a code: `addCode()` returns the member's code when they
  hold one, and null, keeping nothing, when the code belongs to another member;
- a unique constraint on the newcomer of a referral: `add()` returns the referral kept for the newcomer when there is
  one;
- `settle()` in one step that another process cannot slip between — a transaction that locks the referrer's rows, or
  one conditional update —: the referral moves from pending to rewarded once, and the referrer's points count only
  while fewer than `maxRewards` of their referrals gave them points, and never for `erased:member`;
- `refuse()` moving a pending referral to refused, once;
- `eraseMember()` as [Referring and rewarding](referrals.md#erasing-a-member) describes.

`ofReferrer()` reads the referrer's referrals oldest first, from an offset; `rewardedCount()` counts those whose
referrer received points.

## First actions

`FirstActions::firstActionOf($member)` gives the member's first real action as a `FirstAction`: its kind, the
application's name for it such as `game.played` or `sheet.published`, and its moment. The application answers from its
own records: the first game the member played, the first sheet they published. A sign-up, a profile filled in or a
confirmed address is no real action: answer null until the member has made one.

## The same-person signal

`SamePerson::same($referrer, $newcomer)` says whether the two accounts belong to one person, from the signals the
application keeps — the same verified address, the same device, the same payment method in another part of the site.
It is asked when the newcomer is referred and again when the reward is paid. `Contract\NoSamePersonSignal` answers
false: two references are two people.

## Joining dates

`MemberSince` of `tilttek/exchange` tells when a member joined, or null when the application does not know them. It is
read only when `Terms::$referrerSeniority` or `Terms::$newcomerWindow` is set.

## Terms

| Term | Default | Bounds |
|---|---|---|
| `referrerPoints` | 100 | 1 to 1 000 000 |
| `newcomerPoints` | 50 | 1 to 1 000 000 |
| `maxRewards` | 10 | 1 to 10 000 referrals that reward the referrer, ever |
| `currency` | null, the default currency of `tilttek/points` | A currency code |
| `source` | `referrals` | An issuance account name of `tilttek/points` |
| `referrerSeniority` | null, off | An ISO 8601 duration such as `P7D` |
| `newcomerWindow` | null, off | An ISO 8601 duration such as `P2D` |

A change of the points applies to the referrals settled afterwards; a referral already settled keeps its amounts.

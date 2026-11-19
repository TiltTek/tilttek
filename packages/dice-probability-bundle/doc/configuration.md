# Configuration

```yaml
# config/packages/tilttek_dice_probability.yaml
tilttek_dice_probability:
    explosion_depth: 10
    work_limit: 100000
    samples: 5000
    seed: 0
    roll_limit: 50000
    max_dice: 50
    max_outcomes: 500
    group_work_limit: 2000000
    rounding: floor
    kit_subdirectory: ~
    rate_limit:
        enabled: true
        limit: 60
        interval: '1 minute'
        cache_pool: cache.app
        limiter: ~
        single_server: false
```

| Key | Default | What it sets |
|---|---|---|
| `explosion_depth` | `10` | How many explosions per die an exact computation follows; the probability of more is stated |
| `work_limit` | `100000` | How much work an exact computation does at most before an estimate takes over |
| `samples` | `5000` | How many rolls an estimate is drawn from |
| `seed` | `0` | The seed of those rolls: the same seed gives the same estimate |
| `roll_limit` | `50000` | How many dice an estimate rolls at most over all its samples; past it, the expression has no probabilities |
| `max_dice` | `50` | How many dice an expression rolls at most for its probabilities, below the limit of a roll of `tilttek_dice` |
| `max_outcomes` | `500` | How many values a distribution has at most; past it, it is refused rather than drawn |
| `group_work_limit` | `2000000` | How many steps the search of the groups of an estimate, `6d6g10`, takes at most over all its samples; past it, the expression is refused before it is rolled |
| `rounding` | `floor` | How a division rounds when the expression does not say: `floor`, `ceil`, `round`, `towards_zero` or `away_from_zero` |
| `kit_subdirectory` | `null` | Where the recipes of the UX kit are, under the components directory; null takes the subdirectory of the `ux_toolkit` configuration |
| `rate_limit.enabled` | `true` | Whether the Live Component counts its computations against a rate limit per client |
| `rate_limit.limit` | `60` | The computations of one client within the interval |
| `rate_limit.interval` | `'1 minute'` | The sliding window the limit counts over |
| `rate_limit.cache_pool` | `cache.app` | The cache pool the counts are kept in, on a store the servers share: one local to each server is refused in the `prod` environment |
| `rate_limit.limiter` | `null` | The service id of a rate limiter factory of the application, instead of the sliding window the bundle builds |
| `rate_limit.single_server` | `false` | Accepts a cache pool local to the server in the `prod` environment, for an application on one server |

Each bound is at least 1. A page computes on each request, and anyone may type a short expression that asks for a lot
of work: `100d6!` or `10d1000` are seven characters. The bounds hold every request to a fraction of a second at the
defaults: the work limit and the roll limit bound the computation and the estimate, `max_dice` refuses large sets of
dice before any computation, `group_work_limit` refuses groups too long to search, and `max_outcomes` refuses a table
of thousands of rows before it is drawn. A refusal is an exception — `LimitExceeded` of `tilttek/dice`,
`CannotCompute` of `tilttek/dice-probability`, or the bundle's `GroupsTooCostly` and `TooManyOutcomes` — that the
component says in its alert. Raise the bounds for a page that only trusted people use; lower them for a busier one.

## The search of groups

A set that forms groups, `6d6g10`, counts the most groups of dice that each reach a sum. The library always estimates
it, and each sample searches the groups of its dice: exhaustively up to 12 dice, in about `n × 2ⁿ` steps for `n`
dice, and greedily beyond, in about `n²`. `12d6g12` would take tens of seconds on 5,000 samples. The bundle weighs
that search before the library rolls anything, by `GroupSearch`:

- a set of a written count, `8d6g8`, by that count;
- a set of a computed count, `(1d4)d6g6`, or dice taken from other instructions, by the worst count up to `max_dice`;
- a set that explodes, by its count and as many more dice as the explosions of a roll of `tilttek_dice` may add;
- what `repeat()` repeats, once for each time, and every branch of a condition.

The steps of one sample, times `samples`, must stay within `group_work_limit`. At the defaults, sets of up to 6 dice
pass, in about a third of a second; `7d6g7`, `12d6g12` or `10d10g10` are refused at once, with the reason
`refusal.groups` of the translations. With fewer samples, larger sets pass.

The same limit is given to the `Settings` of `tilttek/dice-probability`, whose estimate counts the steps of the groups
it actually searches, sample by sample, and stops with `CannotCompute` past it: an expression the bundle weighs too
low is still bounded.

## The rate limit of the component

The Live Component computes again each time the browser sends the expressions back, on each pause in the typing.
Each of those requests counts one computation against the client's limit: 60 a minute by default, in a sliding window
of `symfony/rate-limiter`. The first render, drawn by the page itself, is not counted. Past the limit, the component
computes nothing and says why in its alert, with the reason `refusal.throttled`.

A client is its IPv4 address, or its IPv6 address with the rest of its /64, since a client is usually given a whole
/64; an IPv4 address mapped into IPv6 counts as the IPv4 address. Behind a proxy, configure the trusted proxies of
Symfony so that the client's address is the one counted. The key of the limit is a digest of that network keyed with
`kernel.secret`, never the address. A failure of the limiter or of its storage refuses the computation.

The counts are kept in `cache_pool`: on several servers, name a pool they share — Redis, Memcached, a database —, or
a client could spend the limit once per server. In the `prod` environment, the container refuses to compile with a
pool local to each server — the filesystem, APCu, array or system adapters, `cache.app` by default —, through the
`RateLimitGuard` of `phil/rate-limit`. An application on one server says so:

```yaml
tilttek_dice_probability:
    rate_limit:
        single_server: true
```

`limiter` names a factory of the application instead, such as one of `framework.rate_limiter`, whose service id is
`limiter.<name>`; the bundle then builds no storage and checks none.

With `symfony/ux-live-component` installed, the rate limit needs `symfony/rate-limiter`: the container refuses to
compile without it, unless the rate limit is turned off:

```yaml
tilttek_dice_probability:
    rate_limit: false
```

The templates draw with the recipes of the UX kit, which the Symfony UX Toolkit installs in the application under the
subdirectory of its `ux_toolkit` configuration: with `ui`, the card is `ui:Card`. The bundle reads that subdirectory
when the container is compiled. In an environment where the Toolkit is not enabled, as production often is, repeat it
in `kit_subdirectory`.

The custom dice, the limits and the notations are those of `tilttek_dice`: an expression is read and checked as for a
roll.

The services keep no state between requests: they are safe in a worker.

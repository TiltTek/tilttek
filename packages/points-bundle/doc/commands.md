# Commands

Registered when `symfony/console` is installed.

## `tilttek:points:expire`

```sh
bin/console tilttek:points:expire
```

Expires the credits whose lifetime has ended, for every member and in every currency with a lifetime, and prints how
many points expired. Schedule it once a day; a spending and a transfer expire the payer's credits first anyway.

## `tilttek:points:history`

```sh
bin/console tilttek:points:history member:44
bin/console tilttek:points:history member:44 --currency=stars --after=120 --limit=100
```

Prints the member's balance in the currency — the default one when none is named — and their movements after a
position, at most 1,000: position, moment, kind, signed points, currency and key. A member reference that is not one,
an unknown currency or a limit above 1,000 prints an error and fails.

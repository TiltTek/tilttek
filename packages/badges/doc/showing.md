# What a member shows

**A badge earned is private until the member shows it.** `Badges` keeps what each member chose in a `Profile`:

| Method | What it does |
|---|---|
| `show($member, $code)` | Makes an earned badge public |
| `hide($member, $code)` | Makes it private again, out of the showcase and off as a title |
| `feature($member, [$codes])` | Replaces the showcase with these earned badges, in this order, each made public; at most the showcase size, six by default |
| `entitle($member, $code)` | Wears an earned badge as the title beside the member's name, made public; `null` for none |
| `profile($member)` | What the member chose |
| `showcase($member)` | What anyone may see: the title, the showcase and every badge shown, those the member no longer holds left out |

Showing, featuring or wearing a badge the member has not earned raises `NotEarned`.

## Sharing

`Badges::share($member, $code, $channel)` shares the latest award of an earned badge through a `ShareChannel` — an image,
a link, a post on the Fediverse — and returns what the channel made of it. The library never shares on its own: the
application calls it when the member asks.

## Another application

A member allows another application to read their badges with `Badges::grant($member, 'portfolio')` and withdraws the
consent with `withdraw()`; `clients()` lists those they allowed. `Badges::readFor($member, 'portfolio')` gives the
application their awards, or raises `AccessDenied` when they have not allowed it. Authenticating the application is the
application's own: the library only keeps the member's consent.

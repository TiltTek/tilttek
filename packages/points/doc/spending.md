# Spending and giving

Each operation takes the key the application gives it; the same call made twice moves the points once.

## Grants and revocations

```php
$points->grant($member, 100, 'contest:2026-spring:44', source: 'contest:2026-spring');
$points->revoke($member, 20, 'fix:2026-11-07:44');
```

A grant issues points from a source's issuance account — `manual` by default —; a revocation takes them back to it. A
revocation is a correction, not an exchange: it is written as it is, even when the balance falls below zero.

## Spendings and refunds

```php
$points->spend($member, 300, 'order:2026-0193:redeem', 'order:2026-0193');
$points->refund('order:2026-0193:redeem');
```

A spending moves points to a redemption account named by what they pay for. **It never makes a balance negative**: the
member's credits due to expire are expired first, then the balance is checked and the movement written as one step
that another process cannot slip between; a balance that does not cover the amount raises `InsufficientPoints`.

A refund gives back the points of a spending, once, as a new credit whose lifetime starts at the refund. A reservation
is a spending refunded when it is cancelled.

## Transfers

```php
$points->transfer($giver, $receiver, 10, 'gift:2026-11-07:44:12');
```

A transfer from one member to another, when the application allows it, is checked like a spending. The library does
not decide who may give to whom, nor how much: the application does, before calling it.

## Currencies

Every operation takes a currency's code, the default one when none is named; an unknown code raises
`UnknownCurrency`. Points of one currency never become points of another.

# Nights and sign-ups

`GameNights` is the service of the nights. It reads a night from the store, changes it, keeps it at its next version,
then tells the observer what happened. Every change is checked by `GameNight` as a whole: a night that would break one
of its rules is never kept.

## Planning

`plan()` takes the identifier of the night, when it starts and ends, the identifier of its venue, the member who hosts
it, and its tables. A night lasts at most a day, and starts in the future. It is kept as a draft: nobody signs up yet.

Every identifier — night, venue, table, member — is opaque: 1 to 128 letters, digits, `_`, `.`, `:` or `-`, starting
with a letter. It holds no `@`, so that an address never stands for a member. A game keeps the shape of the catalogue,
where `@` is allowed.

## Tables

A `Table` has its seats, 1 to 30, and, when known, the game planned at it and the member who leads it. A night has at
most 50 tables; its places are the seats of all its tables.

`addTable()` adds a table, or replaces the table of the same identifier; it keeps at least the places taken at it.
The seats it adds go to the members waiting. `removeTable()` removes a table when the other tables seat the confirmed
places; the members seated at it are seated nowhere.

## Publication

`publish()` opens a draft night to sign-ups, when it has at least one seat and has not started. The observer is told
`NightPublished`.

## Sign-ups

`register()` signs a member up, alone or with one guest. A sign-up takes one place, two with a guest; the guest has no
identifier, and nothing of them is kept.

The sign-up holds its places when they are free and nobody is waiting; otherwise it waits on the waiting list. A
member signs up once. The waiting list holds at most 200 members: past that, a sign-up is refused.

When the last free seat is taken, the observer is told `NightFull`, once: it is told again only after a seat was freed
and taken again.

## The waiting list

The waiting list is served in the order members signed up. When places are freed — a member withdraws, a table is
added or grows — they go to the first member waiting, when their places fit, then to the next, and so on. A member
waiting whose places do not fit holds the others back: a member who signed up later never passes them. Each member
who holds their places from the waiting list is told once, by `SeatOffered`.

## Withdrawals

`withdraw()` removes a member's sign-up, and their vote. The places they free go to the members waiting.

## Seats

`seat()` seats a confirmed member, with their guest, at a table that has the free seats, or nowhere with `null`. A
member waiting is seated once they hold their places.

## Closing and cancelling

Sign-ups, withdrawals, seats and the vote close when the night starts.

`cancel()` cancels a night before it ends, for good: nothing changes it afterwards. The observer is told
`NightCancelled`, once, and an application tells the members signed up from it.

## No reminders

The library tells each fact once and keeps no schedule: it sends no reminder, and never repeats one to the members
who did not come.

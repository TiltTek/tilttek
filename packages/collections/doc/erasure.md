# Erasure

`MemberData::export(member)` lists what the collections keep of a member: their copies, the sets they completed, the
packs they opened with their seeds, and the dice they chose, each as plain values.

`erase(member)` deletes all of it and returns an `Erasure` with the count of each. The copies the member traded away
belong to other members and stay theirs: a copy remembers its source, never the members it went through. Their
activity is erased by `tilttek/activity`, and their offers by `tilttek/exchange`.

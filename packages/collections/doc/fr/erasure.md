<!-- phil-dev source-sha256=39c2bb5631175abb3ce22b82d869bc19927965677f9983135646281980ad2065 -->
# Effacement

`MemberData::export(member)` énumère ce que les collections gardent d'un membre : ses exemplaires, les ensembles qu'il a
complétés, les paquets qu'il a ouverts avec leurs germes, et les dés qu'il a choisis, chacun sous forme de valeurs
simples.

`erase(member)` supprime le tout et renvoie une `Erasure` avec le nombre de chaque sorte. Les exemplaires que le membre
a échangés appartiennent à d'autres membres et restent les leurs : un exemplaire se souvient de sa source, jamais des
membres par lesquels il est passé. Son activité est effacée par `tilttek/activity`, et ses offres par
`tilttek/exchange`.

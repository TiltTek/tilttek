# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Keep the seasons in a database | Implement `Contract\SeasonCatalogue`, building each `Season` from your rows — `SeasonReader::season()` reads an array |
| Read what is on from another package | Depend on `Contract\ActiveContent`, never on `SeasonalEvents` |
| Answer for another moment | Give `SeasonalEvents` another PSR-20 clock, or pass the moment to each call |
| Open a quest with a season | Give a `tilttek/quests` quest a `Bridge\Quests\SeasonQuestSchedule` |
| List the items a season brings | Use `Bridge\Collections\SeasonalItems` on your `tilttek/collections` catalog |

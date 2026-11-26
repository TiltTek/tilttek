# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Default |
|---|---|---|
| `Contract\PredictionStore` | Where the questions and the predictions are kept | `Store\InMemoryPredictionStore`, for tests |
| The currency | A currency code of `tilttek/points` | Its default currency |
| The source | An issuance account name of `tilttek/points` | `predictions` |
| `Random\Randomizer` | The source of the identifiers | A `Randomizer` on the secure engine |
| The batch size | How many predictions a settlement reads at once | 500 |

The library keeps no state between two calls; the store an application gives keeps none either.

## The store

`Contract\PredictionStore` keeps the questions and the predictions. A store backed by a database holds:

- a unique constraint on the key of a question: `addQuestion()` returns the question kept under the key when there is
  one;
- `close()` moving an open question to resolved or cancelled once, and returning a question already closed as it
  stands;
- a unique constraint on the question and the member of a prediction: `save()` replaces the member's pick, keeping the
  identifier of their first prediction;
- `save()` checking that the question takes predictions at the moment and writing in one step — one conditional insert
  or update joined to the open question before its deadline —, returning null, writing nothing, otherwise;
- `predictions()` reading a question's predictions in the order of their identifiers, after the one given, so that a
  prediction erased meanwhile shifts no page;
- `eraseMember()` deleting the member's predictions.

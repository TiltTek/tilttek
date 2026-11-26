# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Predictions` | Asks questions, records picks until the deadline, resolves, settles and cancels, reads, erases a member |
| `Question` | A question on a dated outcome: its outcomes, deadline, points, scoring and status |
| `QuestionKey` | The application's key of a question |
| `QuestionStatus` | Open, resolved or cancelled |
| `Scoring` | By choice, or by probability with the Brier score |
| `Pick` | One outcome, or a probability for each outcome |
| `Prediction` | A member's pick on a question, with its random identifier |
| `PredictionId` | The random identifier of a prediction, which the key of its points carries |
| `Brier` | The Brier score in ten-thousandths, and the points it earns |
| `Settlement` | What settling did: predictions read, rewarded, points, movements written |
| `Contract\PredictionStore` | Where the questions and the predictions are kept |
| `Store\InMemoryPredictionStore` | The store in memory, for tests |
| `Exception\PredictionsException` and its three exceptions | [Exceptions](exceptions.md) |

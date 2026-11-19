<!-- phil-dev source-sha256=a511565ceb9c7ed9da2efdd9a0c33eb270e2c019eec91b5118d5257bc2b376f3 -->
# Référence

| Type | Rôle |
|---|---|
| `TilttekDiceProbabilityBundle` | Le bundle : sa configuration et ses services |
| `Application\DiceStatistics` | Distributions, comparaisons et événements d'expressions écrites en texte |
| `Application\Rounding` | Les modes d'arrondi par nom : `floor`, `ceil`, `round`, `towards_zero`, `away_from_zero` |
| `Application\GroupSearch` | Pèse la recherche des groupes d'une expression avant qu'elle soit estimée |
| `Application\Exception\Refusal` | Un refus du bundle : la clé et les paramètres de sa raison traduite |
| `Application\Exception\GroupsTooCostly` | Des groupes dont la recherche dépasserait `group_work_limit` |
| `Application\Exception\TooManyOutcomes` | Une distribution de plus de valeurs que `max_outcomes` |
| `Application\Exception\Throttled` | Un calcul que la limite de débit a refusé |
| `Infrastructure\DependencyInjection\ProbabilityServices` | Enregistre les services |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Nomme les composants du kit d'après le sous-répertoire du Toolkit, sauf si `kit_subdirectory` le dit |
| `Infrastructure\Twig\KitComponents` | Les noms des composants du kit UX, et les recettes dont les gabarits ont besoin |
| `Infrastructure\Twig\ProbabilityExtension` | Les fonctions Twig et le filtre |
| `Infrastructure\Twig\Components\DistributionComponent` | Le Live Component `TilttekDiceProbability:Distribution` |
| `Infrastructure\RateLimiter\ComputationThrottle` | La limite de débit du composant, par adresse IPv4 ou par /64 IPv6 |

| Service | Public |
|---|---|
| `Tilttek\DiceProbability\Settings` | non |
| `tilttek_dice_probability.limits`, les `Limits` des probabilités | non |
| `Tilttek\DiceProbability\Probabilities` | oui |
| `Tilttek\DiceProbabilityBundle\Application\GroupSearch` | non |
| `Tilttek\DiceProbabilityBundle\Application\DiceStatistics` | oui |
| `Tilttek\DiceProbabilityBundle\Infrastructure\Twig\KitComponents` | non, avec Twig |
| `Tilttek\DiceProbabilityBundle\Infrastructure\Twig\ProbabilityExtension` | oui, avec Twig |
| `Tilttek\DiceProbabilityBundle\Infrastructure\Twig\Components\DistributionComponent` | non, avec Symfony UX |
| `tilttek_dice_probability.limiter` et `tilttek_dice_probability.limiter.storage`, la fenêtre glissante et son stockage | non, avec la limite de débit et sans `limiter` |
| `Tilttek\DiceProbabilityBundle\Infrastructure\RateLimiter\ComputationThrottle` | non, avec Symfony UX et la limite de débit |

<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\Mercure;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\ProtocolVersion;
use Tilttek\EscapeRoomBundle\Application\EscapeRoomTopics;

/**
 * The subscription of the current viewer to a game's moves: the authorization cookie of the current response is given
 * the game's topic, and the URL to subscribe to on the hub is returned. Without a hub, there is no URL, and the screens
 * read the game again at intervals.
 */
final readonly class ScreenSubscriptions
{
    public function __construct(
        private EscapeRoomTopics $topics,
        private ?HubInterface $hub = null,
        private ?Authorization $authorization = null,
        private ?RequestStack $requests = null,
        private ?string $hubName = null,
    ) {
    }

    public function subscribe(string $game): ?string
    {
        if (!$this->hub instanceof HubInterface) {
            return null;
        }

        $topic = $this->topics->topic($game);
        $request = $this->requests?->getCurrentRequest();

        if ($this->authorization instanceof Authorization && $request instanceof Request) {
            $this->authorization->setCookie($request, [$topic], hub: $this->hubName);
        }

        $url = $this->hub->getPublicUrl();
        $parameter = ProtocolVersion::Legacy === $this->hub->getProtocolVersion() ? 'topic' : 'match';

        return $url.(str_contains($url, '?') ? '&' : '?').$parameter.'='.rawurlencode($topic);
    }
}

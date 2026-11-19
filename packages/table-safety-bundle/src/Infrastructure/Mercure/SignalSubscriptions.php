<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Mercure;

use Phil\Resource\ResourceReference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\ProtocolVersion;
use Tilttek\TableSafetyBundle\Application\SignalTopics;

/**
 * The subscription of the current viewer to a table's signals: the authorization cookie of the current response is
 * given the topics the viewer reads — the table's, and the game master's for the game master —, and the URL to
 * subscribe to on the hub is returned. Without a hub, there is no URL and the screens stay without live updates.
 */
final readonly class SignalSubscriptions
{
    public function __construct(
        private SignalTopics $topics,
        private ?HubInterface $hub = null,
        private ?Authorization $authorization = null,
        private ?RequestStack $requests = null,
        private ?string $hubName = null,
    ) {
    }

    public function subscribe(ResourceReference $table, bool $gameMaster): ?string
    {
        if (!$this->hub instanceof HubInterface) {
            return null;
        }

        $topics = $this->topics->readBy($table, $gameMaster);
        $request = $this->requests?->getCurrentRequest();

        if ($this->authorization instanceof Authorization && $request instanceof Request) {
            $this->authorization->setCookie($request, $topics, hub: $this->hubName);
        }

        $url = $this->hub->getPublicUrl();
        $parameter = ProtocolVersion::Legacy === $this->hub->getProtocolVersion() ? 'topic' : 'match';
        $query = implode('&', array_map(static fn (string $topic): string => $parameter.'='.rawurlencode($topic), $topics));

        return $url.(str_contains($url, '?') ? '&' : '?').$query;
    }
}

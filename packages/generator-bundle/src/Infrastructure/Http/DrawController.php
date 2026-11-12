<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tilttek\Generator\Exception\GeneratorException;
use Tilttek\Generator\Exception\UnknownGenerator;
use Tilttek\Generator\Generator;
use Tilttek\GeneratorBundle\Application\ApiAccess;
use Tilttek\GeneratorBundle\Application\DrawRequest;
use Tilttek\GeneratorBundle\Application\Exception\AccessDenied;
use Tilttek\GeneratorBundle\Application\Exception\InvalidDrawRequest;

/**
 * The draw API: `GET /{generator}?count=3&unique=1&seed=x` answers the results in JSON, never the lists; a table with a
 * memory takes back the state of its last answer, `&state[day]=3`, and answers the new one. It answers
 * 404 while the API is disabled or for a generator that does not exist, 401 without a token for a generator that is
 * not public, 403 for a generator not open to the client, 400 for parameters it cannot read, 429 past the rate limit,
 * and 422 for a draw that fails, with its cause for a client and without it for a public draw.
 */
final readonly class DrawController
{
    public function __construct(private ApiAccess $access, private Generator $generator, private int $maxCount)
    {
    }

    public function __invoke(Request $request, string $generator): JsonResponse
    {
        if (!$this->access->isEnabled()) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'The draw API is disabled.');
        }

        try {
            $generator = DrawRequest::generator($generator);
            $grant = $this->access->grant($generator, $this->token($request), (string) $request->getClientIp());
            $draw = DrawRequest::from($request->query->all(), $this->maxCount);
        } catch (AccessDenied $denied) {
            return $this->problem($denied->tokenMissing ? Response::HTTP_UNAUTHORIZED : Response::HTTP_FORBIDDEN, $denied->getMessage());
        } catch (InvalidDrawRequest $invalid) {
            return $this->problem(Response::HTTP_BAD_REQUEST, $invalid->getMessage());
        }

        $retryAfter = $grant->retryAfter();

        if (null !== $retryAfter) {
            return $this->problem(Response::HTTP_TOO_MANY_REQUESTS, 'Too many draws.', ['Retry-After' => $retryAfter]);
        }

        try {
            $result = $this->generator->draw($generator, $draw);
        } catch (UnknownGenerator $unknown) {
            return $this->problem(Response::HTTP_NOT_FOUND, $unknown->getMessage());
        } catch (GeneratorException $failure) {
            // A public draw is answered without the names of the lists and templates the failure tells; a client's
            // draw with them, so that its developer finds the cause.
            return $this->problem(Response::HTTP_UNPROCESSABLE_ENTITY, null === $grant->client ? 'The draw failed.' : $failure->getMessage());
        }

        return new JsonResponse($result->toArray(), Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }

    private function token(Request $request): ?string
    {
        $authorization = (string) $request->headers->get('Authorization');

        return str_starts_with($authorization, 'Bearer ') ? substr($authorization, 7) : null;
    }

    /**
     * @param array<string, int|string> $headers
     */
    private function problem(int $status, string $detail, array $headers = []): JsonResponse
    {
        return new JsonResponse(['status' => $status, 'detail' => $detail], $status, ['Content-Type' => 'application/problem+json', 'Cache-Control' => 'no-store'] + $headers);
    }
}

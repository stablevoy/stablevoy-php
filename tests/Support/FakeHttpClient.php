<?php

declare(strict_types=1);

namespace Stablevoy\Tests\Support;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** Answers from a queue (a response or a throwable per call) and records every request it got. */
final class FakeHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface|\Throwable> */
    private array $answers;

    public function __construct(ResponseInterface|\Throwable ...$answers)
    {
        $this->answers = array_values($answers);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $answer = array_shift($this->answers);
        if ($answer === null) {
            throw new \LogicException('FakeHttpClient got more requests than answers');
        }
        if ($answer instanceof \Throwable) {
            throw $answer;
        }
        return $answer;
    }

    public function last(): RequestInterface
    {
        $last = end($this->requests);
        if ($last === false) {
            throw new \LogicException('No request was sent');
        }
        return $last;
    }
}

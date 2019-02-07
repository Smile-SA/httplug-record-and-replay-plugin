<?php

declare(strict_types=1);

namespace Smile\HTTPlugRecordAndReplayPlugin;

use Http\Client\Common\Plugin;
use Http\Client\Common\Plugin\Cache\Generator\CacheKeyGenerator;
use Http\Client\Common\Plugin\Exception\RewindStreamException;
use Http\Promise\FulfilledPromise;
use Http\Promise\Promise;
use Http\Promise\RejectedPromise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\SimpleCache\CacheInterface;

class RecordAndReplayPlugin implements Plugin
{
    /** @var CacheInterface */
    private $recordStorage;

    /** @var CacheKeyGenerator */
    private $recordKeyGenerator;

    /** @var StreamFactoryInterface */
    private $streamFactory;

    /** @var bool */
    private $isRecording;

    public function __construct(
        CacheInterface $recordStorage,
        CacheKeyGenerator $recordKeyGenerator,
        StreamFactoryInterface $streamFactory,
        bool $isRecording = false
    ) {
        $this->recordStorage = $recordStorage;
        $this->recordKeyGenerator = $recordKeyGenerator;
        $this->streamFactory = $streamFactory;
        $this->isRecording = $isRecording;
    }

    public function handleRequest(RequestInterface $request, callable $next, callable $first): Promise
    {
        $recordKey = hash('sha1', $this->recordKeyGenerator->generate($request));
        if (!$this->isRecording) {
            $cachedRecord = $this->recordStorage->get($recordKey);
            if ($cachedRecord === null) {
                return new RejectedPromise(new NoRecordException($recordKey, $request));
            }

            return new FulfilledPromise($this->createResponseFromRecord($cachedRecord));
        }

        return $next($request)->then(function (ResponseInterface $response) use ($recordKey) {
            $bodyStream = $response->getBody();
            $body = $bodyStream->__toString();
            if ($bodyStream->isSeekable()) {
                $bodyStream->rewind();
            } else {
                $response = $response->withBody($this->streamFactory->createStream($body));
            }

            $record = [
                'response' => $response,
                'body' => $body,
            ];
            $this->recordStorage->set($recordKey, $record);

            return $response;
        });
    }

    /**
     * @param array $record
     *
     * @return ResponseInterface
     */
    private function createResponseFromRecord(array $record): ResponseInterface
    {
        /** @var ResponseInterface $response */
        $response = $record['response'];
        $stream = $this->streamFactory->createStream($record['body']);

        try {
            $stream->rewind();
        } catch (\Exception $e) {
            throw new RewindStreamException('Cannot rewind stream.', 0, $e);
        }

        $response = $response->withBody($stream);

        return $response;
    }
}

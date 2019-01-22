<?php

declare(strict_types=1);

namespace Smile\HTTPlugRecordAndReplayPlugin;

use Http\Client\Common\Plugin;
use Http\Client\Common\Plugin\Cache\Generator\CacheKeyGenerator;
use Http\Client\Common\Plugin\Exception\RewindStreamException;
use Http\Message\StreamFactory;
use Http\Promise\FulfilledPromise;
use Http\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\CacheInterface;

class RecordAndReplayPlugin implements Plugin
{
    /** @var bool */
    private $isRecording;
    /** @var CacheInterface */
    private $cachePool;
    /** @var CacheKeyGenerator */
    private $cacheKeyGenerator;
    /** @var StreamFactory */
    private $streamFactory;

    public function __construct(
        CacheInterface $cachePool,
        CacheKeyGenerator $cacheKeyGenerator,
        StreamFactory $streamFactory,
        bool $isRecording = false
    ) {
        $this->cachePool = $cachePool;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->streamFactory = $streamFactory;
        $this->isRecording = $isRecording;
    }

    public function handleRequest(RequestInterface $request, callable $next, callable $first): Promise
    {
        $recordKey = hash('sha1', $this->cacheKeyGenerator->generate($request));
        if (!$this->isRecording) {
            $cachedRecord = $this->cachePool->get($recordKey);
            if ($cachedRecord === null) {
                throw new NoRecordException($recordKey, $request);
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
            $this->cachePool->set($recordKey, $record);

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

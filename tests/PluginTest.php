<?php

declare(strict_types=1);

use Cache\Adapter\PHPArray\ArrayCachePool;
use Http\Client\Common\Plugin\Cache\Generator\SimpleGenerator;
use Http\Client\Common\PluginClient;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Discovery\StreamFactoryDiscovery;
use Http\Mock\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Smile\HTTPlugRecordAndReplayPlugin\NoRecordException;
use Smile\HTTPlugRecordAndReplayPlugin\RecordAndReplayPlugin;

class PluginTest extends TestCase
{
    public function testItPlugsIn()
    {
        $firstActualClient = new Client();

        $fakeBody =$this->createMock(StreamInterface::class);
        $fakeBody->method('__toString')->willReturn('Hello world !');
        $fakeBody->method('isSeekable')->willReturn(true);

        $responseToBeRecorded = $this->createMock(ResponseInterface::class);
        $responseToBeRecorded->method('getBody')->willReturn($fakeBody);
        $responseToBeRecorded->method('withBody')->willReturnSelf();

        $firstActualClient->addResponse($responseToBeRecorded);

        $cachePool = new ArrayCachePool();
        $cacheKeyGenerator = new SimpleGenerator();
        $streamFactory = StreamFactoryDiscovery::find();
        $messageFactory = MessageFactoryDiscovery::find();

        $pluginOnRecordMode = new RecordAndReplayPlugin(
            $cachePool,
            $cacheKeyGenerator,
            $streamFactory,
            true
        );

        $clientOnRecordMode = new PluginClient($firstActualClient, [$pluginOnRecordMode]);

        $actualResponse = $clientOnRecordMode->sendRequest(
            $messageFactory->createRequest('GET', 'http://dummy.com')
        );

        self::assertSame($actualResponse, $responseToBeRecorded);

        $secondActualClient = new Client();
        $anotherResponse = $this->createMock(ResponseInterface::class);
        $secondActualClient->addResponse($anotherResponse);

        $pluginOnReplayMode = new RecordAndReplayPlugin(
            $cachePool,
            $cacheKeyGenerator,
            $streamFactory
        );

        $clientOnReplayMode = new PluginClient($secondActualClient, [$pluginOnReplayMode]);

        $actualResponse = $clientOnReplayMode->sendRequest(
            $messageFactory->createRequest('GET', 'http://dummy.com')
        );

        self::assertSame($actualResponse, $responseToBeRecorded);

        self::assertSame(0, count($secondActualClient->getRequests()));

        self::expectException(NoRecordException::class);
        $clientOnReplayMode->sendRequest(
            $messageFactory->createRequest('GET', 'http://not-dummy.com')
        );
    }
}

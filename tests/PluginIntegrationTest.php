<?php

declare(strict_types=1);

use Cache\Adapter\PHPArray\ArrayCachePool;
use Http\Client\Common\Plugin\Cache\Generator\SimpleGenerator;
use Http\Client\Common\PluginClient;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Mock\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\SimpleCache\CacheInterface;
use Smile\HTTPlugRecordAndReplayPlugin\NoRecordException;
use Smile\HTTPlugRecordAndReplayPlugin\IncrementalCacheKeyGeneratorDecorator;
use Smile\HTTPlugRecordAndReplayPlugin\RecordAndReplayPlugin;
use Smile\HTTPlugRecordAndReplayPlugin\RecordSuiteIdentifiersGenerator;

/**
 * @covers Smile\HTTPlugRecordAndReplayPlugin\RecordAndReplayPlugin
 * @covers Smile\HTTPlugRecordAndReplayPlugin\IncrementalCacheKeyGeneratorDecorator
 * @covers Smile\HTTPlugRecordAndReplayPlugin\NoRecordException
 */
class PluginIntegrationTest extends TestCase
{
    /** @var CacheInterface */
    private static $cachePool;

    /**
     * @before
     */
    public function setUpCachePool()
    {
        self::$cachePool = new ArrayCachePool();
    }

    public function testItCanRecordAndReplay()
    {
        $firstActualClient = new Client();

        $responseToBeRecorded = $this->createMockResponse();
        $firstActualClient->addResponse($responseToBeRecorded);

        $clientOnRecordMode = $this->decorateClientWithThePlugin($firstActualClient, true);

        $actualResponse = $clientOnRecordMode->sendRequest(
            self::createRequest('GET', 'http://dummy.com')
        );

        self::assertSame(
            $responseToBeRecorded,
            $actualResponse,
            'The client responded an unexpected response.'
        );

        $secondActualClient = new Client();
        $anotherResponse = $this->createMock(ResponseInterface::class);
        $secondActualClient->addResponse($anotherResponse);

        $clientOnReplayMode = $this->decorateClientWithThePlugin($secondActualClient);

        $actualResponse = $clientOnReplayMode->sendRequest(
            self::createRequest('GET', 'http://dummy.com')
        );

        self::assertSame(
            $responseToBeRecorded,
            $actualResponse,
            'The client did not responded the recorded response.'
        );

        self::assertCount(
            0,
            $secondActualClient->getRequests(),
            'The real client has actually been used.'
        );
    }

    public function testItCanOverrideRecords()
    {
        $mockClient = new Client();
        $responseToBeRecorded = $this->createMockResponse();
        $mockClient->addResponse($responseToBeRecorded);
        $anotherResponseToBeRecorded = $this->createMockResponse();
        $mockClient->addResponse($anotherResponseToBeRecorded);

        $clientOnRecordMode = $this->decorateClientWithThePlugin($mockClient, true);
        $anotherClientOnRecordMode = $this->decorateClientWithThePlugin($mockClient, true);
        $clientOnReplayMode = $this->decorateClientWithThePlugin($mockClient);
        $anotherClientOnReplayMode = $this->decorateClientWithThePlugin($mockClient);

        // Make the first request - should be recorded
        $actualResponseFromRecordMode = $clientOnRecordMode->sendRequest(
            self::createRequest('GET', 'http://dummy.com')
        );
        self::assertSame(
            $responseToBeRecorded,
            $actualResponseFromRecordMode,
            'The client responded an unexpected response.'
        );

        // Replay the first request
        $actualResponseFromReplayMode = $clientOnReplayMode->sendRequest(
            self::createRequest('GET', 'http://dummy.com')
        );
        self::assertSame(
            $responseToBeRecorded,
            $actualResponseFromReplayMode,
            'The client did not responded the recorded response.'
        );

        // Make an second but identical request - should overwrite the previous record
        $newActualResponseFromRecordMode = $anotherClientOnRecordMode->sendRequest(
            self::createRequest('GET', 'http://dummy.com')
        );
        self::assertNotSame(
            $responseToBeRecorded,
            $newActualResponseFromRecordMode,
            'The client replayed the request.'
        );

        self::assertSame(
            $anotherResponseToBeRecorded,
            $newActualResponseFromRecordMode,
            'The client responded an unexpected response.'
        );

        // Replay the second request
        $newActualResponseFromReplayMode = $anotherClientOnReplayMode->sendRequest(
            self::createRequest('GET', 'http://dummy.com')
        );
        self::assertSame(
            $anotherResponseToBeRecorded,
            $newActualResponseFromReplayMode,
            'The client did not respond the recorded response.'
        );
    }

    public function testItCanRecordSeveralResponsesForTheSameRequestMadeContinuously()
    {
          $mockClient = new Client();
          $responseToBeRecorded = $this->createMockResponse();
          $mockClient->addResponse($responseToBeRecorded);
          $anotherResponseToBeRecorded = $this->createMockResponse();
          $mockClient->addResponse($anotherResponseToBeRecorded);

          $clientOnRecordMode = $this->decorateClientWithThePlugin($mockClient, true);
          $clientOnReplayMode = $this->decorateClientWithThePlugin($mockClient);

          // Make the first request - should be recorded
          $actualResponseFromRecordMode = $clientOnRecordMode->sendRequest(
              self::createRequest('GET', 'http://dummy.com')
          );
          self::assertSame(
              $responseToBeRecorded,
              $actualResponseFromRecordMode,
              'The client responded an unexpected response.'
          );

          // Make the second identical request - shoud be recorded too
          $secondActualResponseFromRecordMode = $clientOnRecordMode->sendRequest(
              self::createRequest('GET', 'http://dummy.com')
          );
          self::assertSame(
              $anotherResponseToBeRecorded,
              $secondActualResponseFromRecordMode,
              'The client responded an unexpected response.'
          );

          // Replay the first request
          $actualResponseFromReplayMode = $clientOnReplayMode->sendRequest(
              self::createRequest('GET', 'http://dummy.com')
          );
          self::assertSame(
              $responseToBeRecorded,
              $actualResponseFromReplayMode,
              'The client did not responded the recorded response.'
          );

          // Replay the second request
          $secondActualResponseFromReplayMode = $clientOnReplayMode->sendRequest(
              self::createRequest('GET', 'http://dummy.com')
          );
          self::assertSame(
              $anotherResponseToBeRecorded,
              $secondActualResponseFromReplayMode,
              'The client did not respond the recorded response.'
          );
    }

    public function testItCanRecordSeveralResponsesForTheSameRequestMadeInDifferentContexts()
    {
          $firstMockClient = new Client();
          $responseToBeRecorded = $this->createMockResponse();
          $firstMockClient->addResponse($responseToBeRecorded);

          $secondMockClient = new Client();
          $anotherResponseToBeRecorded = $this->createMockResponse();
          $secondMockClient->addResponse($anotherResponseToBeRecorded);

          $firstClientOnRecordMode = $this->decorateClientWithThePlugin($firstMockClient, true);
          $firstClientOnReplayMode = $this->decorateClientWithThePlugin($firstMockClient);

          $secondClientOnRecordMode = $this->decorateClientWithThePlugin($secondMockClient, true, 'second_client');
          $secondClientOnReplayMode = $this->decorateClientWithThePlugin($secondMockClient, false, 'second_client');

          // Make the first request - should be recorded
          $actualResponseFromRecordMode = $firstClientOnRecordMode->sendRequest(
              self::createRequest('GET', 'http://dummy.com')
          );
          self::assertSame(
              $responseToBeRecorded,
              $actualResponseFromRecordMode,
              'The client responded an unexpected response.'
          );

          // Make the second identical request - shoud be recorded too
          $secondActualResponseFromRecordMode = $secondClientOnRecordMode->sendRequest(
              self::createRequest('GET', 'http://dummy.com')
          );
          self::assertSame(
              $anotherResponseToBeRecorded,
              $secondActualResponseFromRecordMode,
              'The client responded an unexpected response.'
          );

          // Replay the first request
          $actualResponseFromReplayMode = $firstClientOnReplayMode->sendRequest(
              self::createRequest('GET', 'http://dummy.com')
          );
          self::assertSame(
              $responseToBeRecorded,
              $actualResponseFromReplayMode,
              'The client did not responded the recorded response.'
          );

          // Replay the second request
          $secondActualResponseFromReplayMode = $secondClientOnReplayMode->sendRequest(
              self::createRequest('GET', 'http://dummy.com')
          );
          self::assertSame(
              $anotherResponseToBeRecorded,
              $secondActualResponseFromReplayMode,
              'The client did not respond the recorded response.'
          );
    }

    public function testItRejectsTheRequestWhenTheRecordDoesNotExist()
    {
        $mockClientWithNoResponse = new Client();
        $clientOnReplayMode = $this->decorateClientWithThePlugin($mockClientWithNoResponse);


        self::expectException(
            NoRecordException::class,
            'The plugin did not throw an exception on an unknown request.'
        );
        $clientOnReplayMode->sendRequest(
            self::createRequest('GET', 'http://not-dummy.com')
        );
    }

    private function createMockResponse(): ResponseInterface
    {
        $fakeBody =$this->createMock(StreamInterface::class);
        $fakeBody->method('__toString')->willReturn(md5(random_bytes(16)));
        $fakeBody->method('isSeekable')->willReturn(true);

        /** @var ResponseInterface $mockResponse */
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getBody')->willReturn($fakeBody);
        $mockResponse->method('withBody')->willReturnSelf();

        return $mockResponse;
    }

    private static function createRequest(string $method, string $uri): RequestInterface
    {
        $requestFactory = Psr17FactoryDiscovery::findRequestFactory();

        return $requestFactory->createRequest($method, $uri);
    }

    private static function decorateClientWithThePlugin(ClientInterface $actualClient, bool $shouldBeRecording = false, string $rootRecordKey = ''): ClientInterface
    {
        $cacheKeyGenerator = new RecordSuiteIdentifiersGenerator(new SimpleGenerator());
        $streamFactory = Psr17FactoryDiscovery::findStreamFactory();

        $pluginOnRecordMode = new RecordAndReplayPlugin(
            self::$cachePool,
            $cacheKeyGenerator,
            $streamFactory,
            $shouldBeRecording
        );

        if($rootRecordKey !== '') {
            $cacheKeyGenerator->updateRecordKey($rootRecordKey);
        }

        return new PluginClient($actualClient, [$pluginOnRecordMode]);
    }
}

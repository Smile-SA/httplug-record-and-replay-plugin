## Vanilla PHP

Given the following script :

```php
// The first dependency is the record storage. It must implement the `Psr\SimpleCache\CacheInterface` interface (*PSR-16*).
// One of the available option is the `symfony/cache` package :
$recordStorage = new Symfony\Component\Cache\Simple\FilesystemCache('', 0, __DIR__.'/httplug_records');

// Second, the record identifier generator implementing the `Http\Client\Common\Plugin\Cache\Generator\CacheKeyGenerator` interface.
// In order to have contextual record keys (and avoid collision between records that are about the same remote resource), you are advised to use the built-in record identifier generator :
$recordIdentifierGenerator = new Smile\HTTPlugRecordAndReplayPlugin\RecordSuiteIdentifiersGenerator(
    new Http\Client\Common\Plugin\Cache\Generator\SimpleGenerator()
);

// Last, a `Psr\Http\Message\StreamInterface\StreamFactory` implementing stream factory (PSR-17).
// You can leverage the `php-http/discovery` : 
$streamFactory = Http\Discovery\Psr17FactoryDiscovery::findStreamFactory();

// Finally you can make the plugin instance and wrap the actual HTTPlug client :
$plugin = new Smile\HTTPlugRecordAndReplayPlugin\RecordAndReplayPlugin(
    $recordStorage,
    $recordIdentifierGenerator,
    $streamFactory,
    getenv('HTTPLUG_RECORDING')
);

$client = new Http\Client\Common\PluginClient($client, [$plugin]);

// Create the actual client :
$client = new Http\Client\HttpClient();

// Make the request :
$request = Psr17FactoryDiscovery::findRequestFactory()->createRequest('GET', 'https://api.somewhere/some_endpoint');
$client->sendRequest($request);
```

First run this script with `HTTPLUG_RECORDING=0` environment variable.
Then, update the run the script again the plugin will replay the recorded communications without actually calling the remote service.

#### Contextual cache keys

When requesting the same resource with the same HTTP verb in several contexts (eg. in different test cases), you may want to a different results. To achieve this "contextualization", you need to construct the plugin with a random `HTTPLUG_RECORDING` variable. This will force the plugin to generate a new set of records - beware of records duplications !

#### Incremental cache keys

When requesting the same resource with the same HTTP verb in the same context (meaning, with the same instance of the plugin), but expecting a different result, you will want to decorate the `$cacheKeyGenerator` argument given in the plugin constructor :

```php
/** @var Smile\HTTPlugRecordAndReplayPlugin\IncrementalCacheKeyGeneratorDecorator $cacheKeyGenerator */
$cacheKeyGenerator = new IncrementalCacheKeyGeneratorDecorator(cacheKeyGenerator);
```

[symfony/cache](https://packagist.org/packages/symfony/cache)

[php-http/discovery](https://packagist.org/packages/php-http/discovery)

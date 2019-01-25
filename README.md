# Record And Replay Plugin

[![Latest Version](https://img.shields.io/packagist/v/smile/httplug-record-and-replay-plugin.svg?style=flat-square)](https://github.com/Smile-SA/httplug-record-and-replay-plugin/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build Status](https://img.shields.io/travis/Smile-SA/httplug-record-and-replay-plugin.svg?style=flat-square)](https://travis-ci.org/Smile-SA/httplug-record-and-replay-plugin)
[![Code Coverage](https://img.shields.io/scrutinizer/coverage/g/Smile-SA/httplug-record-and-replay-plugin.svg?style=flat-square)](https://scrutinizer-ci.com/g/Smile-SA/httplug-record-and-replay-plugin)
[![Quality Score](https://img.shields.io/scrutinizer/g/Smile-SA/httplug-record-and-replay-plugin.svg?style=flat-square)](https://scrutinizer-ci.com/g/Smile-SA/httplug-record-and-replay-plugin)
<!--
[![Total Downloads](https://img.shields.io/packagist/dt/smile/httplug-record-and-replay-plugin.svg?style=flat-square)](https://packagist.org/packages/smile/httplug-record-and-replay-plugin)
-->

**Record and Replay plugin for HTTPlug.**


## Install

Via Composer

``` bash
$ composer require smile/httplug-record-and-replay-plugin
```


## Usage

### Vanilla PHP

```php
/** @var Http\Client\HttpClient $client */
$client = new Client();

/** @var Psr\SimpleCache\CacheInterface $cachePool */
/** @var Http\Client\Common\Plugin\Cache\Generator\CacheKeyGenerator $cacheKeyGenerator */
/** @var Psr\Http\Message\StreamInterface\StreamFactory $streamFactory */
$plugin = new RecordAndReplayPlugin(
    $cachePool,
    $cacheKeyGenerator,
    $streamFactory,
    getenv('HTTPLUG_RECORDING')
);

/** @var Http\Client\Common\PluginClient $client */
$client = new PluginClient($client, [$plugin]);
```

### HTTPlug Bundle for Symfony

Declare the plugin :
```yaml
# config/services.yaml
services:
    Smile\HTTPlugRecordAndReplayPlugin\RecordAndReplayPlugin:
        public: false
        arguments:
            $cachePool: '@app.simple_cache.httplug_records'
            $isRecording: true

    app.simple_cache.httplug_records:
        public: false
        class: Symfony\Component\Cache\Simple\FilesystemCache
        arguments:
            $directory: '%kernel.project_dir%/tests/httplug_records'
```

Plug it to your client(s) :
```yaml
# config/packages/test/httplug.yaml
httplug:
    clients:
        default:
            plugins:
                - 'Smile\HTTPlugRecordAndReplayPlugin\RecordAndReplayPlugin'
```

Run your test-suite and you should see the `tests/httplug_records` folder being filled with cache files representing your records.

Once the test-suite is green, you can remove the `$isRecording` line from your service definition and commit all the records along with the updated `*.yaml` and `composer.json`.

## Testing

``` bash
$ composer update --prefer-stable
$ ./vendor/bin/phpunit
```


## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

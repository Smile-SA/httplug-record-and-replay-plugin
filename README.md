# Record And Replay Plugin for HTTPlug

[![Latest Version](https://img.shields.io/packagist/v/smile/httplug-record-and-replay-plugin.svg?style=flat-square)](https://github.com/Smile-SA/httplug-record-and-replay-plugin/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build Status](https://img.shields.io/travis/Smile-SA/httplug-record-and-replay-plugin/master.svg?style=flat-square)](https://travis-ci.org/Smile-SA/httplug-record-and-replay-plugin)
[![Code Coverage](https://img.shields.io/scrutinizer/coverage/g/Smile-SA/httplug-record-and-replay-plugin/master.svg?style=flat-square)](https://scrutinizer-ci.com/g/Smile-SA/httplug-record-and-replay-plugin)
[![Quality Score](https://img.shields.io/scrutinizer/g/Smile-SA/httplug-record-and-replay-plugin/master.svg?style=flat-square)](https://scrutinizer-ci.com/g/Smile-SA/httplug-record-and-replay-plugin)
<!--
[![Total Downloads](https://img.shields.io/packagist/dt/smile/httplug-record-and-replay-plugin.svg?style=flat-square)](https://packagist.org/packages/smile/httplug-record-and-replay-plugin)
-->

**Achieve isolated test-suites and predictable HTTP communications.**

## Install

Via Composer

``` bash
$ composer require --prefer-stable smile/httplug-record-and-replay-plugin
```


## Usage

 - Vanilla PHP usage
 - Using the HTTPlugBundle and the Symfony WebTestCase
 - Using Symfony Panther (or other end-to-end test framework)

### HTTPlug Bundle for Symfony

Given the following test-case
```php
<?php

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SomeControllerTest extends WebTestCase
{
    /**
     * The `/some-route` page uses a remote webservice to generate its content
     */
    public function testItRespondWell()
    {
       $client = self::createClient();
       $crawler = $client->request('GET', '/some-page');

       self::assertContains(
           'Some content that depend on the webservice response.',
           $crawler->filter('body')->text()
       );
    }
}
```

Declare the plugin, the record key generator and the *PSR-16* storage :

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true
        public: false

    Smile\HTTPlugRecordAndReplayPlugin\RecordAndReplayPlugin:
        arguments:
            $recordStorage: '@app.simple_cache.httplug_records'
            $recordIdentifierGenerator: '@Smile\HTTPlugRecordAndReplayPlugin\RecordSuiteIdentifiersGenerator'
            $isRecording: true

    Smile\HTTPlugRecordAndReplayPlugin\RecordSuiteIdentifiersGenerator:
        arguments:
            $innerGenerator: '@Http\Client\Common\Plugin\Cache\Generator\CacheKeyGenerator'
        calls:
            - [updateRecordKey, ['%env(HTTPLUG_RECORDING_CONTEXT)%']]

    app.simple_cache.httplug_records:
        class: Symfony\Component\Cache\Simple\FilesystemCache
        arguments:
            $directory: '%kernel.project_dir%/tests/httplug_records'
```

Plug it in your client(s) :
```yaml
# config/packages/test/httplug.yaml
httplug:
    clients:
        default:
            plugins:
                - 'Smile\HTTPlugRecordAndReplayPlugin\RecordAndReplayPlugin'
```
You can now run your test case : `phpunit --filter SomeControllerTest::testItRespondWell` (note the `--filter` option that will prevent the other records to be overridden).

When the test has passed, you should see the new record created in the `tests/httplug_records` folder.

You can then remove the `$isRecording` line from your service definition and commit all the records along with the updated configuration and *composer* files.

Later on, when adding other behaviors based on third-party requests, you can switch back to the *record* mode (by putting back the `$isRecording: true` in the plugin service definition) and run only the new tests in order to avoid rewriting all your records.

#### Autowiring troubleshooting

Depending on the dependencies versions used on your application, you may have to declare some additional services :

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true
        public: false

    # PSR-17 and PSR-18 autowiring compat
    Psr\Http\Client\ClientInterface: '@Http\Client\HttpClient'
    Psr\Http\Message\RequestFactoryInterface: '@Http\Factory\Guzzle\RequestFactory'
    Http\Factory\Guzzle\RequestFactory: ~

    # HTTPlug record/replay plugin & records storage
    Http\Client\Common\Plugin\Cache\Generator\CacheKeyGenerator:
        class: Http\Client\Common\Plugin\Cache\Generator\SimpleGenerator
```

### Records contextualization

When recording some client-server interaction, you may want to issue the same request several times while expecting the responses to be different.
To enable such a behavior, the plugin uses record identifiers that are dependent of already recorded calls. This is called *tree-based record key generation*.

Exemple :
```php
/** @var */
$listElementsRequest = $requestFactory->createRequest('GET', 'https://api.somewhere/some_elements');
$elementsList = $client->sendRequest($listElementsRequest); // $elementList is expected to be empty

$insertElementRequest = $requestFactory->createRequest('POST', 'https://api.somewhere/some_elements', [/*...*/]);
$client->sendRequest($listElementsRequest);

$elementsList = $client->sendRequest($listElementsRequest); // $elementList is expected to contain the POSTed element
```

But sometimes, the history of made calls is not enough to isolate different records.
The plugin allows you to explicitly customize the record tree in order to have a completly different set of records even when requesting the same resources in the same order.

```php
$listElementsRequest = $requestFactory->createRequest('GET', 'https://api.somewhere/some_elements');
$elementsList = $client->sendRequest($listElementsRequest); // $elementList is expected to be empty

$insertElementRequest = $requestFactory->createRequest('POST', 'https://api.somewhere/some_elements', [/*...*/]);
$client->sendRequest($listElementsRequest);

$elementsList = $client->sendRequest($listElementsRequest); // $elementList is expected to contain the POSTed element

// Some state changing condition (eg. )
// ...

$recordKeyGenerator->
```

## Contributing and testing

``` bash
$ composer update --prefer-lowest
$ ./vendor/bin/phpunit
```

**Please maintain the test suite : if you add a feature, prove the new behavior; if you fix a bug, ensure the non-regression.**

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

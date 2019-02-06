<?php

declare(strict_types=1);

namespace Smile\HTTPlugRecordAndReplayPlugin;

use Psr\Http\Message\RequestInterface;
use Http\Client\Common\Plugin\Cache\Generator\CacheKeyGenerator;

class RecordSuiteIdentifiersGenerator implements CacheKeyGenerator
{
    private $innerGenerator;
    private $lastRecordKey = 'root';

    public function __construct(CacheKeyGenerator $innerGenerator)
    {
        $this->innerGenerator = $innerGenerator;
    }

    public function generate(RequestInterface $request)
    {
        return $this->updateRecordKey($this->innerGenerator->generate($request));
    }

    public function updateRecordKey(string $newRecordKey)
    {
        $key = sprintf(
            '%s_%s',
            $newRecordKey,
            $this->lastRecordKey
        );

        $this->lastRecordKey = hash('sha1', $key);

        return $key;
    }
}

<?php

declare(strict_types=1);

namespace Smile\HTTPlugRecordAndReplayPlugin;

use Psr\Http\Message\RequestInterface;
use Http\Client\Common\Plugin\Cache\Generator\CacheKeyGenerator;

class RecordSuiteIdentifiersGenerator implements CacheKeyGenerator
{
//    private const ENV_VAR_NAME = 'HTTPLUG_RECORDING_CONTEXT_LOCAL';

    private $innerGenerator;
    private $lastRecordKey = 'root';

    public function __construct(CacheKeyGenerator $innerGenerator)
    {
        $this->innerGenerator = $innerGenerator;
//        $lastRecordKey = getenv(static::ENV_VAR_NAME);
//        if($lastRecordKey !== false){
//            $this->lastRecordKey = $lastRecordKey;
//        }
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
//        putenv(sprintf('%s=%s', static::ENV_VAR_NAME, $this->lastRecordKey));

        return $key;
    }
}

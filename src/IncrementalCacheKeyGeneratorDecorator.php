<?php

declare(strict_types=1);

namespace Smile\HTTPlugRecordAndReplayPlugin;

use Psr\Http\Message\RequestInterface;
use Http\Client\Common\Plugin\Cache\Generator\CacheKeyGenerator;

class IncrementalCacheKeyGeneratorDecorator implements CacheKeyGenerator
{
    private $innerGenerator;
    private $keysIndex = [];

    public function __construct(CacheKeyGenerator $innerGenerator)
    {
        $this->innerGenerator = $innerGenerator;
    }

    public function generate(RequestInterface $request)
    {
        $key = $this->innerGenerator->generate($request);
        if (!array_key_exists($key, $this->keysIndex)) {
            $this->keysIndex[$key] = 0;
        }

        $this->keysIndex[$key]++;

        return $key.'_'.$this->keysIndex[$key];
    }
}

<?php

declare(strict_types=1);

namespace Smile\HTTPlugRecordAndReplayPlugin;

use Http\Client\Exception\RequestException;
use Psr\Http\Message\RequestInterface;

class NoRecordException extends RequestException
{
    /**
     * @param string           $recordKey
     * @param RequestInterface $request
     */
    public function __construct(string $recordKey, RequestInterface $request)
    {
        parent::__construct(sprintf('No record found for "%s".', $recordKey), $request);
    }
}

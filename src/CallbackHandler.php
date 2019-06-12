<?php

namespace LeonMelis\UQ_free;

use stdClass;

/**
 * Class CallbackHandler
 * @package LeonMelis\UQ_free
 */
class CallbackHandler {
    use Cacheable;

    /**
     * CallbackHandler constructor.
     * @param CacheInterface|null $cache
     */
    public function __construct(CacheInterface $cache = null) {
        $this->cacheHandler = $cache;
    }

    /**
     * @param $data
     * @return stdClass
     * @throws UQException
     */
    private static function readCallback($data) {
        if (is_string($data)) {
            if (null === $data = json_decode($data)) {
                throw new UQException('Cannot decode JSON payload: ' . json_last_error_msg(), json_last_error());
            }
        }

        if (!property_exists($data, 'callback')) {
            throw new UQException('No callback object in payload');
        }

        return $data->callback;
    }

    /**
     * @param stdClass|string $data the POST data from the callback
     * @throws UQException
     */
    public function handleCallback($data) {
        $callback = self::readCallback($data);

        if (!property_exists($callback, 'type')) {
            throw new UQException('Expected property \'type\' in callback');
        }

        if (!property_exists($callback, 'uuid')) {
            throw new UQException('Expected property \'uuid\' in callback');
        }

        $this->cacheWrite($data->type, $data->uuid, $data);
    }
}
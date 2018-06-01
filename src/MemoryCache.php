<?php

namespace LeonMelis\UQ_free;

/**
 * Class MemoryCache
 *
 * A very simple memory based caching.
 *
 * Serves as a default caching mechanism for the Connector class.
 */
class MemoryCache implements CacheInterface {
    private $data = [];

    /**
     * @param string $type
     * @param string $uuid
     * @return null|\stdClass
     */
    public function read($type, $uuid) {
        if (array_key_exists($type, $this->data) && array_key_exists($uuid, $this->data[$type])) {
            // Cache hit
            return $this->data[$type][$uuid];
        }

        // Cache miss
        return null;
    }

    /**
     * @param string $type
     * @param string $uuid
     * @param \stdClass $data
     */
    public function write($type, $uuid, $data) {
        $this->data[$type][$uuid] = $data;
    }
}

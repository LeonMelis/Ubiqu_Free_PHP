<?php

namespace LeonMelis\UQ_free;

/**
 * Interface CacheInterface
 *
 * The interface for a caching mechanism
 *
 * @package LeonMelis\UQ_free
 */
interface CacheInterface {
    /**
     * Read from cache.
     *
     * on a cache miss this method should return null
     * on a cache hit, it should return an iterable such as a stdClass or a associative array
     *
     * @param string $type the type of object we are looking for, such as 'asset' or 'serviceprovider'
     * @param string $uuid the UUID of the object we are looking for
     * @return null|\stdClass
     */
    public function read($type, $uuid);

    /**
     * Write data to the cache.
     *
     * @param string $type the type of UQ object, such as 'asset' or 'serviceprovider'
     * @param string $uuid
     * @param \stdClass $data
     */
    public function write($type, $uuid, $data);
}
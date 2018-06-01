<?php

namespace LeonMelis\UQ_free;

/**
 * Trait Cacheable
 * @package LeonMelis\UQ_free
 */
trait Cacheable {
    /**
     * @var CacheInterface $cacheHandler
     */
    private $cacheHandler;

    /**
     * Attempt to fetch from user defined cache function
     *
     * @param string $type
     * @param string $uuid
     * @return null|\stdClass
     */
    private function cacheRead($type, $uuid) {
        if (!$this->cacheHandler) {
            return null;
        }

        return $this->cacheHandler->read($type, $uuid);
    }

    /**
     * @param string $type
     * @param string $uuid
     * @param \stdClass $data
     */
    private function cacheWrite($type, $uuid, $data) {
        if (!$this->cacheHandler) {
            return;
        }

        $this->cacheHandler->write($type, $uuid, $data);
    }
}
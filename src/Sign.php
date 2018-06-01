<?php

namespace LeonMelis\UQ_free;

/**
 * Class Sign
 * @package LeonMelis\UQ_free
 */
class Sign extends AssetRequest {
    use Verifiable;

    /**
     * @return string
     * @throws UQException if signature could not be verified
     */
    function getSignature() {
        $this->verify();

        return $this->signature;
    }
}
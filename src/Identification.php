<?php

namespace LeonMelis\UQ_free;

/**
 * Class Identification
 * @package LeonMelis\UQ_free
 */
class Identification extends UQObject {
    const IDENTIFICATION_STATE_CREATED = 0;
    const IDENTIFICATION_STATE_CONSUMED = 1;
    const IDENTIFICATION_STATE_EXPIRED = 2;

    protected $nonce;
    protected $nonce_formatted;

    protected $asset_uuid;

    /**
     * @return string
     */
    public function getNonce() {
        return $this->nonce;
    }

    /**
     * @return string
     */
    public function getNonceFormatted() {
        return $this->nonce_formatted;
    }

    /**
     * @return integer
     */
    public function getStatusCode() {
        return $this->status_code;
    }

    /**
     * @return string
     */
    public function getStatusText() {
        return $this->status_text;
    }

    /**
     * @return Asset
     */
    public function fetchAsset() {
        return new Asset($this->asset_uuid, $this->connector);
    }
}
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
     * @var string $appapi the app-to-app API URL scheme
     */
    protected $appapi;

    /**
     * @var string $qrcode the BASE64 encoded QR code
     */
    protected $qrcode;

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

    /**
     * @return string the app-API scheme URI
     */
    public function getAppAPI() {
        return $this->appapi;
    }

    /**
     * @return string the QR code as PNG image
     * @throws UQException if image could not be BASE64 decoded
     */
    public function getQRCodePNG() {
        $png = base64_decode($this->qrcode, true);

        if ($png === false) {
            throw new UQException('Cannot BASE64 decode QR-code PNG image');
        }

        return $png;
    }


    /**
     * @return string the BASE64 encoded QR code PNG image
     */
    public function getQRCodeB64() {
        return $this->qrcode;
    }
}
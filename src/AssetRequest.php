<?php

namespace LeonMelis\UQ_free;

/**
 * Class AssetRequest
 * @package LeonMelis\UQ_free
 */
abstract class AssetRequest extends UQObject {
    const ASSET_REQUEST_STATE_PREPARED = 0;
    const ASSET_REQUEST_STATE_CREATED = 1;
    const ASSET_REQUEST_STATE_ACCEPTED = 2;
    const ASSET_REQUEST_STATE_REJECTED = 3;
    const ASSET_REQUEST_STATE_EXPIRED = 4;
    const ASSET_REQUEST_STATE_FAILED = 5;

    /**
     * @var string $token
     */
    protected $token;

    /**
     * @var string $otp
     */
    protected $otp;

    /**
     * @var string $asset_uuid
     */
    protected $asset_uuid;

    /**
     * @var bool $verified
     */
    protected $verified;

    /**
     * @var string $fingerprint
     */
    protected $fingerprint;

    /**
     * @var string $signature
     */
    protected $signature;

    /**
     * @var string $nonce
     */
    protected $nonce;

    /**
     * @var string $nonce_formatted
     */
    protected $nonce_formatted;

    /**
     * @var bool $notify
     */
    protected $notify;

    /**
     * @return Asset
     */
    public function getAsset() {
        return new Asset($this->asset_uuid, $this->connector);
    }

    /**
     * @return bool
     */
    public function isAccepted() {
        return $this->status_code === self::ASSET_REQUEST_STATE_ACCEPTED;
    }

    /**
     * FOR DEBUGGING ONLY
     *
     * Poll the Ubiqu API server for response on this AssetRequest.
     * This is useful for local debugging, but should never be used in production (use callbacks instead)
     *
     * @throws UQException if fetch fails
     */
    public function debugPollForResponse() {
        while (in_array($this->status_code, [self::ASSET_REQUEST_STATE_CREATED, self::ASSET_REQUEST_STATE_PREPARED], true)) {
            echo "Waiting for response on asset request {$this->uuid} (state = {$this->status_code}: '{$this->status_text}')\n";
            sleep(1);
            $this->fetch(true);
        }
    }
}
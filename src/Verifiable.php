<?php

namespace LeonMelis\UQ_free;

use phpseclib\Crypt\Hash;
use phpseclib\Crypt\Random;

/**
 * Trait Verifiable
 * @package LeonMelis\UQ_free
 */
trait Verifiable {
    /**
     * @var string $request_nonce
     */
    protected $request_nonce;

    /**
     * @var string|null $data internally stored data to verify with.
     */
    private $data;

    /**
     * @var string $resource_uri the URI pointing to the resource to be signed.
     * This can be the URL of the website to authenticate or a URL to a document to be signed.
     */
    public $resource_uri;

    /**
     * Verify that the signature returned by the Ubiqu Free API is signed with the private
     * key controlled by the user.
     *
     * @return bool true if valid
     * @throws UQException if signature could not be verified
     */
    public function verify() {
        /**
         * NOTE: PHP linters such as PHPStorm won't know the internal type of $asset,
         * because this trait may not have the getAsset method. So we set it here
         * explicitly.
         * @var Asset $asset
         */
        $asset = $this->getAsset();

        $result = $asset->getRSA()->verify($this->data, hex2bin($this->signature));

        if ($result === false) {
            throw new UQException('Could not verify signature');
        }

        return $result;
    }

    /**
     * Set the data to be verified, if NULL, we generate a random.
     *
     * NOTE: We could also send NULL as data to Ubiqu, in that case the Ubiqu API will
     * generate a secure random value for us, but it always returns the SHA256 hash, not
     * the original value. This was a problem when this library was still using openssl
     * because openssl_verify() has no option to use raw input, it ALWAYS hashes the data
     * prior to verification
     *
     * @param string|null $data
     */
    public function setData($data = null) {
        if ($data === null) {
            $this->data = (new Random())::string(32);
        } else {
            $this->data = $data;
        }
    }

    /**
     * Get fingerprint in format that Ubiqu free API expects, which is a SHA256 digest of the data
     *
     * @return string
     */
    public function getFingerPrint() {
        return (new Hash('sha256'))->hash($this->data);
    }
}
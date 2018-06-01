<?php

namespace LeonMelis\UQ_free;

use phpseclib\Crypt\RSA;

/**
 * Class Asset
 * @package LeonMelis\UQ_free
 */
class Asset extends UQObject {
    const ASSET_STATE_CREATED = 0;
    const ASSET_STATE_ACTIVE = 1;
    const ASSET_STATE_INVALIDATED = 2;
    const ASSET_STATE_UNLOCKED = 3;
    const ASSET_STATE_LOCKED = 4;
    const ASSET_STATE_DESTROYED = 5;

    /**
     * @var string $public_key RSA-2048 PEM-encoded public key of this asset
     */
    protected $public_key;

    /**
     * @return string the public key in PKCS1 format
     */
    public function getPublicKey() {
        return $this->public_key;
    }

    /**
     * @return RSA the phpseclib RSA object with the public key loaded and Ubiqu settings
     * @throws UQException
     */
    public function getRSA() {
        static $rsa;

        if (!$rsa) {
            // Fetch this object, so we have the public key loaded
            $this->fetch();

            $rsa = new RSA();
            $rsa->setHash('sha256');
            $rsa->setSignatureMode(RSA::SIGNATURE_PKCS1);
            $rsa->setEncryptionMode(RSA::ENCRYPTION_OAEP);

            // This seems to be a bug in PHPSecLib, but we must specify the PRIVATE format here
            // for loadKey() to work. The other option is not giving a type at all, but that will
            // cause loadKey() to iterate over the possible formats and attempting to parse them
            // all, which is a performance issue.
            // Another option is to call setPrivateKey, but that does not set some RSA properties
            // (like the 'k' value), so that will result in verify() failing.
            if (false === $rsa->loadKey($this->public_key, RSA::PRIVATE_FORMAT_PKCS1)) {
                throw new UQException('Cannot parse PEM public key of asset');
            }
        }

        return $rsa;
    }

    /**
     * Send authentication request to this asset. This will result in
     * a push message to the device, prompting the user to accept
     *
     * @param string|null $data optional, set to NULL to use a random generated value
     * @param bool $notify set false to prevent push message to device
     * @return Authenticate
     * @throws UQException if request to API fails
     */
    function authenticate($data = null, $notify = true) {
        $authenticate = new Authenticate(null, $this->connector);
        $authenticate->setData($data);

        $authenticate->doCreate([
            'asset_uuid' => $this->uuid,
            'notify' => $notify,
            'fingerprint' => bin2hex($authenticate->getFingerPrint())
        ]);

        return $authenticate;
    }

    /**
     * Sign data using this asset.
     *
     * Returns Sign object which can be verified.
     *
     * @param string $data the data to sign
     * @param string $resource_uri optional URL to document (or other resource) to sign
     * @param bool $notify set false to prevent push message to device
     * @return Sign
     * @throws UQException if request to API fails
     */
    function sign($data, $resource_uri = '', $notify = true) {
        $sign = new Sign(null, $this->connector);
        $sign->setData($data);

        $sign->doCreate([
            'asset_uuid' => $this->uuid,
            'resource_uri' => $resource_uri,
            'notify' => $notify,
            'fingerprint' => bin2hex($sign->getFingerPrint())
        ]);

        return $sign;
    }

    /**
     * Decrypt a cipher string using this asset
     *
     * Returns a Decrypt request object from which the resulting raw data can be retrieved using
     * method getPlainText()
     *
     * NOTE: this had not yet been fully implemented by Ubiqu, implementation may change
     *
     * @param string $cipher_data the cipher string to decrypt
     * @param bool $notify set false to prevent push message to device
     * @return Decrypt
     * @throws UQException
     */
    function decrypt($cipher_data, $notify = true) {
        $decrypt = new Decrypt(null, $this->connector);

        $decrypt->doCreate([
            'asset_uuid' => $this->getUuid(),
            'notify' => $notify,
            'cipher_data' => bin2hex($cipher_data),
            // The cipher_key is a RSA-AOEP encrypted ASN.1 structure that includes
            // the preferred method and key of the transport encryption
            'cipher_key' => bin2hex($decrypt->getTransportKeyCipher())
        ]);

        return $decrypt;
    }

    /**
     * Encrypt data using the asset public key
     *
     * @param string $plaintext
     * @throws UQException
     * @return string the binary ciphertext
     */
    function encrypt($plaintext) {
        $rsa = $this->getRSA();

        $cipher = $rsa->encrypt($plaintext);

        if (false === $cipher) {
            throw new UQException('cannot encrypt data with public key');
        }

        return $cipher;
    }

    /**
     * Create an unsigned CSR with the given Distinguished Name (DN) array
     *
     * Use $csr->requestSign() to request the signature
     * Then use $csr->getSigned() to get the signed CSR
     *
     * @param array $dn the Distinguished Name (DN) array
     * @return CSR
     */
    function createCSR($dn) {
        return new CSR($this, $dn);
    }
}
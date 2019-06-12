<?php

namespace LeonMelis\UQ_free;

use phpseclib\Crypt\AES;
use phpseclib\Crypt\Random;
use phpseclib\File\ASN1;

/**
 * Class Decrypt
 * @package LeonMelis\UQ_free
 */
class Decrypt extends AssetRequest {
    /**
     * The ASN.1 structure as expected by the Ubiqu Free API for decrypt requests.
     */
    const UQ_DECRYPT_ASN1_MAPPING = [
        'type' => ASN1::TYPE_SEQUENCE,
        'children' => [
            [
                'type' => ASN1::TYPE_SEQUENCE,
                'children' => [
                    [
                        'type' => ASN1::TYPE_OBJECT_IDENTIFIER,
                    ],
                    [
                        'type' => ASN1::TYPE_NULL
                    ]
                ]
            ],
            [
                'type' => ASN1::TYPE_OCTET_STRING,
            ]
        ]
    ];

    /**
     * The AES transport cipher.
     *
     * @var AES $transport
     */
    private $transport;

    /**
     * Decrypt constructor.
     *
     * Sets up the AES CBC transport cipher, using a random key of $keyLength bits.
     * Ubiqu recommends using the 128 bit key, but 256 is also supported.
     *
     * @param string|null $uuid
     * @param Connector|null $connector
     * @param int $keyLength length of transport key (in bits) to use
     *    defaults to 128 per recommendation of Ubiqu
     */
    function __construct($uuid = null, Connector $connector = null, $keyLength = 128) {
        parent::__construct($uuid, $connector);

        $this->transport = new AES(AES::MODE_CBC);
        $this->transport->setKey((new Random())->string($keyLength / 8));
    }

    /**
     * Get the plaintext value of the data
     *
     * @return string the plaintext
     * @throws UQException
     */
    function getPlainText() {
        if (!$this->isAccepted()) {
            throw new UQException('Decrypt request was not (yet) accepted');
        }

        // The signature returned from the server consists of:
        // IV + cipher text + PKCS7 padding
        $ivLen = $this->transport->getBlockLength();
        $keyLen = $this->transport->getKeyLength();

        $iv = substr($this->signature, 0, $ivLen);
        $cipherText = substr($this->signature, $ivLen, $keyLen);

        $this->transport->setIV($iv);
        $plaintext = $this->transport->decrypt($cipherText);

        if ($plaintext === false) {
            throw new UQException('Decrypt of payload failed');
        }

        return $plaintext;
    }

    /**
     * Build the ASN.1 structure as expected by the Ubiqu Free API decrypt request.
     *
     * @return string
     * @throws UQException
     */
    private function buildTransportKeyASN1() {
        // The OID's for aes128-cbc and aes256-cbc are not (yet?) defined in PHPSecLib
        // So, we hardcoded them here
        switch ($this->transport->getKeyLength()) {
            case 128:
                $oid = '2.16.840.1.101.3.4.1.2';
                break;
            case 256:
                $oid = '2.16.840.1.101.3.4.1.42';
                break;
            default:
                throw new UQException('Unsupported key length for Ubiqu Free decrypt method');
        }

        $source = [
            [
                $oid,
                null
            ],
            // ASN1.encodeDER() expects values of TYPE_OCTET_STRING to be base64 encoded
            base64_encode($this->transport->key)
        ];

        /**
         * Note that the phpdoc for phpseclib ASN1->encodeDER() is not correct.
         * Until that is fixed, suppress the inspection here
         * @noinspection PhpParamsInspection
         */
        return (new ASN1())->encodeDER($source, self::UQ_DECRYPT_ASN1_MAPPING);
    }

    /**
     * This method returns transport preferences in the format expected by
     * the Ubiqu Free API in the 'cipher_key' parameter.
     *
     * It is an ASN.1 structure containing the encryption method, and the key
     * which should be used for transport ($this->transport). The ASN.1 structure
     * is then encrypted using the asset public key.
     *
     * @throws UQException
     * @return string the encrypted ASN.1 structure in DER format (binary)
     */
    function getTransportKeyCipher() {
        $rsa = $this->getAsset()->getRSA();

        // Encrypt the ASN1 structure with the asset public key
        $cipher = $rsa->encrypt($this->buildTransportKeyASN1());

        if (false === $cipher) {
            throw new UQException('encrypt of transport_key failed');
        }

        return $cipher;
    }
}
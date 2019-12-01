<?php

namespace LeonMelis\UQ_free;

use phpseclib\Crypt\Hash;
use phpseclib\File\X509;
use phpseclib\File\ASN1;
use phpseclib\Crypt\RSA;

/**
 * This class is a helper class to create a CSR using an asset, thus
 * without possession of the private key.
 *
 * Since it is uncommon that a creator of a CSR does not have access to the
 * private key, most crypto libraries don't support direct ASN.1 manipulation
 * which is required for this use-case.
 * It is also not expected from a developer to have the knowledge to manually
 * create a CSR ASN.1 structure.
 *
 * Thus, this class is included in this library for ease of use. It is not
 * part of the Ubiqu free API.
 *
 * The procedure to create the CSR is as follows:
 * - Create a CSR ASN.1 structure without a signature
 * - Calculate a hash digest over the CSR ASN.1 structure
 * - Sign the hash digest using the asset through the Ubiqu free API
 * - Add the hash digest to the CSR ASN.1 structure
 * - Export the CSR in a common format (usually PEM)
 *
 * Example:
 * $csr = new CSR($asset, ['commonname' => 'My first CSR']);
 * $csr->requestSign();
 * << Accept sign request on device controlling the asset >>
 * $csr->getSigned()
 */
class CSR {
    const EMPTY_SIGNATURE = 'AA==';

    /**
     * @var Asset $asset
     */
    private $asset;

    /**
     * @var Sign $signRequest
     */
    private $sign;

    /**
     * @var X509 $x509
     */
    private $x509;

    /**
     * Create a new CSR based on an asset
     *
     * @param Asset $asset
     * @param array $dn the DN structure of the CSR as named array
     */
    public function __construct($asset, $dn = []) {
        $this->asset = $asset;

        $this->x509 = new X509();
        $this->x509->setDN($dn);
    }

    /**
     * Request a signature from the asset
     *
     * @return Sign
     * @throws UQException
     */
    public function requestSign() {
        $this->sign = $this->asset->sign($this->calculateHash());

        return $this->sign;
    }

    /**
     * Get the signed CSR in given format, defaults to PEM
     *
     * @param int $format the format to return the CSR in
     * @return string the signed signature in given format
     * @throws UQException if no signature was set
     * @see phpseclib::X509 for supported formats
     */
    public function getSigned($format = X509::FORMAT_PEM) {
        $this->sign->fetch();

        if (!$this->sign->isAccepted()) {
            throw new UQException('Sign request not accepted for CSR');
        }

        if (!$this->sign->verify()) {
            throw new UQException('Cannot verify signature for CSR');
        }

        return $this->getCSR($format);
    }

    /**
     * Calculate the hash digest of the unsigned CSR.
     *
     * @return string the hash digest of the unsigned CSR
     * @throws UQException
     */
    private function calculateHash() {
        $der = $this->getCSR(X509::FORMAT_DER);

        $asn1 = new ASN1();
        $decoded = $asn1->decodeBER($der);
        $signatureSubject = substr($der, $decoded[0]['content'][0]['start'], $decoded[0]['content'][0]['length']);

        return (new Hash('sha256'))->hash($signatureSubject);
    }

    /**
     * Build a CSR and return it in given format.
     *
     * @param int $format
     * @return string
     * @throws UQException if signature is invalid
     */
    private function getCSR($format) {
        $publicKey = $this->asset->getRSA()->getPublicKey(RSA::PUBLIC_FORMAT_PKCS1);

        if ($publicKey === false) {
            throw new UQException('Cannot load public key');
        }

        $csr = [
            'certificationRequestInfo' => [
                'version' => 'v1',
                'subject' => $this->x509->dn,
                'subjectPKInfo' => [
                    'algorithm' => ['algorithm' => 'rsaEncryption'],
                    'subjectPublicKey' => $publicKey
                ],
                'attributes' => []
            ],
            'signatureAlgorithm' => [
                'algorithm' => 'sha256WithRSAEncryption'
            ],
            'signature' => $this->getSignature()
        ];

        return $this->x509->saveCSR($csr, $format);
    }

    /**
     * Get the signature in BASE64 format. If the signature request was accepted,
     * that signature is returned, else an empty signature.
     *
     * @return string
     * @throws UQException if asset signature couldn't be validated
     */
    private function getSignature() {
        if ($this->sign && $this->sign->isAccepted()) {
            return base64_encode($this->sign->getSignature());
        }

        return self::EMPTY_SIGNATURE;
    }
}
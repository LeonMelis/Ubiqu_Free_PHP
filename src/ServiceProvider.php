<?php

namespace LeonMelis\UQ_free;

use stdClass;

/**
 * Class ServiceProvider
 * @package LeonMelis\UQ_free
 */
class ServiceProvider extends UQObject {
    const SERVICE_PROVIDER_STATE_CREATED = 0;
    const SERVICE_PROVIDER_STATE_ACTIVATED = 1;
    const SERVICE_PROVIDER_STATE_DEACTIVATED = 2;

    /**
     * @var string $nonce 9-digit nonce used to finalize the creation of the service provider
     */
    protected $nonce;

    /**
     * @var string $nonce_formatted same 9-digit nonce, but formatted corresponding to formatting in App
     */
    protected $nonce_formatted;

    /**
     * @var string $domain_challenge if template requires a URL, this is the response that should be served to the domain validation challenge call.
     */
    protected $domain_challenge;

    /**
     * @var string $domain_challenge_url if template requires a URL, this is the URL that the domain validation challenge call will be sent to.
     */
    protected $domain_challenge_url;

    /**
     * @var bool $domain_validated true if domain has been validated
     */
    protected $domain_validated;

    /**
     * @var string $callback_url URL that http callbacks will be sent to
     */
    protected $callback_url;

    /**
     * @var string $notification_url URL that http notifications will be sent to
     */
    protected $notification_url;

    /**
     * @var integer $asset_count Count of how many active assets are registered with ServiceProvider
     */
    protected $asset_count;

    /**
     * @var string $api_key the API key for this serviceprovider
     */
    protected $api_key;

    /**
     * @var string $asset_uuid the UUID of the asset managing this serviceprovider object
     */
    protected $asset_uuid;


    /**
     * ServiceProvider constructor.
     * @param string|null $uuid
     * @param string|null $api_key
     * @param Connector|null $connector optional custom connector instance
     */
    public function __construct($uuid = null, $api_key = null, Connector $connector = null) {
        if (!$connector) {
            $connector = new Connector();
        }

        $connector->setApiKey($api_key);

        parent::__construct($uuid, $connector);
    }

    /**
     * Override the parent readData class, so the if the API key is created by the API
     * we update the connector
     *
     * @param stdClass|array $data
     */
    final public function readData($data) {
        parent::readData($data);

        if (is_object($data) && property_exists($data, 'api_key') && !empty($data->api_key)) {
            $this->connector->setApiKey($data->api_key);
        } else if (is_array($data) && array_key_exists('api_key', $data) && !empty($data['api_key'])) {
            $this->connector->setApiKey($data['api_key']);
        }
    }

    /**
     * @param string $name the name of the service provider (shown in app)
     * @param string $url the website URL of the service provider (shown in app)
     * @param string $callback_url
     * @param string $template
     * @param Connector|null $connector optional connector, if not set, a default connector is created
     * @return ServiceProvider
     * @throws UQException if request to API fails
     */
    public static function create($name, $url, $callback_url, $template = 'ubiqu_nourl', Connector $connector = null) {
        $provider = new self(null, null, $connector);

        $provider->doCreate([
            'name' => $name,
            'template' => $template,
            'url' => $url,
            'callback_url' => $callback_url,
            'challenge_url' => $url       // Deprecated
        ]);

        return $provider;
    }

    /**
     * @param bool $force set true to force validate, even if already validated
     * @return bool true if successful
     * @throws UQException if request to API fails
     */
    public function validateDomain($force = false) {
        if ($this->domain_validated && !$force) {
            // Domain already validated, don't perform request
            return true;
        }

        return $this->connector->post('/serviceprovider/validatedomain')->success;
    }

    /**
     * @return string
     */
    public function getDomainChallenge() {
        return $this->domain_challenge;
    }

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
     * We would prefer not to have this method, but since Ubiqu does not
     * have an administrative API (yet!), the only way to obtain an API
     * key for a newly created ServiceProvider is through this method.
     *
     * @return string
     */
    public function getAPIKey() {
        return $this->api_key;
    }

    /**
     * Create an Identification object in order to create a new asset.
     *
     * @return Identification
     * @throws UQException if request to API fails
     */
    public function createIdentification() {
        $identification = new Identification(null, $this->connector);
        $identification->doCreate();

        return $identification;
    }

    /**
     * Return an Asset with given UUID. The Asset will not be auto-fetched from the API.
     * To populate the Asset from the API, perform fetch() on returned object
     *
     * @param string $uuid
     * @return Asset
     */
    public function getAsset($uuid) {
        return new Asset($uuid, $this->connector);
    }

    /**
     * Return the administrative asset (the owner of this ServiceProvider)
     *
     * @return Asset
     */
    public function getAdminAsset() {
        return new Asset($this->asset_uuid, $this->connector);
    }
}
<?php

namespace LeonMelis\UQ_free;

use stdClass;

/**
 * Class Connector
 * @package LeonMelis\UQ_free
 */
class Connector {
    use Cacheable;

    const DEFAULT_URL = 'https://free.ubiqu.com/api';

    /**
     * @var bool $debug set true to enable debugging output for the request
     */
    public $debug = false;

    /**
     * @var array|null $status the status of the last call to the server
     */
    private $status = null;

    /**
     * @var array $headers
     */
    private $headers = [
        'Accept' => 'application/vnd.free.v1+json',
        'Content-Type' => 'application/json'
    ];

    /**
     * Connector constructor.
     * @param CacheInterface|null $cacheHandler
     */
    function __construct(CacheInterface $cacheHandler = null) {
        $this->cacheHandler = $cacheHandler ?: new MemoryCache();
    }

    /**
     * @return string
     */
    private static function getURL() {
        return getenv('UQ_API_URL') ?: self::DEFAULT_URL;
    }

    /**
     * Convert an associative array to a cURL compatible header array
     *
     * @return array
     */
    private function getHeaders() {
        return array_map(function ($key, $val) {
            return $key . ': ' . $val;
        }, array_keys($this->headers), $this->headers);
    }

    /**
     * Build a query string from an array
     *
     * @param array $args
     * @return string the query string
     */
    private static function getQueryString($args) {
        if (!is_array($args) || empty($args)) {
            return '';
        }

        $c = 0;
        $out = '?';

        foreach ($args as $name => $value) {
            if ($c++ != 0) $out .= '&';
            $out .= urlencode("$name");

            if (is_null($value)) {
                //For null values, just print the name, without =
                continue;
            }

            $out .= '=';

            if (is_array($value)) {
                $out .= urlencode(serialize($value));
            } else {
                $out .= urlencode("$value");
            }
        }

        return $out;
    }

    /**
     * Perform a request to the Ubiqu API
     *
     * @param string $request
     * @param array|null $data the POST data or GET query params
     * @param bool $post set false to perform a GET
     * @return stdClass the returned object from the API
     * @throws UQException
     */
    private function request($request, $data = null, $post = true) {
        $ch = curl_init();

        $url = join('/', [self::getURL(), $request]);

        if ($post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            $url .= self::getQueryString($data);
        }

        curl_setopt($ch, CURLOPT_VERBOSE, $this->debug);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders());

        if ($this->debug) {
            if ($post) {
                error_log(">> POST {$url}: " . json_encode($data), LOG_DEBUG);
            } else {
                error_log(">> GET {$url}", LOG_DEBUG);
            }
        }

        if (false === $response = curl_exec($ch)) {
            throw new UQException(curl_error($ch), curl_errno($ch));
        }

        if ($this->debug) {
            error_log("<< {$response}", LOG_DEBUG);
        }

        $this->status = curl_getinfo($ch);

        curl_close($ch);

        if (null === $data = json_decode($response)) {
            throw new UQException('Cannot decode JSON response: ' . json_last_error_msg(), json_last_error());
        }

        if (property_exists($data, 'errors')) {
            throw new UQException('UQ free API returned error(s): ' . json_encode($data->errors));
        }

        if (!property_exists($data, 'result')) {
            throw new UQException('No result in response');
        }

        return $data->result;
    }

    /**
     * Fetch a UQObject from the Ubiqu free API
     *
     * @param UQObject $object
     * @throws UQException
     * @return stdClass response from the API
     */
    function fetch($object) {
        return $this->request(join('/', [$object->getType(), $object->getUuid()]), null, false);
    }

    /**
     * Perform a POST request on the Ubiqu API
     *
     * @param string $request
     * @param array|null $data the POST data
     * @return stdClass response from the API
     * @throws UQException if request to API fails
     */
    function post($request, $data = null) {
        return $this->request($request, $data, true);
    }

    /**
     * @param string|null $api_key the API key, set to NULL to clear the internally stored key
     */
    public function setApiKey($api_key) {
        if ($api_key) {
            $this->headers['X-Api-Key'] = $api_key;
        } else {
            unset($this->headers['X-Api-Key']);
        }
    }

    /**
     * Get the status object of the last cURL request
     * @return array|null
     */
    public function getLastStatus() {
        return $this->status;
    }

    /**
     * @param $data
     * @return mixed
     * @throws UQException
     */
    private static function APIRead($data) {
        if (!property_exists($data, 'type')) {
            throw new UQException('Expected property \'type\' in response');
        }

        if (!property_exists($data, 'uuid')) {
            throw new UQException('Expected property \'uuid\' in response');
        }

        return $data;
    }

    /**
     * Loads an object.
     *
     * If a caching function is set for this Connector, it will first be used
     * to attempt to load from cache. If there is no caching function, or there
     * is a cache miss, the data is loaded from the API.
     *
     * @param UQObject $object
     * @param bool $forceFetch set true to force fetching from the API
     * @return stdClass
     * @throws UQException
     */
    public function loadObject($object, $forceFetch = false) {
        if (!$forceFetch) {
            $cache_hit = $this->cacheRead($object->getType(), $object->getUuid());

            if ($cache_hit) {
                return $cache_hit;
            }
        }

        $data = self::APIRead($this->fetch($object));

        $this->cacheWrite($data->type, $data->uuid, $data);

        return $data;
    }

    /**
     * Create a new object on the Ubiqu server
     *
     * @param string $type
     * @param stdClass|array $data
     * @return stdClass
     * @throws UQException
     */
    public function createObject($type, $data) {
        $object = self::APIRead($this->post($type, $data));

        $this->cacheWrite($object->type, $object->uuid, $object);

        return $object;
    }
}
<?php

namespace LeonMelis\UQ_free;

use DateTime;
use stdClass;

/**
 * Class UQObject
 *
 * The abstract object class for an object send to or received from the Ubiqu free API.
 *
 * @package LeonMelis\UQ_free
 */
abstract class UQObject {
    const UQ_OBJ_TYPE_ASSET = 'asset';
    const UQ_OBJ_TYPE_AUTHENTICATE = 'authenticate';
    const UQ_OBJ_TYPE_IDENTIFICATION = 'identification';
    const UQ_OBJ_TYPE_SIGN = 'sign';
    const UQ_OBJ_TYPE_DECRYPT = 'decrypt';
    const UQ_OBJ_TYPE_SERVICE_PROVIDER = 'serviceprovider';
    const UQ_OBJ_TYPE_API_KEY = 'apikey';
    const UQ_OBJ_TYPE_CLIENT_CERTIFICATE = 'clientcert';
    const UQ_OBJ_TYPE_CLIENT_ADMINISTRATE = 'administrate';
    const UQ_OBJ_TYPE_PING = 'ping';

    const TYPE_TO_CLASS = [
        self::UQ_OBJ_TYPE_ASSET => Asset::class,
        self::UQ_OBJ_TYPE_AUTHENTICATE => Authenticate::class,
        self::UQ_OBJ_TYPE_IDENTIFICATION => Identification::class,
        self::UQ_OBJ_TYPE_SIGN => Sign::class,
        self::UQ_OBJ_TYPE_DECRYPT => Decrypt::class,
        self::UQ_OBJ_TYPE_SERVICE_PROVIDER => ServiceProvider::class
    ];

    /**
     * @var Connector $connector
     */
    protected $connector;

    /**
     * @var string $type
     */
    protected $type;

    /**
     * @var string $name
     */
    protected $name;

    /**
     * @var string $uuid
     */
    protected $uuid;

    /**
     * @var integer $status_code
     */
    protected $status_code;

    /**
     * @var string $status_text
     */
    protected $status_text;

    /**
     * @var DateTime $created_at
     */
    protected $created_at;

    /**
     * @var DateTime $updated_at
     */
    protected $updated_at;

    /**
     * UQObject constructor.
     *
     * @param string|null $uuid optional, the UUID of this UQObject
     * @param Connector|null $connector optional, the Connector interface to use
     */
    public function __construct($uuid = null, Connector $connector = null) {
        $this->connector = $connector;
        $this->type = $this->getAPINamespace();

        if ($uuid) {
            $this->uuid = $uuid;
        }
    }

    /**
     * @return string
     */
    public function getUuid() {
        return $this->uuid;
    }

    /**
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Returns the namespace for the Ubiqu free API, this is the part of
     * the URL after '/api/'. This method can be overridden.
     *
     * @return string the API namespace
     */
    public function getAPINamespace() {
        // strip the namespace, convert to lowercase
        return strtolower(substr(static::class, strrpos(static::class, '\\') + 1));
    }

    /**
     * @param stdClass|array $data
     */
    public function readData($data) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = self::read($key, $value);
            } else {
                $cls = static::class;
                error_log("Received unknown property '{$key}' for {$cls} from API");
            }
        }
    }

    /**
     * Read values from the API
     *
     * @param string $field
     * @param mixed $value
     * @return mixed
     */
    private static function read($field, $value) {
        switch ($field) {
            case 'created_at':
            case 'updated_at':
                // Note that neither \DateTime::ISO8601 nor \DateTime::ATOM are actually
                // compliant with an ISO8601 time string which includes microseconds
                return DateTime::createFromFormat('Y-m-d\TH:i:s.uP', $value);
                break;
            default:
                return $value;
        }
    }

    /**
     * Create a new object on the Ubiqu server
     *
     * @param array|null $data
     * @throws UQException
     */
    public function doCreate($data = null) {
        $this->readData($this->connector->createObject($this->getAPINamespace(), $data));
    }

    /**
     * Refresh the provider info by fetching from the Ubiqu server
     *
     * @param bool $force
     * @throws UQException
     */
    public function fetch($force = false) {
        $this->readData($this->connector->loadObject($this, $force));
    }

    /**
     * Create a UQ object by type and UUID
     *
     * @param string $type the object type as POSTed by the notification
     * @param string|null $uuid the UUID of the object
     * @param Connector|null $connector
     * @throws UQException if object type is not supported
     * @return UQObject
     */
    public static function createObjectByType($type, $uuid = null, Connector $connector = null) {
        if (!in_array($type, self::TYPE_TO_CLASS, true)) {
            throw new UQException("Unsupported UQ object type '{$type}'");
        }

        $className = self::TYPE_TO_CLASS[$type];

        return new $className($uuid, $connector);
    }
}
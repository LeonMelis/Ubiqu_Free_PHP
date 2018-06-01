<?php

namespace LeonMelis\UQ_free;

/**
 * Class NotificationHandler
 * @package LeonMelis\UQ_free
 */
class NotificationHandler {
    use Cacheable;

    /**
     * @var Connector $connector
     */
    private $connector;

    /**
     * NotificationHandler constructor
     *
     * @param Connector $connector
     * @param CacheInterface|null $cache
     */
    public function __construct($connector, CacheInterface $cache = null) {
        $this->connector = $connector;
        $this->cacheHandler = $cache;
    }

    /**
     * @param $data
     * @return \stdClass
     * @throws UQException
     */
    private static function decodeNotification($data) {
        if (is_string($data)) {
            if (null === $data = json_decode($data)) {
                throw new UQException('Cannot decode JSON payload: ' . json_last_error_msg(), json_last_error());
            }
        }

        if (!property_exists($data, 'notification')) {
            throw new UQException('No notification object in payload');
        }

        return $data->notification;
    }

    /**
     * Returns the UQ object from the notification.
     *
     * The UQ object will be constructed, but not fetched.
     *
     * @param \stdClass $notification
     * @return UQObject
     * @throws UQException
     */
    public function getUQObjectFromNotification($notification) {
        if (!property_exists($notification, 'type')) {
            throw new UQException('Expected property \'type\' in notification');
        }

        if (!property_exists($notification, 'uuid')) {
            throw new UQException('Expected property \'uuid\' in notification');
        }

        return UQObject::createObjectByType($notification->type, $notification->uuid, $this->connector);
    }

    /**
     * Handle a notification by creating the object in the notification type and UUID,
     * then fetching that object through the Ubiqu Free API. Fetching the object will
     * also trigger the write() method on the cache interface.
     *
     * @param \stdClass|string $data the POST data from the notification
     * @return UQObject
     * @throws UQException
     */
    public function handleNotification($data) {
        $notification = self::decodeNotification($data);

        $uq_object = $this->getUQObjectFromNotification($notification);
        $uq_object->fetch(true);

        return $uq_object;
    }
}
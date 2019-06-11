# Unofficial Ubiqu Free API implementation for PHP

This is an *unofficial* PHP implementation for the 
[Ubiqu Free API](https://ubiqu.com/developers). The maintainer 
of this project is not affiliated with Ubiqu.

License: MIT

## Installation
This library can easily be installed through Composer.

```php
composer require leonmelis/uq_free
```

Then use it in your PHP code using:

```php
require __DIR__ . '/vendor/autoload.php';

use \LeonMelis\UQ_free;
```

## Requirements
- PHP >= 5.6 or PHP >= 7.0
- cURL PHP module (`ext-curl`)

NOTE: this library uses `phpseclib` internally. It is recommended (but not required) by phpseclib to have
the PHP `ext-openssl` module installed for better performance.

## Getting started
Before getting started, you should have the Ubiqu Authenticate app 
installed on a mobile device:
- [Apple App store](https://itunes.apple.com/nl/app/authenticate/id934349819)
- [Google Play store](https://play.google.com/store/apps/details?id=com.ubiqu.eid)

Then, create a new `ServiceProvider`:

```php
$provider = UQ_free\ServiceProvider::create(
    'My Service Provider',
    'https://mydomain.com',
    'https://mydomain.com/callback'
);

echo "UUID: {$provider->getUuid()}\n";
echo "API key: '{$provider->getAPIKey()}'\n";
echo "Activate admin with nonce: '{$provider->getNonceFormatted()}'\n";
```

The `ServiceProvider` object is now created on the Ubiqu server, but 
not yet active. To activate the provider (and also become owner of it) 
use the Authenticate app on your mobile device, choose 'auto-activate' 
from the settings menu and enter the 9 digit nonce from the 
`ServiceProvider` object when prompted.  

Store the API key and UUID for this provider. 

A previously created `ServiceProvider` can be constructed by passing 
the UUID and API-key to the constructor:

```php
$provider = new UQ_free\ServiceProvider($uuid, $api_key);

// Optionally, you can remote fetch the ServiceProvider object
// to get additional data, such as the name.
$provider->fetch(); 
echo "Using provider {$provider->getName()}\n";
```

## Creating assets
An `Asset` is created through an `Identification` object. This may be
confusing at first, but you have to remember that we don't know the 
device that will control the asset at this point in time.

The `Identification` object contains a nonce which can be entered in 
the Authenticate app. Once completed a callback is made from the Ubiqu 
Free API, notifying us that the identification has been consumed and 
the asset is ready to use.

```php
// To create a new asset
$identification = $provider->createIdentification();
echo "Identify with: '{$identification->getNonceFormatted()}'\n";
// Wait for callback, then we can fetch the asset
$asset = $identification->fetchAsset();
```

## Performing an `AssetRequest`
The `AssetRequest` allows to perform a cryptographic method on the private key owned
by the end user. This is the heart of the Ubiqu system. The user gets a push-message
on their mobile device, asking to approve or reject the request, unlocking their
private key using their PIN. Since we have the public key we can validate the signature
we receive.
 

```php
/* Authentication */
$authentication_request = $asset->authenticate();
// Wait for callback
$verified = $authentication_request->verify();

/* Sign */
$sign_request = $asset->sign(hash($document));
// Wait for callback
$verified = $sign_request->verify();

/* Decrypt */
$decrypt_request = $asset->descrypt($encrypted_data);
// Wait for callback
$plain = $decrypt_request->getPlainText();
```

## Creating a CSR
It is possible to create a CSR without possession of the private key. 
However, this is uncommon and requires knowledge of the CSR internal structure (ASN.1).
So, a CSR class is added to help you with performing this procedure.

```php
$csr = $asset->createCSR(['CommonName' => 'example.com']);
$csr->requestSign();
// Wait for callback
echo $csr->getSigned();
```

## Callbacks
Due to the asynchronous nature of the Ubiqu Free protocol (waiting for 
the user to approve/reject the `AssetRequest` through the app) the Ubiqu
Free API makes callbacks to the `callback_url` passed to the API during 
creation of the `ServiceProvider`. Ubiqu makes a callback every time an 
object owned by the `ServiceProvider` changes state.

To handle the callbacks, use the `CallbackHandler` class.

```php
$handler = new CallbackHandler();
$handler->handleCallback($_POST);
```   

The `CallbackHandler` constructor accepts a `CacheInterface` instance for 
persisting the received updates, of pushing the changes to some data bus.

## Caching / persisting
By default, the `Connector` class uses a `MemoryCache` instance for 
caching. This prevents having to fetch the same object more than once 
from the API.

However, the `MemoryCache` is of limited use, due to the very nature 
of PHP being restarted on each call. Also, this Ubiqu Free library is 
fairly useless if objects cannot be persisted to something like a 
database. 

To create your own caching/persistence class, all you need to do is 
implement the `CacheInterface` interface, consisting of 2 simple methods. 
Pass an instance of that class to the `Connector` constructor.

```php
class MyCache implements UQ_free\CacheInterface {
    function read($type, $uuid) {
        return database_read($type, $uuid);
    }
    
    function write($type, $uuid, $data) {
        database_write($type, $uuid, $data);
    }
}

$connector = new UQ_free\Connector(new MyCache());
$provider = new UQ_free\ServiceProvider($uuid, $api_key, $connector);
```
The data is passed to the writer as a raw `stdClass` as received from 
the API. The reader is expected to return a `stdClass` instance or an 
associative array.
 
You don't necessarily need to store all fields of the `UQObject` 
instances, or even store every object type. 

For all `UQObject` instances you should at least store the UUID and 
state (`status_code`). For an `Asset` you must also store the value 
of `public_key`. All other fields are currently not required for the 
functioning of this library.
 
## More information
https://ubiqu.com/developers/ubiqu-free-tutorial/

https://ubiqu.com/developers/freetutorial-creating-service-providers/

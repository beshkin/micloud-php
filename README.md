# Mi Cloud client library
Current PHP library helps to manage Mi devices through cloud access.

## Installation
```$ composer require beshkin/micloud-php```

## Usage
### Power On device
```
$config = [
    'country' => 'de', // 'ru', 'us', 'tw', 'sg', 'cn', 'de'
    'login' => [your Mi app login],
    'password' => [your Mi app password],
]
$client = new MiCloudClient($config);
$client->login();
$result = $client->miioCall(
    [Device id],
    'set_power',
    Helper::withLightEffect('on', 5)
);
```

### Get device data
I use this method in order to get sensor data (temperature, humidity etc)
```
$config = [
    'country' => 'de', // 'ru', 'us', 'tw', 'sg', 'cn', 'de'
    'login' => [your Mi app login],
    'password' => [your Mi app password],
]
$client = new MiCloudClient($config);
$client->login();
$result = $client->request('/home/device_list', ['dids' => [your device id]]);
```

## Special thanks
Basically this library is a translation from node-mihome library cloud client (https://github.com/samueljansem/node-mihome)
Thanks Samuel Jansem for inspiration.
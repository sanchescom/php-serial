# PHP Serial

Multi-platform convenience class to access the serial port from PHP.
This is refactored version of sources library [PHP-Serial](https://github.com/Xowap/PHP-Serial). Current version rewrite
on PHP 7.

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development and testing purposes. See deployment for notes on how to deploy the project on a live system.

### Installing

Require this package, with [Composer](https://getcomposer.org/), in the root directory of your project.

``` bash
$ composer require sanchescom/php-serial
```

## Usage

```php
<?php

use Sanchescom\Serial\Serial;

try {
    $serial = new Serial();
    
    // First we must specify the device. This works on both linux and windows (if
    // your linux serial device is /dev/ttyS0 for COM1, etc)
    $device = $serial->setDevice('COM5');

    // We can change the baud rate, parity, length, stop bits, flow control
    $device->setBaudRate(2400);
    $device->setParity("none");
    $device->setCharacterLength(8);
    $device->setStopBits(1);
    $device->setFlowControl("none");

    // Then we need to open it
    $device->open();

    // To write into
    $device->send('Hello!');

    // Or to read from
    $read = $device->read();

    // If you want to change the configuration, the device must be closed
    $device->close();

    // We can change the baud rate
    $device->setBaudRate(2400);
} catch (Exception $e) {

}
```
## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct, and the process for submitting pull requests to us.

## Versioning

We use [SemVer](http://semver.org/) for versioning. For the versions available, see the [tags on this repository](https://github.com/sanchescom/php-serial/tags). 

## Authors

* **Efimov Aleksandr** - *Refactoring and supporting work* - [Sanchescom](https://github.com/sanchescom)
* **Rémy Sanchez** - *Initial work* - [Xowap](https://github.com/Xowap)

See also the list of [contributors](https://github.com/sanchescom/php-serial/contributors) who participated in this project.

## License

This project is licensed under the GPL-3.0 License - see the [LICENSE.md](LICENSE.md) file for details

## Platform support

* **Linux**: the initially supported platform, the one I used. Probably the less
  buggy one.
* **MacOS**: although I never tried it on MacOS, it is similar to Linux and some
  patches were submitted to me, so I guess it is OK
* **Windows**: it seems to be working for some people, not working for some
  others. Theoretically there should be a way to get it done.
# MangoSpot simple library Network Manager

A simple PHP library for reading and manipulating the /etc/network/interfaces file in Debian/Ubuntu based distributions.

## Install

```
composer require mangospot/network-manager
```

### Usage:

```php
<?php
//include composer autoloader
include 'vendor/autoload.php';

// 'import' NetworkInterfaces class
use NetworkManager\Adaptor;
use NetworkManager\Networks;
use NetworkManager\Interfaces;

// create new handle from /etc/networking/interfaces
$handle = new Interfaces('/etc/networking/interfaces');
```

[![paypal](https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8CRUEDLPLCFSQ)

```
Paypal: mangospot.net@gmail.com
Phone: [+62 856-4231-1781](https://wa.me/6285642311781)
```

# MangoSpot simple library Network Manager

A simple PHP library for reading and manipulating the /etc/network/interfaces file in Debian/Ubuntu based distributions.

## Install

```
composer require mangospot/network-manager
```

### Network:

```php
<?php
//include composer autoloader
include 'vendor/autoload.php';

// 'import' NetworkInterfaces class
use NetworkManager\Networks;

// create new netif from ifconfig
$netif = new Networks();

// or create new netif from net_get_interfaces()
$netif = new Networks(false);

// gets the host name
$host = $netif->getHostAddr();

// get network interfaces
$interfaces = $netif->getNetworkInterfaces();

// get array network interfaces
$arrayInterface = $netif->arrayInterfaces();
```

### Interface:

```php
<?php
//include composer autoloader
include 'vendor/autoload.php';

// 'import' NetworkInterfaces class
use NetworkManager\Adaptor;
use NetworkManager\Interfaces;

// create new handle from /etc/networking/interfaces
$handle = new Interfaces();

// parse file
$handle->parse();

// add source on /etc/networking/interfaces
$handle->addSource();

// or add source dir
$handle->addSource('/etc/network/interfaces.d/*');

// create new Adaptor and set configs
$adaptor = new Adaptor();
$adaptor->name = "eth2";
$adaptor->family = "inet";
$adaptor->method = "static";
$adaptor->address = '192.168.2.100';
$adaptor->gateway = '192.168.2.1';
$adaptor->netmask = '255.255.255.0';
$adaptor->auto = true;
$adaptor->allows[] = 'hotplug';
$adaptor->Unknown['dns-nameservers'] = '8.8.8.8 8.8.4.4';

// add adaptor to NetworkInterfaces instance
$handle->add($adaptor);

// change eth0 ip address
$handle->Adaptors['eth0']->address = '192.168.0.30';

// Write changes to /etc/networking/interfaces
$handle->write();

// bringing up new interface
$handle->up('eth2');
```

### Use SSH autoload.php

You can use [phpseclib](https://github.com/phpseclib/phpseclib) library

```php
<?php
//include composer autoloader
include 'vendor/autoload.php';

// phpseclib3 class
use phpseclib3\Net\SSH2;
use phpseclib3\Net\SFTP;
class SSH extends SSH2 {
    private $sftp;

    public function __construct(){
        parent::__construct('127.0.0.1', 22);
        $this->login('username', 'password');
    }

    public function sftp(){
        $this->sftp = new SFTP('127.0.0.1', 22);
        $this->sftp->login('username', 'password');
        return $this->sftp;
    }
}
```

### example.php

```php
//include autoload.php
include 'autoload.php';

// NetworkInterfaces class
use NetworkManager\Adaptor;
use NetworkManager\Networks;
use NetworkManager\Interfaces;

$SSH     = new SSH();
$network = new Networks();
$interface = new Interfaces();

$network->ssh($SSH);
$interface->ssh($SSH);
```

### Libraries Used

- SSH: [phpseclib](https://github.com/phpseclib/phpseclib)
- Networks: [VIPSoft](https://github.com/vipsoft/network-interfaces)
- Interfaces: [carp3](https://github.com/carp3/networkinterfaces)

### Contributors

[![paypal](https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8CRUEDLPLCFSQ)

- Paypal: mangospot.net@gmail.com
- WhatsApp: [+62 856-4231-1781](https://wa.me/6285642311781)

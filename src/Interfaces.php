<?php
/*
 * This file is part of NetworkManager.
 *
 * (c) MangoSpot <mangospot.net@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace NetworkManager;


use Exception;

/**
 * Class NetworkManager
 * @package NetworkManager
 */
class Interfaces
{
    /**
     * @var bool|string
     */
    private $ssh = false;
    /**
     * @var bool|string
     */
    private $sftp = false;
    /**
     * @var bool|string
     */
    private $source = false;
    /**
     * @var Adaptor[]
     */
    public $Adaptors = [];
    /**
     * @var bool|string
     */
    private $_interfaceFile = false;
    /**
     * @var bool|string
     */
    private $_interfaceContent = '';
    /**
     * @var bool
     */
    private $_interfaceLoaded = false;
    /**
     * @var bool
     */
    private $_interfaceParsed = false;
    /**
     * @var string
     */
    private $_interfaceSource = '/etc/network/interfaces.d/*';
    /**
     * NetworkManager constructor.
     * @param string $InterfacePath Path to interface file, usually /etc/network/interfaces
     * @param bool $new skip reading interface file, useful for creating new file
     * @throws Exception
     */
    public function __construct(string $InterfacePath = '/etc/network/interfaces', $new = False)
    {
        $this->_interfaceFile = $InterfacePath;
        if ($new) {
            $this->_interfaceParsed = true;
            return;
        }
        
    }
    
    /**
     * Remote connetion with SSH and SFTP
     * @throws Exception
     */
    public function ssh($ssh = false)
    {
        if($ssh){
		    $this->ssh = $ssh;
            $this->sftp = $this->ssh->sftp();
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check file network interfaces
     */
    private function pathFile()
    {
        if($this->sftp){
            if (!$this->sftp->file_exists($this->_interfaceFile)){
                throw new Exception("Interface file does not exist");
            }
            if (!$this->sftp->is_readable($this->_interfaceFile)){
                throw new Exception("Interface file is not readable");
            }
            $this->_interfaceContent = $this->sftp->get($this->_interfaceFile);
            $this->_interfaceLoaded = true;
        } else {
            if (!file_exists($this->_interfaceFile)){
                throw new Exception("Interface file does not exist");
            }
            if (!is_readable($this->_interfaceFile)){
                throw new Exception("Interface file is not readable");
            }
            $this->_interfaceContent = file_get_contents($this->_interfaceFile);
            $this->_interfaceLoaded = true;
        }
    }

    /**
     * Add source path 
     * @return bolean or string (/etc/network/interfaces.d/*)
     * @throws Exception
     */
    public function addSource($sources = true)
    {
        if(is_bool($sources)){
            return $this->source = $sources == true ? $this->_interfaceSource : false;
        } else if(isset($sources) && !empty($sources)){
            return $this->source = $sources;
        } else {
            return false;
        }
    }
    
    /**
     * read interface file and fill Adaptor property
     * @return array
     * @throws Exception
     */
    public function parse()
    {
        $this->pathFile();
        if (!$this->_interfaceLoaded){
            throw new Exception("Interface file is not loaded");
        }
        $interfaceContent = explode("\n", $this->_interfaceContent);
        $lastAdaptor = '';
        foreach ($interfaceContent as $item) {
            $item = trim($item);

            if (strpos(ltrim($item), '#') === 0) continue;
            if (trim($item) == '') continue;
            if (strpos($item, 'iface') === 0)
                $lastAdaptor = $this->_parseIface($item);
            elseif (strpos($item, 'auto') === 0)
                $this->_parseAuto($item);
            elseif (strpos($item, 'allow-') === 0)
                $this->_parseAllow($item);
            elseif ($lastAdaptor != '')
                $this->_parseDetail($item, $lastAdaptor);
        }
        $this->_interfaceParsed = true;
        return $this->Adaptors;
    }

    /**
     * @param $item
     * @return mixed
     */
    private function _parseIface($item)
    {
        $chunks = $this->_split($item);
        list($null, $this->Adaptors[$chunks[1]]->name, $this->Adaptors[$chunks[1]]->family, $this->Adaptors[$chunks[1]]->method) = $chunks;
        unset($null);
        return $chunks[1];
    }

    /**
     * @param $item
     * @return array
     */
    private function _split($item, $adaptor = False, $returnAdaptor = false)
    {
        $chunks = preg_split('/\s+/', $item, -1, PREG_SPLIT_NO_EMPTY);
        if (!$adaptor) $this->_addAdaptor($chunks[1]);
        return $returnAdaptor ? $chunks[1] : $chunks;
    }


    /**
     * @param $adaptor
     */
    private function _addAdaptor($adaptor)
    {
        if (!array_key_exists($adaptor, $this->Adaptors)) $this->Adaptors[$adaptor] = new Adaptor();
        $this->Adaptors[$adaptor]->auto = false;
    }

    /**
     * @param $item
     */
    private function _parseAuto($item)
    {
        $chunks = $this->_split($item);
        foreach (array_slice($chunks, 1) as $chunk) {
            $this->_addAdaptor($chunk);
            $this->Adaptors[$chunk]->auto = True;
        }

    }

    /**
     * @param $item
     */
    private function _parseAllow($item)
    {
        $chunks = $this->_split($item);
        $allow = str_replace('allow-', '', $chunks[0]);
        $allow = trim($allow);
        if (!in_array($allow, $this->Adaptors[$chunks[1]]->allows)) $this->Adaptors[$chunks[1]]->allows[] = $allow;
    }

    /**
     * @param $item
     * @param $lastAdaptor
     */
    private function _parseDetail($item, $lastAdaptor)
    {
        $chunks = $this->_split($item, $lastAdaptor);
        $adaptor = &$this->Adaptors[$lastAdaptor];
        switch ($chunks[0]) {
            case 'address':
                if(strpos($chunks[1], '/') == false){
                    $adaptor->address = $chunks[1];
                } else {
                    $chunks[1] =  $this->_parseCidr($chunks[1]);
                    $adaptor->address = $chunks[1]["address"];
                    $adaptor->netmask = $chunks[1]["netmask"];
                    $adaptor->broadcast = $chunks[1]["broadcast"];
                    $adaptor->network = $chunks[1]["network"];
                }
                break;
            case 'netmask':
                $adaptor->netmask = $chunks[1];
                break;
            case 'gateway':
                $adaptor->gateway = $chunks[1];
                break;
            case 'broadcast':
                $adaptor->broadcast = $chunks[1];
                break;
            case 'network':
                $adaptor->network = $chunks[1];
                break;
            default:
                $adaptor->Unknown[] = trim($item);
                break;
        }
    }

    /**
     * Netmask list
     * @param $data
     */
    function _Netmask($data = null)
    {
        $array = array(
            0 => "0.0.0.0",
            1 => "128.0.0.0",
            2 => "192.0.0.0",
            3 => "224.0.0.0",
            4 => "240.0.0.0",
            5 => "248.0.0.0",
            6 => "252.0.0.0",
            7 => "254.0.0.0",
            8 => "255.0.0.0",
            9 => "255.128.0.0",
            10 => "255.192.0.0",
            11 => "255.224.0.0",
            12 => "255.240.0.0",
            13 => "255.248.0.0",
            14 => "255.252.0.0",
            15 => "255.254.0.0",
            16 => "255.255.0.0",
            17 => "255.255.128.0",
            18 => "255.255.192.0",
            19 => "255.255.224.0",
            20 => "255.255.240.0",
            21 => "255.255.248.0",
            22 => "255.255.252.0",
            23 => "255.255.254.0",
            24 => "255.255.255.0",
            25 => "255.255.255.128",
            26 => "255.255.255.192",
            27 => "255.255.255.224",
            28 => "255.255.255.240",
            29 => "255.255.255.248",
            30 => "255.255.255.252",
            31 => "255.255.255.254",
            32 => "255.255.255.255"
        );
        if (array_key_exists($data, $array)) {
            return intval($data);
        } else if(array_search($data, $array)){
            return intval(array_search($data, $array));
        } else {
            return $array;
        }
    }

    /**
    * @param $cidr
    */
    function _parseCidr($cidr)
    {
        $range = array();
        $cidr = explode('/', $cidr);
        $range["address"] = $cidr[0];
        $range["network"] = long2ip((ip2long($cidr[0])) & ((-1 << (32 - $this->_Netmask($cidr[1])))));
        $range["broadcast"] = long2ip((ip2long($range["network"])) + pow(2, (32 - $this->_Netmask($cidr[1]))) - 1);
        $range["netmask"] = long2ip(-1 << (32 - $this->_Netmask($cidr[1])));
        return $range;
    }

    public function getNetmask(){
        return $this->_Netmask();
    }
    /**
     * brings up an interface
     * @param string $name Interface name
     * @param bool $sudo use sudo command before ifup
     * @throws Exception
     */
    public function up($name, $sudo = false)
    {
        if (!$this->_interfaceParsed)
            throw new Exception("Interface file is not parsed");
        if (!array_key_exists($name, $this->Adaptors))
            throw new Exception("$name does not exist is adaptor list");
        $cmd = ($sudo ? 'sudo ' : '') . "ifup $name";
        if($this->ssh){
            $this->ssh->exec($cmd);
        } else {
            shell_exec($cmd);
        }
    }

    /**
     * brings down an interface
     * @param string $name Interface name
     * @param bool $sudo use sudo command before ifdown
     * @throws Exception
     */
    public function down($name, $sudo = false)
    {
        if (!$this->_interfaceParsed)
            throw new Exception("Interface file is not parsed");
        if (!array_key_exists($name, $this->Adaptors))
            throw new Exception("$name does not exist is adaptor list");
        $cmd = ($sudo ? 'sudo ' : '') . "ifdown $name";
        if($this->ssh){
            $this->ssh->exec($cmd);
        } else {
            shell_exec($cmd);
        }
    }

    /**
     * restart an interface
     * @param string $name Interface name
     * @param bool $sudo use sudo command before ifup and ifdown
     * @throws Exception
     */
    public function restart($name, $sudo = false)
    {
        if (!$this->_interfaceParsed)
            throw new Exception("Interface file is not parsed");
        if (!array_key_exists($name, $this->Adaptors))
            throw new Exception("$name does not exist is adaptor list");
        $cmd = ($sudo ? 'sudo ' : '') . "ifdown $name && " . ($sudo ? ' sudo ' : '') . "ifup $name";
        if($this->ssh){
            $this->ssh->exec($cmd);
        } else {
            shell_exec($cmd);
        }
    }

    /**
     * generate inteface file and write it (or return it)
     * @param bool $return if true, generated file will be returned.
     * @return bool|int|string
     * @throws Exception
     */
    public function write($return = False)
    {
        if (!$this->_interfaceParsed){
            throw new Exception("Interface file is not parsed");
        }
        if ($this->sftp){
            if(!$this->sftp->is_writeable($this->_interfaceFile) && !$return){
                throw new Exception("Interface file is not writable");
            }
        } else if (!@is_writable($this->_interfaceFile) && !$return){
            throw new Exception("Interface file is not writable");
        }

        $knownAddresses = ['address', 'netmask', 'gateway', 'broadcast', 'network'];

        $buffer = [];
        $buffer[] = "#This file is generated by MangoSpot Network Manager library";
        $buffer[] = "#" . date('r');
        $buffer[] = '';
        if($this->source){
            $buffer[] = "source ".$this->source;
            $buffer[] = '';
        }
        foreach ($this->Adaptors as $adaptor => $detail) {
            if ($detail->auto) $buffer[] = "auto $adaptor";
            foreach ($detail->allows as $item)
                $buffer[] = "allow-$item $adaptor";
            $buffer[] = "iface $adaptor {$detail->family} {$detail->method}";
            foreach ($knownAddresses as $item)
                if (isset($detail->$item)) $buffer[] = " $item {$detail->$item}";
            foreach ($detail->Unknown as $item)
                $buffer[] = " $item";
            $buffer[] = '';
        }
        $imploded = implode("\n", $buffer);
        if ($return){
            return $imploded;
        }
        
        if($this->sftp){
            return $this->sftp->put($this->_interfaceFile, $imploded);
        } else {
            return file_put_contents($this->_interfaceFile, $imploded);
        }
    }

    /**
     * add a new adaptor to Adaptor property
     * @param Adaptor $Adaptor
     * @throws Exception
     */
    public function add($Adaptor)
    {
        if (!$this->_interfaceParsed)
            throw new Exception("Interface file is not parsed");
        if (array_key_exists($Adaptor->name, $this->Adaptors))
            throw new Exception("{$Adaptor->name} already exist is adaptor list");
        $this->Adaptors[$Adaptor->name] = $Adaptor;
    }
}

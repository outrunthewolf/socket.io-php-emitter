<?php

namespace SocketIO;

if (!function_exists('msgpack_pack')) {
  require(__DIR__ . '/msgpack_pack.php');
}

class Emitter 
{
    /**
     * Event
     * @var int
     */
    public $event = 2;

    /**
     * Binary event
     * @var int
     */
    public $binaryEvent = 5;

    /**
     * Redis
     * @var \Redis
     */
    protected $redis;

    /**
     * Event key
     * @var string
     */
    protected $key;

    /**
     * Rooms
     * @var array
     */
    protected $_rooms = array();

    /**
     * Flags
     * @var array
     */
    protected $_flags = array();


    public function __construct($redis = false, $opts = array()) {

        if (is_array($redis)) {
            $opts = $redis;
            $redis = false;
        }

        // Apply default arguments
        $opts = array_merge(array('host' => 'localhost', 'port' => 6379), $opts);

        if ($redis == false) {
            // Default to phpredis
            if (extension_loaded('redis')) {
                if (!isset($opts['socket']) && !isset($opts['host'])) throw new \Exception('Host should be provided when not providing a redis instance');
                if (!isset($opts['socket']) && !isset($opts['port'])) throw new \Exception('Port should be provided when not providing a redis instance');

                $redis = new \Redis();
                if (isset($opts['socket'])) {
                    $redis->connect($opts['socket']);
                } else {
                    $redis->connect($opts['host'], $opts['port']);
                }
            } else {
                $redis = new \TinyRedisClient($opts['host'].':'.$opts['port']);
            }
        }

        // Fail on unsupported redis
        if (!is_callable(array($redis, 'publish'))) {
          throw new \Exception('The Redis client provided is invalid. The client needs to implement the publish method. Try using the default client.');
        }

        $this->redis = $redis;
        $this->key = (isset($opts['key']) ? $opts['key'] : 'socket.io');

        $this->_rooms = array();
        $this->_flags = array();
    }

    /**
    * Flags
    *
    * @param string $flag
    * @return SocketIO\Emitter
    */
    public function __get($flag) {
        $this->_flags[$flag] = TRUE;
        return $this;
    }

    /**
     * Read flags
     *
     * @param string $flag
     * @return bool
     */
    private function readFlag($flag) {
        return isset($this->_flags[$flag]) ? $this->_flags[$flag] : false;
    }

    /**
     * Broadcasting
     *
     * @param string $room
     * @return SocketIO\Emitter
     */
    public function in($room) {
        if (!in_array($room, $this->_rooms)) {
            $this->_rooms[] = $room;
        }

        return $this;
    }

    /**
     * Alias for $this->in()
     *
     * @param string $room
     * @return SocketIO\Emitter
     */
    public function to($room) {
        return $this->in($room);
    }

    /**
    * Namespaces
    *
    * @param string $nsp
    * @return SocketIO\Emitter
    */
    public function of($nsp) {
        $this->_flags['nsp'] = $nsp;
        return $this;
    }

    /**
     * Emit the data
     *
     * @return SocketIO\Emitter
     */
    public function emit($key = '', $data) {

        // Try publish by normal means, catch other wise (HHVM issue)
        try {
            $this->redis->publish($this->key . $key, json_encode($data));
        } catch() { 
            $this->redis->__call('publish', array($this->key . $key, json_encode($data)));
        }
        
        return $this;
    }
}



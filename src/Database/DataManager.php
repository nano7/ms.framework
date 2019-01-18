<?php namespace Nano7\Framework\Database;

use Nano7\Framework\Support\Arr;
use Nano7\Framework\Support\Str;
use Nano7\Framework\Foundation\Application;

class DataManager
{
    /**
     * The application instance.
     *
     * @var Application
     */
    protected $app;

    /**
     * @var array
     */
    protected $config = [];

    /**
     * The registered custom connection creators.
     *
     * @var array
     */
    protected $customCreators = [];

    /**
     * The array of created "connections".
     *
     * @var array
     */
    protected $connections = [];

    /**
     * Create a new manager instance.
     *
     * @param  Application  $app
     * @param  array        $config
     * @return void
     */
    public function __construct($app, $config)
    {
        $this->app    = $app;
        $this->config = $config;
    }

    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function getDefaultConnection()
    {
        return Arr::get($this->config, 'default');
    }

    /**
     * Get a connection instance.
     *
     * @param  string  $connection
     * @return ConnectionInterface
     */
    public function connection($connection = null)
    {
        $connection = $connection ?: $this->getDefaultConnection();

        if (is_null($connection)) {
            throw new \InvalidArgumentException(sprintf('Unable to resolve NULL connection for [%s].', get_called_class()));
        }

        // If the given connection has not been created before, we will create the instances
        // here and cache it so we can return it next time very quickly. If there is
        // already a connection created by this name, we'll just return that instance.
        if (! isset($this->connections[$connection])) {
            $this->connections[$connection] = $this->createConnection($connection);
        }

        return $this->connections[$connection];
    }

    /**
     * Create a new connection instance.
     *
     * @param  string  $connection
     * @return ConnectionInterface
     *
     * @throws \InvalidArgumentException
     */
    protected function createConnection($connection)
    {
        // Load config connection
        $config = Arr::get($this->config, 'connections.' . $connection);
        if (is_null($config)) {
            throw new \InvalidArgumentException("config connection [$connection] not found.");
        }

        $driver = Arr::get($config, 'driver');
        if (is_null($driver)) {
            throw new \InvalidArgumentException("driver connection [$connection] not information.");
        }

        // We'll check to see if a creator method exists for the given connection. If not we
        // will check for a custom connection creator, which allows developers to create
        // connections using their own customized connection creator Closure to create it.
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver, $config);
        } else {
            $method = 'create' . Str::studly($driver) . 'Driver';

            if (method_exists($this, $method)) {
                return $this->$method($config);
            }
        }
        throw new \InvalidArgumentException("driver [$driver] connection [$connection] not supported.");
    }

    /**
     * Call a custom connection creator.
     *
     * @param  string  $driver
     * @param  array   $config
     * @return ConnectionInterface
     */
    protected function callCustomCreator($driver, $config)
    {
        return $this->customCreators[$driver]($this->app, $config);
    }

    /**
     * Register a custom driver connection creator Closure.
     *
     * @param  string    $driver
     * @param  \Closure  $callback
     * @return $this
     */
    public function extend($driver, \Closure $callback)
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Get all of the created "connections".
     *
     * @return array
     */
    public function getConnections()
    {
        return $this->connections;
    }

    /**
     * Dynamically call the default connection instance.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->connection(), $method], $parameters);
    }
}
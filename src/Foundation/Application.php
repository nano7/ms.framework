<?php namespace Nano7\Framework\Foundation;

use Slim\App;
use Dotenv\Dotenv;
use Slim\Container;
use Slim\Interfaces\RouteInterface;
use Slim\Interfaces\RouteGroupInterface;

/**
 * Class Application.
 *
 * @method RouteInterface get($pattern, $callable)
 * @method RouteInterface post($pattern, $callable)
 * @method RouteInterface put($pattern, $callable)
 * @method RouteInterface delete($pattern, $callable)
 * @method RouteInterface patch($pattern, $callable)
 * @method RouteInterface options($pattern, $callable)
 * @method RouteInterface map($pattern, $callable)
 * @method RouteInterface redirect($from, $to, $status = 302)
 * @method RouteGroupInterface group($pattern, $callable)
 */
class Application
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @var App
     */
    protected $app;

    /**
     * The current globally available container (if any).
     *
     * @var static
     */
    protected static $instance;

    /**
     * The base path for the Laravel installation.
     *
     * @var string
     */
    protected $basePath;

    /**
     * Lista de instancias.
     *
     * @var array
     */
    protected $instances = [];

    /**
     * Lista de binds compartilhados.
     *
     * @var array
     */
    protected $shareds = [];

    /**
     * Create a new application instance.
     *
     * @param  string|null $basePath
     * @return void
     */
    public function __construct($basePath = null)
    {
        error_reporting(E_ALL & ~E_NOTICE); // Exibe todos os erros, warnings, menos as noticias

        if ($basePath) {
            $this->setBasePath($basePath);
        }

        static::setInstance($this);
        $this->registerEnv();
        $this->registerBases();

        $this->container = new Container();

        $this->app = new App($this->container);
    }

    /**
     * Setar instancia do conteiner.
     *
     * @param Application $container
     * @return static
     */
    public static function setInstance(Application $container)
    {
        return static::$instance = $container;
    }

    /**
     * Set the base path for the application.
     *
     * @param  string $basePath
     * @return $this
     */
    public function setBasePath($basePath)
    {
        $this->basePath = rtrim($basePath, '\/');

        $this->bindPathsInContainer();

        return $this;
    }

    /**
     * Bind all of the application paths in the container.
     *
     * @return void
     */
    protected function bindPathsInContainer()
    {
        $this->instance('path.base',   $this->basePath());
        $this->instance('path.apps',   $this->basePath('apps'));
    }

    /**
     * Get the base path of the Laravel installation.
     *
     * @param  string $path Optionally, a path to append to the base path
     * @return string
     */
    public function basePath($path = '')
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Register the env bindings into the container.
     *
     * @return void
     */
    protected function registerEnv()
    {
        $file_env = $this->basePath('.env');

        $env = new Dotenv($this->basePath(), '.env');
        if (file_exists($file_env)) {
            $env->load();
        }
    }

    /**
     * Registrar objetos bases.
     */
    protected function registerBases()
    {
        // Events
        $this->singleton('events', function() {
            $events = new Dispatcher($this);

            return $events;
        });
    }

    /**
     * Register an existing instance as shared in the container.
     *
     * @param  string  $abstract
     * @param  mixed   $instance
     * @return void
     */
    public function instance($name, $instance)
    {
        $this->instances[$name] = $instance;
    }

    /**
     * Registrar novo bind.
     *
     * @param $name
     * @param $concrete
     * @param bool $shared
     * @return $this
     */
    public function bind($name, $concrete, $shared = false)
    {
        $this->container[$name] = $concrete;

        // Zerar instancia
        if ($shared && isset($this->instances[$name])) {
            unset($this->instances[$name]);
        }

        if ($shared) {
            $this->shareds[$name] = true;
        }

        return $this;
    }

    /**
     * Alias para bind compartilhado.
     *
     * @param $name
     * @param $concrete
     * @return $this
     */
    public function singleton($name, $concrete)
    {
        return $this->bind($name, $concrete, true);
    }

    /**
     * Make.
     *
     * @param $name
     * @return mixed
     */
    public function make($name)
    {
        // Verificar se deve carregar compartilhado
        if (isset($this->shareds[$name]) && $this->shareds[$name]) {
            if (array_key_exists($name, $this->instances)) {
                return $this->instances[$name];
            }
        }

        // Carregar instancia
        $instance = $this->container->get($name);

        // Verificar se deve guardar instancia
        if (isset($this->shareds[$name]) && $this->shareds[$name]) {
            $this->instance($name, $instance);
        }

        return $instance;
    }

    /**
     * @return Application
     */
    public static function getInstance()
    {
        return static::$instance;
    }

    /**
     * @return App
     */
    public function getApp()
    {
        return $this->app;
    }

    /**
     * @return Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    function __call($name, $arguments)
    {
        return call_user_func_array([$this->app, $name], $arguments);
    }
}
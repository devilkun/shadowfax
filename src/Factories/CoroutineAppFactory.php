<?php

namespace HuangYi\Shadowfax\Factories;

use HuangYi\Shadowfax\Contracts\AppFactory;
use HuangYi\Shadowfax\FrameworkBootstrapper;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

class CoroutineAppFactory implements AppFactory
{
    use RebindsAbstracts;

    /**
     * The application pool.
     *
     * @var \Swoole\Coroutine\Channel
     */
    protected $pool;

    /**
     * The application bootstrapper.
     *
     * @var \HuangYi\Shadowfax\FrameworkBootstrapper
     */
    protected $bootstrapper;

    /**
     * The pool capacity.
     *
     * @var int
     */
    protected $capacity;

    /**
     * CoroutineAppFactory constructor.
     *
     * @param  \HuangYi\Shadowfax\FrameworkBootstrapper  $bootstrapper
     * @param  int  $capacity
     * @return void
     */
    public function __construct(FrameworkBootstrapper $bootstrapper, $capacity = 10)
    {
        $this->bootstrapper = $bootstrapper;
        $this->capacity = $capacity;

        $this->init();
    }

    /**
     * Initialize the application factory.
     *
     * @return void
     */
    protected function init()
    {
        $this->pool = new Channel($this->capacity);

        for ($i = 0; $i < $this->capacity; $i++) {
            $this->pool->push($this->bootstrapper->boot());
        }
    }

    /**
     * Make a Laravel/Lumen application.
     *
     * @return \Illuminate\Contracts\Container\Container
     */
    public function make(): ContainerContract
    {
        $app = $this->pool->pop();

        Coroutine::getContext()->laravel = $app;

        shadowfax_set_global_container($app);

        return $app;
    }

    /**
     * Recycle the Laravel/Lumen application.
     *
     * @param  \Illuminate\Contracts\Container\Container  $app
     * @return void
     */
    public function recycle(ContainerContract $app)
    {
        $this->rebindAbstracts($app);

        $this->pool->push($app);
    }

    /**
     * Get the application pool.
     *
     * @return \Swoole\Coroutine\Channel
     */
    public function getPool()
    {
        return $this->pool;
    }

    /**
     * Get the pool capacity.
     *
     * @return int
     */
    public function getCapacity()
    {
        return $this->capacity;
    }
}

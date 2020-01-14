<?php

namespace HuangYi\Shadowfax\Server;

use HuangYi\Shadowfax\Composer;
use HuangYi\Shadowfax\ContainerRewriter;
use HuangYi\Shadowfax\Server\Events\ControllerRequestEvent;
use HuangYi\Watcher\Commands\Fswatch;
use HuangYi\Watcher\Watcher;
use Swoole\Http\Server;

class Starter extends Action
{
    /**
     * The swoole server events.
     *
     * @var array
     */
    protected $events = [
        'Start', 'ManagerStart', 'WorkerStart', 'Request', 'Task',
        'WorkerStop', 'ManagerStop', 'Shutdown',
    ];

    /**
     * The fswatch events.
     *
     * @var array
     */
    protected $fswatchEvents = [
        Fswatch::CREATED, Fswatch::UPDATED, Fswatch::REMOVED, Fswatch::RENAMED,
        Fswatch::MOVED_FROM, Fswatch::MOVED_TO,
    ];

    /**
     * Start the server.
     *
     * @return void
     */
    public function start()
    {
        $this->rewriteContainer();

        $server = $this->createServer();

        $this->shadowfax()->instance(Server::class, $server);

        $this->output->writeln(sprintf(
            '<info>Starting the Shadowfax server: %s:%d</info>',
            $server->host,
            $server->port
        ));

        $this->createControllerServer($server);

        $this->createWatcherProcess($server);

        $this->unregisterAutoload();

        $server->start();
    }

    /**
     * Rewrite the illuminate container.
     *
     * @return void
     */
    protected function rewriteContainer()
    {
        $rewriter = new ContainerRewriter;

        $rewriter->rewrite();

        $this->shadowfax()->instance(ContainerRewriter::class, $rewriter);
    }

    /**
     * Create the server.
     *
     * @return \Swoole\Http\Server
     */
    protected function createServer()
    {
        $server = new Server(
            $this->getHost(),
            $this->getPort(),
            $this->getMode()
        );

        $server->set($this->getSettings());

        foreach ($this->events as $name) {
            if ($name == 'Start' && $server->mode == SWOOLE_BASE) {
                continue;
            }

            $eventClass = "\\HuangYi\\Shadowfax\\Server\\Events\\{$name}Event";

            $server->on($name, [new $eventClass($this->output), 'handle']);
        }

        return $server;
    }

    /**
     * Create the controller server.
     *
     * @param  \Swoole\Http\Server  $server
     * @return void
     */
    protected function createControllerServer($server)
    {
        $ctl = $server->addListener(
            $this->getControllerHost(),
            $this->getControllerPort(),
            SWOOLE_SOCK_TCP
        );

        $ctl->on('Request', [new ControllerRequestEvent($this->output, $server), 'handle']);
    }

    /**
     * Unregister autoload.
     *
     * @return void
     */
    protected function unregisterAutoload()
    {
        $this->shadowfax()->make(Composer::class)->unregister();
    }

    /**
     * Create the watcher process.
     *
     * @param  \Swoole\Http\Server  $server
     * @return void
     */
    protected function createWatcherProcess($server)
    {
        if ($this->input->getOption('watch') === false) {
            return;
        }

        $command = new Fswatch($this->shadowfax()->basePath('../../..'));
        $command->setEvent($this->getFswatchEvents());
        $command->setFilterFrom($this->getFswatchFilterPath());

        $watcher = new Watcher($command);
        $watcher->onChange(function () use ($server) {
            $server->reload();
        });

        $server->addProcess($watcher->getProcess());

        $watcher->watch(false);
    }

    /**
     * Get the fswatch events.
     *
     * @return int
     */
    protected function getFswatchEvents()
    {
        $events = 0;

        foreach ($this->fswatchEvents as $event) {
            $events |= $event;
        }

        return $events;
    }

    /**
     * Get the fswatch filter rules path.
     *
     * @return string
     */
    protected function getFswatchFilterPath()
    {
        $path = $this->shadowfax()->basePath('../../../.watch');

        if (! file_exists($path)) {
            $path = __DIR__.'/../../.watch';
        }

        return realpath($path);
    }

    /**
     * Get the server host.
     *
     * @return string
     */
    protected function getHost()
    {
        if ($host = $this->input->getOption('host')) {
            return $host;
        }

        return $this->config('host', '127.0.0.1');
    }

    /**
     * Get the server port.
     *
     * @return int
     */
    protected function getPort()
    {
        if ($port = $this->input->getOption('port')) {
            return (int) $port;
        }

        return $this->config('port', '1215');
    }

    /**
     * Get the server mode.
     *
     * @return int
     */
    protected function getMode()
    {
        return $this->config('mode', 'base') == 'process' ?
            SWOOLE_PROCESS : SWOOLE_BASE;
    }

    /**
     * Get the Swoole server settings.
     *
     * @return array
     */
    protected function getSettings()
    {
        $settings = $this->config('server', []);
        $isCoroutineEnabled = $this->isCoroutineEnabled();

        $settings['enable_coroutine'] = $isCoroutineEnabled;
        $settings['task_enable_coroutine'] = $isCoroutineEnabled;

        return $settings;
    }

    /**
     * Determine if the coroutine is enabled.
     *
     * @return int
     */
    protected function isCoroutineEnabled()
    {
        return $this->config('enable_coroutine', 0);
    }
}

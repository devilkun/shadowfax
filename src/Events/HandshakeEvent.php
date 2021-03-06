<?php

namespace HuangYi\Shadowfax\Events;

class HandshakeEvent
{
    /**
     * The HTTP request instance.
     *
     * @var \Swoole\Http\Request
     */
    public $request;

    /**
     * The HTTP response instance.
     *
     * @var \Swoole\Http\Response
     */
    public $response;

    /**
     * Create a new HandshakeEvent instance.
     *
     * @param  \Swoole\Http\Request  $request
     * @param  \Swoole\Http\Response  $response
     * @return void
     */
    public function __construct($request, $response)
    {
        $this->request = $request;
        $this->response = $response;
    }
}

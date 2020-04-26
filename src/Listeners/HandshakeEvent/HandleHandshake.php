<?php

namespace HuangYi\Shadowfax\Listeners\HandshakeEvent;

use HuangYi\Shadowfax\Events\HandshakeEvent;
use HuangYi\Shadowfax\Events\OpenEvent;
use HuangYi\Shadowfax\Http\Kernel;
use HuangYi\Shadowfax\Http\Request;
use HuangYi\Shadowfax\Listeners\HasHelpers;
use HuangYi\Shadowfax\WebSocket\Connection;
use HuangYi\Shadowfax\WebSocket\RequestVerifier;

class HandleHandshake
{
    use HasHelpers;

    /**
     * Handle the event.
     *
     * @param  \HuangYi\Shadowfax\Events\HandshakeEvent  $event
     * @return void
     */
    public function handle(HandshakeEvent $event)
    {
        $this->handleWithoutException(function ($app) use ($event) {
            $response = $app->make(Kernel::class)->handle(
                $request = Request::make($event->request), true
            );

            if ($this->isSuccessful($response)) {
                Connection::init(shadowfax('server'), $request);
            }

            $this->formatResponse($request, $response);

            $response->send($event->response);

            if ($this->isSuccessful($response)) {
                shadowfax('events')->dispatch(new OpenEvent(shadowfax('server'), $event->request));
            }
        });
    }

    /**
     * Format the response.
     *
     * @param  \HuangYi\Shadowfax\Http\Request  $request
     * @param  \HuangYi\Shadowfax\Http\Response  $response
     * @return void
     */
    protected function formatResponse($request, $response)
    {
        if ($this->isSuccessful($response)) {
            $verifier = new RequestVerifier($request->getIlluminateRequest());

            $response->getIlluminateResponse()->withHeaders([
                'Upgrade' => 'websocket',
                'Connection' => 'Upgrade',
                'Sec-WebSocket-Accept' => $verifier->getSecWebSocketAccept(),
            ]);

            $response->getIlluminateResponse()->setStatusCode(101);
        }

        $response->getIlluminateResponse()->setContent('');
    }

    /**
     * Determine whether handshake is successful.
     *
     * @param  \HuangYi\Shadowfax\Http\Response  $response
     * @return bool
     */
    protected function isSuccessful($response)
    {
        $status = $response->getIlluminateResponse()->getStatusCode();

        return $status == 101 || ($status >= 200 && $status < 300);
    }
}
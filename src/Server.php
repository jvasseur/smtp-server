<?php

namespace Jhome\Smtp\Server;

use Evenement\EventEmitter;
use React\Socket\ServerInterface as SocketServerInterface;
use React\Socket\ConnectionInterface;

class Server extends EventEmitter
{
    private $io;

    private $domain;

    public function __construct(SocketServerInterface $io, $domain)
    {
        $this->io = $io;
        $this->domain = $domain;

        $this->io->on('connection', function ($conn) {
            $connection = new ConnectionHandler($this, $conn);

            $conn->on('data', [$connection, 'feed']);
            $conn->write('220 ' . $this->domain . ' SMTP' . "\r\n");
        });
    }

    public function getDomain()
    {
        return $this->domain;
    }
}

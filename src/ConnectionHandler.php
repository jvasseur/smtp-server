<?php

namespace Jhome\Smtp\Server;

use React\Socket\Connection;

class ConnectionHandler
{
    const STATE_COMMAND = 0;
    const STATE_DATA = 1;

    /**
     * @var Server
     */
    private $server;

    /**
     * @var Connection
     */
    private $conn;

    private $state = self::STATE_COMMAND;

    private $buffer = '';

    /**
     * @var Mail|null
     */
    private $mail = null;

    public function __construct(Server $server, Connection $conn)
    {
        $this->server = $server;
        $this->conn = $conn;
    }

    public function feed($data)
    {
        $this->buffer .= $data;

        if ($this->state === self::STATE_COMMAND) {
            if (false !== strpos($this->buffer, "\r\n")) {
                list($command, $this->buffer) = explode("\r\n", $this->buffer, 2);

                $this->handleCommand($command);
            }
        } else {
            $lines = explode("\r\n", $this->buffer);
            $this->buffer = array_pop($lines);

            if ($end = '.' === end($lines)) {
                array_pop($lines);
            }

            $data = '';
            foreach ($lines as $line) {
                if ('..' === substr($line, 0, 2)) {
                    $data .= substr($line, 1) . "\r\n";
                } else {
                    $data .= $line . "\r\n";
                }
            }

            $this->mail->emit('data', [$data]);

            if ($end) {
                $this->mail->emit('end');

                $this->state = self::STATE_COMMAND;
                $this->push('250 Ok');
            }
        }
    }

    private function handleCommand($line)
    {
        if (false === strpos($line, ' ')) {
            $command = $line;
            $arg = null;
        } else {
            list($command, $arg) = explode(' ', $line, 2);
        }

        $command = strtoupper($command);

        switch ($command) {
            case 'HELO':
                $this->push('250 ' . $this->server->getDomain());
                break;
            case 'EHLO':
                $this->push('250 ' . $this->server->getDomain());
                break;
            case 'QUIT':
                $this->push('221 Bye');
                $this->conn->end();
                break;
            case 'MAIL':
                if ($this->mail !== null) {
                    $this->push('503 Error: nested MAIL command');
                } elseif (preg_match('/^FROM:[ ]*<(.+)>$/', $arg, $matches)) {
                    $this->mail = new Mail();
                    $this->mail->setFrom($matches[1]);

                    $this->push('250 Ok');
                } else {
                    $this->push('501 Syntax: MAIL FROM: <address>');
                }
                break;
            case 'RCPT':
                if ($this->mail === null) {
                    $this->push('503 Error: need MAIL command');
                } elseif (preg_match('/^TO:[ ]*<(.+)>$/', $arg, $matches)) {
                    $this->mail->addTo($matches[1]);

                    $this->push('250 Ok');
                } else {
                    $this->push('501 Syntax: RCPT TO: <address>');
                }
                break;
            case 'DATA':
                if ($this->mail === null) {
                    $this->push('503 Error: need MAIL command');
                } else {
                    $this->state = self::STATE_DATA;
                    $this->server->emit('mail', [$this->mail]);
                    $this->push('354 Ok');
                }
                break;
        }
    }

    private function push($msg)
    {
        $this->conn->write($msg . "\r\n");
    }
}

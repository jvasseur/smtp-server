<?php

namespace Jhome\Smtp\Server;

class ConnectionHandler
{
    const STATE_COMMAND = 0;
    const STATE_DATA = 1;

    /**
     * @var Server
     */
    private $server;

    private $conn;

    private $state = self::STATE_COMMAND;

    private $buffer = '';

    private $mail = null;

    public function __construct(Server $server, $conn)
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
            if (false !== strpos($this->buffer, "\r\n.\r\n")) {
                list($data, $this->buffer) = explode("\r\n.\r\n", $this->buffer, 2);

                $this->handleData($data . "\r\n");
                $this->mail->emit('end');

                $this->state = self::STATE_COMMAND;
                $this->push('250 Ok');
            } else {
                $data = $this->buffer;
                $this->buffer = '';

                $this->handleData($data);
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
                } elseif (preg_match('/^FROM:[ ]+<(.+)>$/', $arg, $matches)) {
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
                } elseif (preg_match('/^TO:[ ]+<(.+)>$/', $arg, $matches)) {
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

    private function handleData($data)
    {
        $data = str_replace("\r\n..", "\r\n.", $data);

        $this->mail->emit('data', [$data]);
    }

    private function push($msg)
    {
        $this->conn->write($msg . "\r\n");
    }
}

<?php

namespace Jhome\Smtp\Server;

use Evenement\EventEmitter;

class Mail extends EventEmitter
{
    /**
     * @var string
     */
    private $from;

    /**
     * @var array<string>
     */
    private $to = [];

    public function getFrom()
    {
        return $this->from;
    }

    public function setFrom($from)
    {
        $this->from = $from;
    }

    public function getTo()
    {
        return $this->to;
    }

    public function addTo($to)
    {
        $this->to[] = $to;
    }
}

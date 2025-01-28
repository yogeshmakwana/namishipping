<?php

namespace Namicargo\Shipping\Logger;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

if (!defined('BP')) {
    define('BP', dirname(__DIR__));
}

class NamicargoShippingLogger extends Logger
{
    public function __construct(
        string $name = 'NamicargoShipping',
        array $handlers = [],
        array $processors = []
    ) {
        $handlers[] = new StreamHandler(BP . '/var/log/NamicargoShipping.log', Logger::DEBUG);
        parent::__construct($name, $handlers, $processors);
    }
}

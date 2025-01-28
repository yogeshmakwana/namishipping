<?php

namespace Namicargo\Shipping\Logger;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

if (!defined('BP')) {
    define('BP', dirname(__DIR__));
}

class NamicargoTransferCustomAttributeLogger extends Logger
{
    public function __construct(
        string $name = 'NamicargoTransferCustomAttribute',
        array $handlers = [],
        array $processors = []
    ) {
        $handlers[] = new StreamHandler(BP . '/var/log/NamicargoSendOrderToApi.log', Logger::DEBUG);
        parent::__construct($name, $handlers, $processors);
    }
}

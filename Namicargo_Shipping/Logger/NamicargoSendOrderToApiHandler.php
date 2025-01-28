<?php

namespace Namicargo\Shipping\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

if (!defined('BP')) {
    define('BP', dirname(__DIR__));
}

class NamicargoSendOrderToApiHandler extends Base
{
    protected $fileName = BP . '/var/log/NamicargoSendOrderToApi.log';
    protected $loggerType = Logger::DEBUG;
}

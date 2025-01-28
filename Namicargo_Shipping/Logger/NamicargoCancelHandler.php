<?php

namespace Namicargo\Shipping\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

if (!defined('BP')) {
    define('BP', dirname(__DIR__));
}

class NamicargoCancelHandler extends Base
{
    protected $fileName = BP . '/var/log/NamicargoCancel.log';
    protected $loggerType = Logger::DEBUG;
}

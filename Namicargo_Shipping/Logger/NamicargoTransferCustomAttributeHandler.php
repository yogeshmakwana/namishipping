<?php

namespace Namicargo\Shipping\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

if (!defined('BP')) {
    define('BP', dirname(__DIR__));
}

class NamicargoTransferCustomAttributeHandler extends Base
{
    protected $fileName = BP . '/var/log/NamicargoTransferCustomAttribute.log';
    protected $loggerType = Logger::DEBUG;
}

<?php

namespace Namicargo\Shipping\Logger;

use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

if (!defined('BP')) {
    define('BP', dirname(__DIR__));
}

class NamicargoShippingHandler extends Base
{
    protected $fileName = BP . '/var/log/NamicargoShipping.log';
    protected $loggerType = Logger::DEBUG;

    public function __construct(DriverInterface $filesystem, ?string $filePath = null, ?string $fileName = null)
    {
        parent::__construct($filesystem, $filePath, $fileName);
    }
}

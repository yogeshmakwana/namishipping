<?php

namespace Namicargo\Shipping\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Math\Random;

class InstallShippingData implements DataPatchInterface
{
    protected $configWriter;
    protected $random;

    public function __construct(
        WriterInterface $configWriter,
        Random $random
    ) {
        $this->configWriter = $configWriter;
        $this->random = $random;
    }

    public function apply()
    {
        $bytes = $this->random->getRandomString(32);
        $token = bin2hex((string) $bytes);
        $this->configWriter->save('carriers/namicargoshipping/token', $token);

        return $this;
    }

    public static function getDependencies()
    {
        return [];
    }

    public static function getVersion()
    {
        return '1.0.0';
    }

    public function getAliases()
    {
        return [];
    }
}

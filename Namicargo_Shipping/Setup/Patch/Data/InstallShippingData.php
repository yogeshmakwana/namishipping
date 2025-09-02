<?php

namespace Namicargo\Shipping\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Math\Random;

/**
 * Class InstallShippingData
 *
 * Data patch to install shipping configuration data.
 */
class InstallShippingData implements DataPatchInterface
{
    /**
     * @var WriterInterface
     * Configuration writer interface.
     */
    protected $configWriter;

    /**
     * @var Random
     * Random string generator.
     */
    protected $random;

    /**
     * Constructor
     *
     * @param WriterInterface $configWriter
     * @param Random $random
     */
    public function __construct(
        WriterInterface $configWriter,
        Random $random
    ) {
        $this->configWriter = $configWriter;
        $this->random = $random;
    }

    /**
     * Apply the data patch.
     *
     * @return $this
     */
    public function apply()
    {
        $bytes = $this->random->getRandomString(32);
        $token = bin2hex((string) $bytes);
        $this->configWriter->save('carriers/namicargoshipping/token', $token);

        return $this;
    }

    /**
     * Get dependencies for the patch.
     *
     * @return array
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * Get the version of the patch.
     *
     * @return string
     */
    public static function getVersion()
    {
        return '1.0.0';
    }

    /**
     * Get aliases for the patch.
     *
     * @return array
     */
    public function getAliases()
    {
        return [];
    }
}

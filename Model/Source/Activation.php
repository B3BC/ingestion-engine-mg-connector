<?php

namespace IngestionEngine\Connector\Model\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class Activation
 *
 * @package   IngestionEngine\Connector\Model\Source\Filters
 * @author    IngestionEngine <sales@silksoftware.com>
 * @copyright 2020 IngestionEngine
 * @license   https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://www.silksoftware.com/
 */
class Activation implements ArrayInterface
{
    /** const keys */
    const STATUS_ENABLED = '1';
    const STATUS_DISABLED = '2';

    /**
     * Return array of options for the status filter
     *
     * @return array Format: array('<value>' => '<label>', ...)
     */
    public function toOptionArray()
    {
        return [
            [
                'label' => __('Enabled'),
                'value' => self::STATUS_ENABLED
            ],
            [
                'label' => __('Disabled'),
                'value' => self::STATUS_DISABLED
            ],
        ];
    }
}

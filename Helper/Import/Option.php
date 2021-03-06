<?php

namespace IngestionEngine\Connector\Helper\Import;

use IngestionEngine\Connector\Helper\Config as ConfigHelper;
use IngestionEngine\Connector\Helper\Import\Entities;
use Magento\Catalog\Model\Product as BaseProductModel;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Zend_Db_Expr as Expr;

/**
 * Class Option
 *
 * @category  Class
 * @package   IngestionEngine\Connector\Helper\Import
 * @author    IngestionEngine <sales@silksoftware.com>
 * @copyright 2020 IngestionEngine
 * @license   https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://www.silksoftware.com/
 */
class Option extends Entities
{
    /**
     * Option constructor
     *
     * @param ResourceConnection $connection
     * @param DeploymentConfig   $deploymentConfig
     * @param BaseProductModel   $product
     * @param ConfigHelper       $configHelper
     */
    public function __construct(
        ResourceConnection $connection,
        DeploymentConfig $deploymentConfig,
        BaseProductModel $product,
        ConfigHelper $configHelper
    ) {
        parent::__construct($connection, $deploymentConfig, $product, $configHelper);
    }

    /**
     * Match Magento Id with code
     *
     * @param string $pimKey
     * @param string $entityTable
     * @param string $entityKey
     * @param string $import
     * @param string $prefix
     *
     * @return \IngestionEngine\Connector\Helper\Import\Entities
     */
    public function matchEntity($pimKey, $entityTable, $entityKey, $import, $prefix = null)
    {
        /** @var string $localeCode */
        $localeCode = $this->configHelper->getDefaultLocale();
        /** @var \Magento\Framework\DB\Adapter\AdapterInterface $connection */
        $connection = $this->connection;
        /** @var string $tableName */
        $tableName = $this->getTableName($import);

        $connection->delete($tableName, [$pimKey . ' = ?' => '']);
        /** @var string $ingestionengineConnectorTable */
        $ingestionengineConnectorTable = $this->getTable('ingestionengine_connector_entities');
        /** @var string $entityTable */
        $entityTable = $this->getTable($entityTable);

        if ($entityKey == 'entity_id') {
            $entityKey = $this->getColumnIdentifier($entityTable);
        }

        /* Connect existing Magento options to new IngestionEngine items */
        // Get existing entities from IngestionEngine table
        /** @var Select $select */
        $select = $connection->select()->from($ingestionengineConnectorTable, ['entity_id' => 'entity_id'])->where(
            'import = ?',
            'option'
        );
        /** @var string[] $existingEntities */
        $existingEntities = $connection->query($select)->fetchAll();
        $existingEntities = array_column($existingEntities, 'entity_id');

        // Get all entities that are being imported and already present in Magento
        $select = $connection->select()->from(
            ['t' => $tableName],
            ['label' => 't.labels-' . $localeCode, 'code' => 't.code', 'attribute' => 't.attribute']
        )->joinInner(
            ['e' => 'eav_attribute_option_value'],
            '`labels-' . $localeCode . '` = e.value'
        )->joinInner(
            ['o' => 'eav_attribute_option'],
            'o.`option_id` = e.`option_id`'
        )->joinInner(
            ['a' => 'eav_attribute'],
            'o.`attribute_id` = a.`attribute_id` AND t.`attribute` = a.`attribute_code`'
        )->where('e.store_id = ?', 0);
        /** @var string $query */
        $query = $connection->query($select);
        /* Use new error-free separator */
        $entityCodeColumnName = ($prefix ? 'CONCAT(t.`' . $prefix . '`, "-", t.`' . $pimKey . '`)' : 't.`' . $pimKey . '`');

        /* Legacy: update columns still using former "_" separator */
        /** @var string $oldEntityCodeColumnName */
        $oldEntityCodeColumnName = ($prefix ? 'CONCAT(t.`' . $prefix . '`, "_", t.`' . $pimKey . '`)' : 't.`' . $pimKey . '`');

        /** @var string $update */
        $update = 'UPDATE `' . $ingestionengineConnectorTable . '` AS `e`, `' . $tableName . '` AS `t` SET e.code = ' . $entityCodeColumnName . ' WHERE e.code = ' . $oldEntityCodeColumnName . ' AND e.`import` = "' . $import . '"';
        $connection->query($update);

        /** @var mixed $row */
        while ($row = $query->fetch()) {
            // Create a row in IngestionEngine table for options present in Magento and IngestionEngine that were never imported before
            if (!in_array($row['option_id'], $existingEntities)) {
                /** @var string[] $values */
                $values = [
                    'import'    => 'option',
                    'code'      => $row['attribute'] . '-' . $row['code'],
                    'entity_id' => $row['option_id'],
                ];
                $connection->insertOnDuplicate($ingestionengineConnectorTable, $values);
            }
        }

        /* Continue with original matchEntities */
        /* Update entity_id column from ingestionengine_connector_entities table */
        $connection->query(
            '
            UPDATE `' . $tableName . '` t
            SET `_entity_id` = (
                SELECT `entity_id` FROM `' . $ingestionengineConnectorTable . '` c
                WHERE ' . ($prefix ? 'CONCAT(t.`' . $prefix . '`, "-", t.`' . $pimKey . '`)' : 't.`' . $pimKey . '`') . ' = c.`code`
                    AND c.`import` = "' . $import . '"
            )
        '
        );

        /* Set entity_id for new entities */
        /** @var string $query */
        $query = $connection->query('SHOW TABLE STATUS LIKE "' . $entityTable . '"');
        /** @var mixed $row */
        $row = $query->fetch();

        $connection->query('SET @id = ' . (int)$row['Auto_increment']);
        /** @var array $values */
        $values = [
            '_entity_id' => new Expr('@id := @id + 1'),
            '_is_new'    => new Expr('1'),
        ];
        $connection->update($tableName, $values, '_entity_id IS NULL');

        /* Update ingestionengine_connector_entities table with code and new entity_id */
        /** @var Select $select */
        $select = $connection->select()->from(
            $tableName,
            [
                'import'    => new Expr("'" . $import . "'"),
                'code'      => $prefix ? new Expr('CONCAT(`' . $prefix . '`, "-", `' . $pimKey . '`)') : $pimKey,
                'entity_id' => '_entity_id',
            ]
        )->where('_is_new = ?', 1);

        $connection->query(
            $connection->insertFromSelect($select, $ingestionengineConnectorTable, ['import', 'code', 'entity_id'], 2)
        );

        /* Update entity table auto increment */
        /** @var string $count */
        $count = $connection->fetchOne(
            $connection->select()->from($tableName, [new Expr('COUNT(*)')])->where('_is_new = ?', 1)
        );
        if ($count) {
            /** @var string $maxCode */
            $maxCode = $connection->fetchOne(
                $connection->select()->from($ingestionengineConnectorTable, new Expr('MAX(`entity_id`)'))->where(
                    'import = ?',
                    $import
                )
            );
            /** @var string $maxEntity */
            $maxEntity = $connection->fetchOne(
                $connection->select()->from($entityTable, new Expr('MAX(`' . $entityKey . '`)'))
            );

            $connection->query(
                'ALTER TABLE `' . $entityTable . '` AUTO_INCREMENT = ' . (max((int)$maxCode, (int)$maxEntity) + 1)
            );
        }

        return $this;
    }
}

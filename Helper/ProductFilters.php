<?php

namespace IngestionEngine\Connector\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use IngestionEngine\Connector\Helper\Config as ConfigHelper;
use IngestionEngine\Connector\Helper\Store as StoreHelper;
use IngestionEngine\Connector\Helper\Locales as LocalesHelper;
use IngestionEngine\Connector\Model\Source\Filters\Completeness;
use IngestionEngine\Connector\Model\Source\Filters\Mode;
use IngestionEngine\Connector\Model\Source\Filters\Status;
use IngestionEngine\Connector\Model\Source\Filters\Update;

/**
 * Class ProductFilters
 *
 * @category  Class
 * @package   IngestionEngine\Connector\Helper
 * @author    IngestionEngine <sales@silksoftware.com>
 * @copyright 2020 IngestionEngine
 * @license   https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://www.silksoftware.com/
 */
class ProductFilters
{
    /**
     * This variable contains a ConfigHelper
     *
     * @var ConfigHelper $configHelper
     */
    protected $configHelper;
    /**
     * This variable contains a StoreHelper
     *
     * @var StoreHelper $storeHelper
     */
    protected $storeHelper;
    /**
     * This variable contains a LocaleHelper
     *
     * @var IngestionEngine\Connector\Helper\Locales $localesHelper
     */
    protected $localesHelper;

    /**
     * This variable contains a SearchBuilder
     *
     * @var SearchBuilder $searchBuilder
     */
    protected $searchBuilder;

    /**
     * ProductFilters constructor
     *
     * @param ConfigHelper         $configHelper
     * @param Store                $storeHelper
     * @param Locales              $localesHelper
     */
    public function __construct(
        ConfigHelper $configHelper,
        StoreHelper $storeHelper,
        LocalesHelper $localesHelper
    ) {
        $this->configHelper         = $configHelper;
        $this->storeHelper          = $storeHelper;
        $this->localesHelper        = $localesHelper;
    }

    /**
     * Get the filters for the product API query
     *
     * @param string|null $productFamily
     *
     * @return mixed[]|string[]
     */
    public function getFilters($productFamily = null)
    {
        /** @var mixed[] $mappedChannels */
        $mappedChannels = $this->configHelper->getMappedChannels();
        if (empty($mappedChannels)) {
            /** @var string[] $error */
            $error = [
                'error' => __('No website/channel mapped. Please check your configurations.'),
            ];

            return $error;
        }

        /** @var mixed[] $filters */
        $filters = [];
        /** @var mixed[] $search */
        $search = [];
        /** @var  $productFilterAdded */
        $productFilterAdded = false;
        /** @var string $mode */
        $mode = $this->configHelper->getFilterMode();
        if ($mode == Mode::ADVANCED) {
            /** @var mixed[] $advancedFilters */
            $advancedFilters = $this->getAdvancedFilters();
            // If product import gave a family, add it to the filter
            if ($productFamily) {
                if (isset($advancedFilters['search']['family'])) {
                    /**
                     * @var int      $key
                     * @var string[] $familyFilter
                     */
                    foreach ($advancedFilters['search']['family'] as $key => $familyFilter) {
                        if (isset($familyFilter['operator']) && $familyFilter['operator'] == 'IN') {
                            $advancedFilters['search']['family'][$key]['value'][] = $productFamily;
                            $productFilterAdded = true;

                            break;
                        }
                    }
                }

                if (!$productFilterAdded) {
                    /** @var string[] $familyFilter */
                    $familyFilter = ['operator' => 'IN', 'value' => [$productFamily]];
                    $advancedFilters['search']['family'][] = $familyFilter;
                    $productFilterAdded = true;
                }
            }

            if (!empty($advancedFilters['scope'])) {
                if (!in_array($advancedFilters['scope'], $mappedChannels)) {
                    /** @var string[] $error */
                    $error = [
                        'error' => __('Advanced filters contains an unauthorized scope, please add check your filters and website mapping.'),
                    ];

                    return $error;
                }

                return [$advancedFilters];
            }

            $search = $advancedFilters['search'];
        }

        // If import product gave a family, add this family to the search
        if ($productFamily && !$productFilterAdded) {
            $familyFilter = ['operator' => 'IN', 'value' => [$productFamily]];
            $search['family'][] = $familyFilter;
            $productFilterAdded = true;
        }

        /** @var string $channel */
        foreach ($mappedChannels as $channel) {
            /** @var string[] $filter */
            $filter = [
                'search' => $search,
                'scope'  => $channel,
            ];

            if ($mode == Mode::ADVANCED) {
                $filters[] = $filter;

                continue;
            }

            if ($this->configHelper->getCompletenessTypeFilter() !== Completeness::NO_CONDITION) {
                /** @var string[] $completeness */
                $completeness = reset($search['completeness']);
                if (!empty($completeness['scope']) && $completeness['scope'] !== $channel) {
                    $completeness['scope']  = $channel;
                    $search['completeness'] = [$completeness];

                    $filter['search'] = $search;
                }
            }

            /** @var string[] $locales */
            $locales = $this->storeHelper->getChannelStoreLangs($channel);
            if (!empty($locales)) {
                /** @var string $locales */
                $ingestionengineLocales = $this->localesHelper->getIngestionEngineLocales();
                if (!empty($ingestionengineLocales)) {
                    $locales = array_intersect($locales, $ingestionengineLocales);
                }

                /** @var string $locales */
                $locales           = implode(',', $locales);
                $filter['locales'] = $locales;
            }

            $filters[] = $filter;
        }

        return $filters;
    }


    /**
     * Get the filters for the product model API query
     *
     * @return mixed[]|string[]
     */
    public function getModelFilters()
    {
        /** @var mixed[] $mappedChannels */
        $mappedChannels = $this->configHelper->getMappedChannels();
        if (empty($mappedChannels)) {
            /** @var string[] $error */
            $error = [
                'error' => __('No website/channel mapped. Please check your configurations.'),
            ];

            return $error;
        }

        /** @var mixed[] $filters */
        $filters = [];

        /** @var string $channel */
        foreach ($mappedChannels as $channel) {
            /** @var string[] $filter */
            $filter = [
                'scope'  => $channel,
            ];

            /** @var string[] $locales */
            $locales = $this->storeHelper->getChannelStoreLangs($channel);
            if (!empty($locales)) {
                /** @var string $locales */
                $ingestionengineLocales = $this->localesHelper->getIngestionEngineLocales();
                if (!empty($ingestionengineLocales)) {
                    $locales = array_intersect($locales, $ingestionengineLocales);
                }

                /** @var string $locales */
                $locales           = implode(',', $locales);
                $filter['locales'] = $locales;
            }

            $filters[] = $filter;
        }

        return $filters;
    }

    /**
     * Retrieve advanced filters config
     *
     * @return mixed[]
     */
    protected function getAdvancedFilters()
    {
        /** @var mixed[] $filters */
        $filters = $this->configHelper->getAdvancedFilters();

        return $filters;
    }

    /**
     * Add completeness filter for IngestionEngine API
     *
     * @return void
     */
    protected function addCompletenessFilter()
    {
        /** @var string $filterType */
        $filterType = $this->configHelper->getCompletenessTypeFilter();
        if ($filterType === Completeness::NO_CONDITION) {
            return;
        }

        /** @var string $scope */
        $scope = $this->configHelper->getAdminDefaultChannel();
        /** @var mixed[] $options */
        $options = ['scope' => $scope];

        /** @var string $filterValue */
        $filterValue = $this->configHelper->getCompletenessValueFilter();

        /** @var string[] $localesType */
        $localesType = [
            Completeness::LOWER_OR_EQUALS_THAN_ON_ALL_LOCALES,
            Completeness::LOWER_THAN_ON_ALL_LOCALES,
            Completeness::GREATER_THAN_ON_ALL_LOCALES,
            Completeness::GREATER_OR_EQUALS_THAN_ON_ALL_LOCALES,
        ];
        if (in_array($filterType, $localesType)) {
            /** @var mixed $locales */
            $locales = $this->configHelper->getCompletenessLocalesFilter();
            /** @var string[] $locales */
            $locales            = explode(',', $locales);
            $options['locales'] = $locales;
        }

        $this->searchBuilder->addFilter('completeness', $filterType, $filterValue, $options);

        return;
    }

    /**
     * Add status filter for IngestionEngine API
     *
     * @return void
     */
    protected function addStatusFilter()
    {
        /** @var string $filter */
        $filter = $this->configHelper->getStatusFilter();
        if ($filter === Status::STATUS_NO_CONDITION) {
            return;
        }
        $this->searchBuilder->addFilter('enabled', '=', (bool)$filter);

        return;
    }

    /**
     * Add updated filter for IngestionEngine API
     *
     * @return void
     */
    protected function addUpdatedFilter()
    {
        /** @var string $mode */
        $mode = $this->configHelper->getUpdatedMode();

        if ($mode == Update::BETWEEN) {
            $dateAfter  = $this->configHelper->getUpdatedBetweenAfterFilter() . ' 00:00:00';
            $dateBefore = $this->configHelper->getUpdatedBetweenBeforeFilter() . ' 23:59:59';
            if (empty($dateAfter) || empty($dateBefore)) {
                return;
            }
            $dates = [$dateAfter, $dateBefore];
            $this->searchBuilder->addFilter('updated', $mode, $dates);
        }
        if ($mode == Update::SINCE_LAST_N_DAYS) {
            /** @var string $filter */
            $filter = $this->configHelper->getUpdatedSinceFilter();
            if (!is_numeric($filter)) {
                return;
            }
            $this->searchBuilder->addFilter('updated', $mode, (int)$filter);
        }
        if ($mode == Update::LOWER_THAN) {
            /** @var string $date */
            $date = $this->configHelper->getUpdatedLowerFilter();
            if (empty($date)) {
                return;
            }
            $date = $date . ' 23:59:59';
        }
        if ($mode == Update::GREATER_THAN) {
            $date = $this->configHelper->getUpdatedGreaterFilter();
            if (empty($date)) {
                return;
            }
            $date = $date . ' 00:00:00';
        }
        if (!empty($date)) {
            $this->searchBuilder->addFilter('updated', $mode, $date);
        }
        return;
    }

    /**
     * Add families filter for IngestionEngine API
     *
     * @return void
     */
    protected function addFamiliesFilter()
    {
        /** @var mixed $filter */
        $filter = $this->configHelper->getFamiliesFilter();
        if (!$filter) {
            return;
        }

        /** @var string[] $filter */
        $filter = explode(',', $filter);

        $this->searchBuilder->addFilter('family', 'NOT IN', $filter);

        return;
    }
}

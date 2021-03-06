<?php

namespace IngestionEngine\Connector\Block\Adminhtml;

use \Magento\Backend\Block\Template;
use Magento\Backend\Model\Url;
use \Magento\Backend\Model\UrlFactory;
use \Magento\Backend\Block\Template\Context;
use IngestionEngine\Connector\Api\ImportRepositoryInterface;

/**
 * Class Import
 *
 * @category  Class
 * @package   IngestionEngine\Connector\Block\Adminhtml
 * @author    IngestionEngine <sales@silksoftware.com>
 * @copyright 2020 IngestionEngine
 * @license   https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://www.silksoftware.com/
 */
class Import extends Template
{

    /**
     * This variable contains an Url
     *
     * @var Url $urlModel
     */
    protected $urlModel;
    /**
     * This variable contains an ImportRepositoryInterface
     *
     * @var ImportRepositoryInterface $importRepository
     */
    protected $importRepository;

    /**
     * Import constructor.
     *
     * @param Context $context
     * @param UrlFactory $backendUrlFactory
     * @param ImportRepositoryInterface $importRepository
     * @param array $data
     */
    public function __construct(
        Context $context,
        UrlFactory $backendUrlFactory,
        ImportRepositoryInterface $importRepository,
        $data = []
    ) {
        parent::__construct($context, $data);

        $this->urlModel         = $backendUrlFactory->create();
        $this->importRepository = $importRepository;
    }

    /**
     * Retrieve import collection
     *
     * @return Iterable
     * @throws \Exception
     */
    public function getCollection()
    {
        return $this->importRepository->getList();
    }

    /**
     * Check import is allowed
     *
     * @param string $code
     *
     * @return bool
     */
    public function isAllowed($code)
    {
        return $this->_authorization->isAllowed('IngestionEngine_Connector::import_'.$code);
    }

    /**
     * {@inheritdoc}
     */
    public function _toHtml()
    {
        /** @var string $runUrl */
        $runUrl = $this->_getRunUrl();
        /** @var string $runProductUrl */
        $runProductUrl = $this->_getRunProductUrl();

        $this->assign(
            [
                'runProductUrl' => $this->_escaper->escapeHtml($runProductUrl),
                'runUrl' => $this->_escaper->escapeHtml($runUrl),
            ]
        );

        return parent::_toHtml();
    }

    /**
     * Retrieve run URL
     *
     * @return string
     */
    public function _getRunUrl()
    {
        return $this->urlModel->getUrl('ingestionengine_connector/import/run');
    }

    /**
     * Retrieve run URL
     *
     * @return string
     */
    public function _getRunProductUrl()
    {
        return $this->urlModel->getUrl('ingestionengine_connector/import/runProduct');
    }
}

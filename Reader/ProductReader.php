<?php

namespace Pim\Bundle\MagentoConnectorBundle\Reader;

use Pim\Bundle\BaseConnectorBundle\Reader\Doctrine\ObsoleteProductReader as PimProductReader;
use Pim\Bundle\MagentoConnectorBundle\Manager\ProductExportManager;
use Akeneo\Bundle\BatchBundle\Entity\StepExecution;
use Pim\Bundle\TransformBundle\Converter\MetricConverter;
use Pim\Bundle\CatalogBundle\Manager\ChannelManager;
use Pim\Bundle\CatalogBundle\Manager\CompletenessManager;
use Pim\Bundle\CatalogBundle\Repository\ProductRepositoryInterface;
use Pim\Bundle\CatalogBundle\Model\AbstractProduct;

/**
 * Reads products one by one without delta
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductReader extends PimProductReader
{
    protected $productExportManager;

    /**
     * Constructor
     *
     * @param ProductRepositoryInterface $repository
     * @param ChannelManager             $channelManager
     * @param CompletenessManager        $completenessManager
     * @param MetricConverter            $metricConverter
     * @param ProductExportManager       $productExportManager
     */
    public function __construct(
        ProductRepositoryInterface $repository,
        ChannelManager $channelManager,
        CompletenessManager $completenessManager,
        MetricConverter $metricConverter,
        ProductExportManager $productExportManager
    ) {
        parent::__construct($repository, $channelManager, $completenessManager, $metricConverter);

        $this->productExportManager = $productExportManager;
    }

    /**
     * {@inheritdoc}
     */
    public function read()
    {
        $product = $this->filterProduct(parent::read());

        return $product;
    }

    /**
     * Filter products and return only products that got updated since the last export
     * @param AbstractProduct $readProduct
     *
     * @return AbstractProduct|null
     */
    protected function filterProduct(AbstractProduct $readProduct = null)
    {
        if (null !== $readProduct) {
            $filteredProduct = $this->productExportManager->filterProduct($readProduct, $this->jobInstance);

            if ($filteredProduct === null) {
                return $this->filterProduct(parent::read());
            } else {
                return $filteredProduct;
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function setStepExecution(StepExecution $stepExecution)
    {
        parent::setStepExecution($stepExecution);

        $this->jobInstance = $this->stepExecution->getJobExecution()->getJobInstance();
    }
}

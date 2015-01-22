<?php

namespace Pim\Bundle\MagentoConnectorBundle\Entity;

use Akeneo\Bundle\BatchBundle\Entity\JobInstance;
use Pim\Bundle\CatalogBundle\Model\ProductInterface;

/**
 * Delta configurable export entity
 *
 * @author    Romain Monceau <romain@akeneo.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class DeltaConfigurableExport
{
    /** @var int */
    protected $id;

    /** @var \DateTime */
    protected $lastExport;

    /** @var ProductInterface */
    protected $product;

    /** @var JobInstance */
    protected $jobInstance;

    /**
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param \DateTime $lastExport
     *
     * @return DeltaConfigurableExport
     */
    public function setLastExport($lastExport)
    {
        $this->lastExport = $lastExport;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getLastExport()
    {
        return $this->lastExport;
    }

    /**
     * @param ProductInterface $product
     *
     * @return DeltaConfigurableExport
     */
    public function setProduct(ProductInterface $product = null)
    {
        $this->product = $product;

        return $this;
    }

    /**
     * @return ProductInterface
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * @param JobInstance $jobInstance
     *
     * @return DeltaConfigurableExport
     */
    public function setJobInstance(JobInstance $jobInstance = null)
    {
        $this->jobInstance = $jobInstance;

        return $this;
    }

    /**
     * @return JobInstance
     */
    public function getJobInstance()
    {
        return $this->jobInstance;
    }
}

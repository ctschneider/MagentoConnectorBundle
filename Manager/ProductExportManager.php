<?php

namespace Pim\Bundle\MagentoConnectorBundle\Manager;

use PDO;
use Doctrine\ORM\EntityManager;
use Pim\Bundle\CatalogBundle\Model\AbstractProduct;
use Akeneo\Bundle\BatchBundle\Entity\JobInstance;
use Pim\Bundle\CatalogBundle\Model\ProductInterface;

/**
 * Product export manager to update and create product export entities
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductExportManager
{
    /** @staticvar string */
    const DELTA_PRODUCT_TABLE = 'pim_magento_delta_product_export';

    /** @staticvar string */
    const DELTA_ASSOCIATION_TABLE = 'pim_magento_delta_product_association_export';

    /**
     * @var boolean
     */
    protected $productValueDelta;

    /** @var EntityManager */
    protected $entityManager;

    /** @var string */
    protected $productExportClass;

    /**@var string */
    protected $assoExportClass;

    /**
     * @var EntityRepository
     */
    protected $productExportRepository;

    /** @var \Doctrine\ORM\EntityRepository */
    protected $assoExportRepository;

    /**
     * @var EntityRepository
     */
    protected $productRepository;

    /**
     * Constructor
     *
     * @param EntityManager $entityManager      Entity manager for other entitites
     * @param string        $productExportClass ProductExport class
     * @param string        $assoExportClass    ProductExport class
     * @param string        $productClass       Product class
     * @param boolean       $productValueDelta  Should we do a delta on product values
     */
    public function __construct(
        EntityManager $entityManager,
        $productExportClass,
        $assoExportClass,
        $productClass,
        $productValueDelta = false
    ) {
        $this->entityManager           = $entityManager;
        $this->productExportClass      = $productExportClass;
        $this->assoExportClass         = $assoExportClass;
        $this->productExportRepository = $this->entityManager->getRepository($this->productExportClass);
        $this->assoExportRepository    = $this->entityManager->getRepository($this->assoExportClass);
        $this->productRepository       = $this->entityManager->getRepository($productClass);
        $this->productValueDelta       = $productValueDelta;
    }

    /**
     * Update product export date for the given product
     *
     * @param string      $identifier
     * @param JobInstance $jobInstance
     */
    public function updateProductExport($identifier, JobInstance $jobInstance)
    {
        $product = $this->productRepository->findByReference((string) $identifier);

        if (null != $product) {
            $productExport = $this->productExportRepository->findOneBy(
                array(
                    'product'     => $product,
                    'jobInstance' => $jobInstance,
                )
            );

            $this->updateExport(
                $productExport,
                static::DELTA_PRODUCT_TABLE,
                $product,
                $jobInstance
            );
        }
    }

    /**
     * Update product association export date for the given product
     *
     * @param string      $identifier
     * @param JobInstance $jobInstance
     */
    public function updateProductAssociationExport($identifier, JobInstance $jobInstance)
    {
        $product = $this->productRepository->findByReference((string) $identifier);

        if (null != $product) {
            $productAssoExport = $this->assoExportRepository->findOneBy(
                array(
                    'product'     => $product,
                    'jobInstance' => $jobInstance,
                )
            );

            $this->updateExport(
                $productAssoExport,
                static::DELTA_ASSOCIATION_TABLE,
                $product,
                $jobInstance
            );
        }
    }

    /**
     * Update export date for the given product
     *
     * @param string           $export
     * @param string           $table
     * @param ProductInterface $product
     * @param JobInstance      $jobInstance
     */
    protected function updateExport($export, $table, ProductInterface $product, JobInstance $jobInstance)
    {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));

        $conn = $this->entityManager->getConnection();

        $jobInstance->getId();
        $product->getId();

        if (null === $export) {
            $sql = "
                INSERT INTO $table
                (product_id, job_instance_id, last_export)
                VALUES (:product_id, :job_instance_id, :last_export)
            ";
        } else {
            $sql = "
                UPDATE $table
                SET last_export = :last_export
                WHERE product_id = :product_id AND job_instance_id = :job_instance_id
            ";
        }

        $query = $conn->prepare($sql);
        $lastExport = $now->format('Y-m-d H:i:s');
        $productId = $product->getId();
        $jobInstanceId = $jobInstance->getId();

        $query->bindParam(':last_export', $lastExport, PDO::PARAM_STR);
        $query->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $query->bindParam(':job_instance_id', $jobInstanceId, PDO::PARAM_INT);
        $query->execute();
    }

    /**
     * Filter products to export
     * @param array       $products
     * @param JobInstance $jobInstance
     *
     * @return AbstractProduct
     */
    public function filterProducts($products, JobInstance $jobInstance)
    {
        $productsToExport = array();

        foreach ($products as $product) {
            $product = $this->filterProduct($product, $jobInstance);

            if (null !== $product) {
                $productsToExport[] = $product;
            }
        }

        return $productsToExport;
    }

    /**
     * Filter a product (return null if the product got exported after his last edit)
     * @param AbstractProduct $product
     * @param JobInstance     $jobInstance
     *
     * @return AbstractProduct|null
     */
    public function filterProduct(AbstractProduct $product, JobInstance $jobInstance)
    {
        $productExport = $this->productExportRepository->findProductExportAfterEdit(
            $product,
            $jobInstance,
            $product->getUpdated()
        );

        if (0 === count($productExport)) {
            if ($this->productValueDelta) {
                $product = $this->filterProductValues($product);
            }
        } else {
            $product = null;
        }

        return $product;
    }

    /**
     * Filter on product values
     *
     * @param AbstractProduct $product
     *
     * @return AbstractProduct
     */
    public function filterProductValues(AbstractProduct $product)
    {
        $this->entityManager->detach($product);
        $productValues  = $product->getValues();
        $identifierType = $product->getIdentifier()->getAttribute()->getAttributeType();

        foreach ($productValues as $productValue) {
            if ($identifierType != $productValue->getAttribute()->getAttributeType() && (
                    null == $productValue->getUpdated() || (
                        null != $productValue->getUpdated() &&
                        $product->getUpdated()->getTimestamp() - $productValue->getUpdated()->getTimestamp() > 60
                    )
                )
            ) {
                $product->removeValue($productValue);
            }
        }

        return $product;
    }
}

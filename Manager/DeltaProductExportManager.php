<?php

namespace Pim\Bundle\MagentoConnectorBundle\Manager;

use Doctrine\ORM\EntityRepository;
use PDO;
use Doctrine\ORM\EntityManager;
use Akeneo\Bundle\BatchBundle\Entity\JobInstance;
use Pim\Bundle\CatalogBundle\Model\ProductInterface;
use Pim\Bundle\CatalogBundle\Repository\ProductRepositoryInterface;

/**
 * Delta product export manager to update and create product export entities
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class DeltaProductExportManager
{
    /** @staticvar string */
    const DELTA_PRODUCT_TABLE = 'pim_magento_delta_product_export';

    /** @staticvar string */
    const DELTA_ASSOCIATION_TABLE = 'pim_magento_delta_product_association_export';

    /** @var boolean */
    protected $productValueDelta;

    /** @var EntityManager */
    protected $entityManager;

    /** @var EntityRepository */
    protected $productExportRepository;

    /** @var EntityRepository */
    protected $assoExportRepository;

    /** @var ProductRepositoryInterface */
    protected $productRepository;

    /**
     * @param EntityManager                $entityManager           Entity manager for other entities
     * @param EntityRepository             $productExportRepository Product export repository
     * @param EntityRepository             $assoExportRepository    Association export repository
     * @param ProductRepositoryInterface   $productRepository       Product repository
     * @param boolean                      $productValueDelta       Should we do a delta on product values
     */
    public function __construct(
        EntityManager $entityManager,
        EntityRepository $productExportRepository,
        EntityRepository $assoExportRepository,
        ProductRepositoryInterface $productRepository,
        $productValueDelta = false
    ) {
        $this->entityManager           = $entityManager;
        $this->productExportRepository = $productExportRepository;
        $this->assoExportRepository    = $assoExportRepository;
        $this->productRepository       = $productRepository;
        $this->productValueDelta       = $productValueDelta;
    }

    /**
     * Update product export date for the given product
     *
     * @param JobInstance $jobInstance
     * @param string      $identifier
     */
    public function updateProductExport(JobInstance $jobInstance, $identifier)
    {
        $product = $this->productRepository->findByReference((string) $identifier);

        if ($product) {
            $productExport = $this->productExportRepository->findOneBy(
                [
                    'product'     => $product,
                    'jobInstance' => $jobInstance,
                ]
            );

            $this->updateExport(
                $product,
                $jobInstance,
                $productExport,
                static::DELTA_PRODUCT_TABLE
            );
        }
    }

    /**
     * Update product association export date for the given product
     *
     * @param JobInstance $jobInstance
     * @param string      $identifier
     */
    public function updateProductAssociationExport(JobInstance $jobInstance, $identifier)
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
                $product,
                $jobInstance,
                $productAssoExport,
                static::DELTA_ASSOCIATION_TABLE
            );
        }
    }

    /**
     * Update export date for the given product
     *
     * @param ProductInterface $product
     * @param JobInstance      $jobInstance
     * @param mixed            $export
     * @param string           $table
     */
    protected function updateExport(
        ProductInterface $product,
        JobInstance $jobInstance,
        $export,
        $table
    ) {
        $conn = $this->entityManager->getConnection();

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

        $now           = new \DateTime('now', new \DateTimeZone('UTC'));
        $formattedNow  = $now->format('Y-m-d H:i:s');
        $productId     = $product->getId();
        $jobInstanceId = $jobInstance->getId();
        $query         = $conn->prepare($sql);

        $query->bindParam(':last_export', $formattedNow, PDO::PARAM_STR);
        $query->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $query->bindParam(':job_instance_id', $jobInstanceId, PDO::PARAM_INT);
        $query->execute();
    }
}

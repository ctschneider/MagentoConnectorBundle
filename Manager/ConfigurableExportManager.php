<?php

namespace Pim\Bundle\MagentoConnectorBundle\Manager;

use Akeneo\Bundle\BatchBundle\Entity\JobInstance;
use Doctrine\ORM\EntityManager;
use PDO;

/**
 * Manage DeltaConfigurableExport entities
 *
 * @author    Romain Monceau <romain@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ConfigurableExportManager
{
    /** @var \Doctrine\ORM\EntityManager */
    protected $em;

    /** @var string */
    protected $deltaConfigClass;

    /** @var string */
    protected $groupClass;

    /**
     * @param EntityManager $em
     * @param string        $deltaConfigClass
     * @param string        $groupClass
     */
    public function __construct(
        EntityManager $em,
        $deltaConfigClass,
        $groupClass
    ) {
        $this->em = $em;
        $this->deltaConfigClass = $deltaConfigClass;
        $this->groupClass = $groupClass;
    }

    /**
     * Update configurable delta export
     *
     * @param string      $identifier
     * @param JobInstance $jobInstance
     */
    public function setLastExportDate($identifier, JobInstance $jobInstance)
    {
        $variantGroup = $this->getGroupRepository()->findOneBy(['code' => $identifier]);

        if (null !== $variantGroup) {
            foreach ($variantGroup->getProducts() as $product) {
                $deltaConfig = $this->getDeltaConfigRepository()->findOneBy([
                    'product'     => $product,
                    'jobInstance' => $jobInstance
                ]);

                if (null === $deltaConfig) {
                    $sql = <<<SQL
                      INSERT INTO pim_magento_delta_configurable_export (product_id, job_instance_id, last_export)
                      VALUES (:product_id, :job_instance_id, :last_export)
SQL;
                } else {
                    $sql = <<<SQL
                      UPDATE pim_magento_delta_configurable_export SET last_export = :last_export
                      WHERE product_id = :product_id AND job_instance_id = :job_instance_id
SQL;
                }

                $connection = $this->em->getConnection();
                $query = $connection->prepare($sql);

                $now = new \DateTime('now', new \DateTimeZone('UTC'));
                $lastExport = $now->format('Y-m-d H:i:s');
                $productId = $product->getId();
                $jobInstanceId = $jobInstance->getId();

                $query->bindParam(':last_export', $lastExport, PDO::PARAM_STR);
                $query->bindParam(':product_id', $productId, PDO::PARAM_INT);
                $query->bindParam(':job_instance_id', $jobInstanceId, PDO::PARAM_INT);
                $query->execute();
            }
        }
    }

    /**
     * @return \Doctrine\ORM\EntityRepository
     */
    protected function getDeltaConfigRepository()
    {
        return $this->em->getRepository($this->deltaConfigClass);
    }

    /**
     * @return \Doctrine\ORM\EntityRepository
     */
    protected function getGroupRepository()
    {
        return $this->em->getRepository($this->groupClass);
    }
}

<?php

namespace Pim\Bundle\MagentoConnectorBundle\Reader;

use Doctrine\ORM\EntityManager;
use Pim\Bundle\BaseConnectorBundle\Reader\Doctrine\ORMProductReader;
use Pim\Bundle\CatalogBundle\Manager\ChannelManager;
use Pim\Bundle\CatalogBundle\Manager\CompletenessManager;
use Pim\Bundle\CatalogBundle\Repository\ProductRepositoryInterface;
use Pim\Bundle\TransformBundle\Converter\MetricConverter;

/**
 * Delta reader for configurables
 *
 * @author    Romain Monceau <romain@akeneo.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class DeltaConfigurableReader extends ORMProductReader
{
    /** @var string */
    protected $productClass;

    /**
     * @param ProductRepositoryInterface $repository
     * @param ChannelManager             $channelManager
     * @param CompletenessManager        $completenessManager
     * @param MetricConverter            $metricConverter
     * @param EntityManager              $entityManager
     * @param boolean                    $missingCompleteness
     * @param string                     $productClass
     */
    public function __construct(
        ProductRepositoryInterface $repository,
        ChannelManager $channelManager,
        CompletenessManager $completenessManager,
        MetricConverter $metricConverter,
        EntityManager $entityManager,
        $missingCompleteness = true,
        $productClass = null
    ) {
        parent::__construct(
            $repository,
            $channelManager,
            $completenessManager,
            $metricConverter,
            $entityManager,
            $missingCompleteness
        );

        $this->productClass = $productClass;
    }

    /**
     * {@inheritdoc}
     */
    protected function getIds()
    {
        if (!is_object($this->channel)) {
            $this->channel = $this->channelManager->getChannelByCode($this->channel);
        }

        if ($this->missingCompleteness) {
            $this->completenessManager->generateMissingForChannel($this->channel);
        }

        $treeId = $this->channel->getCategory()->getId();
        $sql = $this->getSQLQuery($this->channel->getId(), $treeId, $this->getJobInstance()->getId());

        $connection = $this->entityManager->getConnection();
        $results = $connection->fetchAll($sql);

        $productIds = [];
        foreach ($results as $result) {
            $productIds[] = $result['id'];
        }

        return $productIds;
    }

    /**
     * @param int $channelId
     * @param int $treeId
     * @param int $jobInstanceId
     *
     * @return string
     */
    protected function getSQLQuery($channelId, $treeId, $jobInstanceId)
    {
        return <<<SQL
            SELECT p.id FROM pim_catalog_product p
            INNER JOIN pim_catalog_completeness comp
                ON comp.product_id = p.id AND comp.channel_id = $channelId AND comp.ratio = 100
            INNER JOIN pim_catalog_category_product cp ON p.id = cp.product_id
            INNER JOIN pim_catalog_category c ON c.id = cp.category_id AND c.discr IN ('category')
              AND c.root = $treeId

            INNER JOIN pim_catalog_group_product gp ON gp.product_id = p.id
            INNER JOIN pim_catalog_group g ON g.id = gp.group_id
            INNER JOIN pim_catalog_group_type gt ON gt.id = g.type_id AND gt.is_variant = 1

            INNER JOIN pim_versioning_version v ON v.resource_id = p.id
                AND v.resource_name = "Pim\\\\Bundle\\\\CatalogBundle\\\\Model\\\\Product"

            LEFT JOIN pim_magento_delta_configurable_export de ON de.product_id = p.id
            LEFT JOIN akeneo_batch_job_instance j ON j.id = de.job_instance_id AND j.id = $jobInstanceId

            WHERE v.logged_at > de.last_export OR de.last_export IS NULL
            AND p.is_enabled = 1

            GROUP BY p.id
SQL;
    }

    /**
     * @return \Akeneo\Bundle\BatchBundle\Entity\JobInstance
     */
    protected function getJobInstance()
    {
        return $this->stepExecution->getJobExecution()->getJobInstance();
    }
}

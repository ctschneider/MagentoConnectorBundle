<?php

namespace Pim\Bundle\MagentoConnectorBundle\Reader\ORM;

use Pim\Bundle\BaseConnectorBundle\Reader\Doctrine\ORMProductReader;

/**
 * Delta Published product reader
 *
 * @author    Romain Monceau <romain@akeneo.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class DeltaProductReader extends ORMProductReader
{
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
            SELECT cp.id FROM pim_catalog_product cp

            INNER JOIN pim_catalog_completeness comp
                ON comp.product_id = cp.id AND comp.channel_id = $channelId AND comp.ratio = 100

            INNER JOIN pim_catalog_category_product ccp ON ccp.product_id = cp.id
            INNER JOIN pim_catalog_category c
                ON c.id = ccp.category_id AND c.discr IN ('category') AND c.root = $treeId

            LEFT JOIN pim_magento_delta_product_export dpe ON dpe.product_id = cp.id
            LEFT JOIN akeneo_batch_job_instance j
                ON j.id = dpe.job_instance_id AND j.id = $jobInstanceId

            WHERE (cp.updated > dpe.last_export OR dpe.last_export IS NULL) AND cp.is_enabled = 1

            GROUP BY cp.id;
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

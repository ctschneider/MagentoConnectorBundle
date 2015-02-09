<?php

namespace Pim\Bundle\MagentoConnectorBundle\Reader;

/**
 * Delta product association reader
 *
 * @author    Damien Carcel (https://github.com/damien-carcel)
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class DeltaProductAssociationReader extends DeltaProductReader
{
    /**
     * Left join with "pim_magento_delta_product_association_export"
     * instead of "pim_magento_delta_product_export"
     *
     * @inheritdoc
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

            LEFT JOIN pim_magento_delta_product_association_export dpae ON dpae.product_id = cp.id
            LEFT JOIN akeneo_batch_job_instance j
                ON j.id = dpae.job_instance_id AND j.id = $jobInstanceId

            WHERE (cp.updated > dpae.last_export OR dpae.last_export IS NULL) AND cp.is_enabled = 1

            GROUP BY cp.id;
SQL;
    }
}

<?php

namespace spec\Pim\Bundle\MagentoConnectorBundle\Manager;

use Akeneo\Bundle\BatchBundle\Entity\JobInstance;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\ORM\EntityManager;
use PhpSpec\ObjectBehavior;
use Pim\Bundle\CatalogBundle\Entity\Group;
use Pim\Bundle\CatalogBundle\Entity\Repository\GroupRepository;
use Pim\Bundle\CatalogBundle\Model\Product;
use Prophecy\Argument;

/**
 * Class ConfigurableExportManagerSpec
 *
 * @author    Damien Carcel (https://github.com/damien-carcel)
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ConfigurableExportManagerSpec extends ObjectBehavior
{
    function let(
        Connection $connection,
        EntityManager $entityManager,
        GroupRepository $groupRepository,
        Statement $query
    ) {
        $this->beConstructedWith($entityManager, 'my_delta_config_class', 'my_group_class');

        $entityManager->getRepository('my_group_class')->willReturn($groupRepository);

        $entityManager->getConnection()->willReturn($connection);
        $connection->prepare('my_sql_request')->willReturn($query);
    }

    function it_get_data_from_children_and_set_last_export_date(
        $groupRepository,
        Group $variantGroup,
        ArrayCollection $products,
        Product $product,
        $query,
        JobInstance $jobinstance
    ) {
        $groupRepository->findOneBy(['code' => 'sku'])->willReturn($variantGroup);

        $variantGroup->getProducts()->willReturn($products);

        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $query->bindParam(':last_export', $now->format('Y-m-d H:i:s'), \PDO::PARAM_STR)
            ->shouldBeCalled();
        $query->bindParam(':product_id', $product->getId(), \PDO::PARAM_INT)
            ->shouldBeCalled();
        $query->bindParam(':job_instance_id', $jobinstance->getId(), \PDO::PARAM_INT)
            ->shouldBeCalled();
        $query->execute()->shouldBeCalled();

        $this->setLastExportDate('sku', $jobinstance);
    }
}

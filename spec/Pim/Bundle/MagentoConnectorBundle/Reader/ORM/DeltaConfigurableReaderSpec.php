<?php

namespace spec\Pim\Bundle\MagentoConnectorBundle\Reader\ORM;

use Doctrine\ORM\EntityManager;
use PhpSpec\ObjectBehavior;
use Pim\Bundle\CatalogBundle\Manager\ChannelManager;
use Pim\Bundle\CatalogBundle\Manager\CompletenessManager;
use Pim\Bundle\CatalogBundle\Repository\ProductRepositoryInterface;
use Pim\Bundle\TransformBundle\Converter\MetricConverter;
use Prophecy\Argument;

class DeltaConfigurableReaderSpec extends ObjectBehavior
{
    function let(
        ProductRepositoryInterface $repository,
        ChannelManager $channelManager,
        CompletenessManager $completenessManager,
        MetricConverter $metricConverter,
        EntityManager $entityManager
    ) {
        $this->beConstructedWith(
            $repository,
            $channelManager,
            $completenessManager,
            $metricConverter,
            $entityManager,
            true,
            'my_product_class'
        );
    }

    function it_reads_products()
    {
        //
    }
}

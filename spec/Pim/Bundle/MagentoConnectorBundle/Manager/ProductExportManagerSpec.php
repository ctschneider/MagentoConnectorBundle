<?php

namespace spec\Pim\Bundle\MagentoConnectorBundle\Manager;

use Doctrine\ORM\EntityManager;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ProductExportManagerSpec extends ObjectBehavior
{
    function let(EntityManager $entityManager) {
        $this->beConstructedWith(
            $entityManager,
            'my_product_export_class',
            'my_association_export_class',
            'my_product_class',
            false
        );
    }
}

<?php

namespace spec\Pim\Bundle\MagentoConnectorBundle\Reader;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ProductReaderSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType('Pim\Bundle\MagentoConnectorBundle\Reader\ORM\ProductReader');
    }
}

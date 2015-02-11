<?php

namespace spec\Pim\Bundle\MagentoConnectorBundle\Reader\ORM;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class DeltaProductReaderSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType('Pim\Bundle\MagentoConnectorBundle\Reader\ORM\DeltaProductReader');
    }
}

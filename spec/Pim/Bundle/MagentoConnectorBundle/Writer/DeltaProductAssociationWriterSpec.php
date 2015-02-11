<?php

namespace spec\Pim\Bundle\MagentoConnectorBundle\Writer;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class DeltaProductAssociationWriterSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType('Pim\Bundle\MagentoConnectorBundle\Writer\DeltaProductAssociationWriter');
    }
}

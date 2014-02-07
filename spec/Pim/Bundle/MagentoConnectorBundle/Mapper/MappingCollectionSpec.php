<?php

namespace spec\Pim\Bundle\MagentoConnectorBundle\Mapper;

use Pim\Bundle\MagentoConnectorBundle\Mapper\MappingCollection;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class MappingCollectionSpec extends ObjectBehavior
{
    function it_shoulds_add_value_to_the_collection()
    {
        $this->add(array('source' => 'foo', 'target' => 'bar', 'deletable' => true));

        $this->toArray()->shouldReturn(array(
            'foo' => array(
                'source'    => 'foo',
                'target'    => 'bar',
                'deletable' => true
            )
        ));
    }

    function it_shoulds_merge_value_that_are_allready_in_collection()
    {
        $this->add(array('source' => 'foo', 'target' => 'bar', 'deletable' => true));
        $this->add(array('source' => 'foo', 'target' => 'foo', 'deletable' => true));

        $this->toArray()->shouldReturn(array(
            'foo' => array(
                'source'    => 'foo',
                'target'    => 'foo',
                'deletable' => true
            )
        ));
    }

    function it_should_retain_the_old_target_value_if_the_new_one_is_empty()
    {
        $this->add(array('source' => 'foo', 'target' => 'bar', 'deletable' => true));
        $this->add(array('source' => 'foo', 'target' => '', 'deletable' => true));

        $this->toArray()->shouldReturn(array(
            'foo' => array(
                'source'    => 'foo',
                'target'    => 'bar',
                'deletable' => true
            )
        ));
    }

    function it_shoulds_retain_the_deletable_value_if_its_false()
    {
        $this->add(array('source' => 'foo', 'target' => 'bar', 'deletable' => false));
        $this->add(array('source' => 'foo', 'target' => 'bar', 'deletable' => true));

        $this->toArray()->shouldReturn(array(
            'foo' => array(
                'source'    => 'foo',
                'target'    => 'bar',
                'deletable' => false
            )
        ));
    }

    function it_shoulds_merge_two_collection()
    {
        $collectionToMerge = new MappingCollection(array('bar' => array('source' => 'bar', 'target' => 'foo', 'deletable' => true)));

        $this->add(array('source' => 'foo', 'target' => 'bar', 'deletable' => true));

        $this->merge($collectionToMerge);

        $this->toArray()->shouldReturn(array(
            'foo' => array('source' => 'foo', 'target' => 'bar', 'deletable' => true),
            'bar' => array('source' => 'bar', 'target' => 'foo', 'deletable' => true)
        ));
    }
}

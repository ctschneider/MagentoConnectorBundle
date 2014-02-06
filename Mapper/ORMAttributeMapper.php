<?php

namespace Pim\Bundle\MagentoConnectorBundle\Mapper;

use Pim\Bundle\MagentoConnectorBundle\Manager\SimpleMappingManager;
use Pim\Bundle\MagentoConnectorBundle\Manager\AttributeManager;

/**
 * Magento attribute mapper
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ORMAttributeMapper extends AbstractAttributeMapper
{
    /**
     * @var SimpleMappingManager
     */
    protected $simpleMappingManager;

    /**
     * @var AttributeManager
     */
    protected $attributeManager;

    /**
     * @param SimpleMappingManager $simpleMappingManager
     * @param AttributeManager     $attributeManager
     */
    public function __construct(
        SimpleMappingManager $simpleMappingManager,
        AttributeManager $attributeManager
    ) {
        $this->simpleMappingManager = $simpleMappingManager;
        $this->attributeManager = $attributeManager;
    }

    /**
     * Get mapping
     * @return array
     */
    public function getMapping()
    {
        $simpleMappingItems = $this->simpleMappingManager->getMapping($this->getIdentifier());

        $mapping = new MappingCollection();
        foreach ($simpleMappingItems as $simpleMappingItem) {
            $mapping.add(array(
                'source'    => $simpleMappingItem->getSource(),
                'target'    => $simpleMappingItem->getTarget(),
                'deletable' => true
            ));
        }

        return $mapping;
    }

    /**
     * Set mapping
     * @param array $mapping
     */
    public function setMapping(array $mapping)
    {
        $this->simpleMappingManager->setMapping($mapping, $this->getIdentifier());
    }

    /**
     * Get all targets
     * @return array
     */
    public function getAllTargets()
    {
        return array();
    }

    /**
     * Get all sources
     * @return array
     */
    public function getAllSources()
    {
        $attributes = $this->attributeManager->getAttributes();

        $sources = array();
        foreach ($attributes as $attribute) {
            $sources[] = $attribute->getCode();
        }

        return $sources;
    }

    /**
     * Get mapper priority
     * @return integer
     */
    public function getPriority()
    {
        return 10;
    }
}

<?php

namespace spec\Pim\Bundle\MagentoConnectorBundle\Processor;

use Akeneo\Bundle\BatchBundle\Entity\StepExecution;
use Akeneo\Bundle\BatchBundle\Event\EventInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Pim\Bundle\CatalogBundle\Entity\Category;
use PhpSpec\ObjectBehavior;
use Pim\Bundle\CatalogBundle\Entity\Channel;
use Pim\Bundle\CatalogBundle\Entity\Family;
use Pim\Bundle\CatalogBundle\Entity\Group;
use Pim\Bundle\CatalogBundle\Manager\ChannelManager;
use Pim\Bundle\CatalogBundle\Model\Product;
use Pim\Bundle\MagentoConnectorBundle\Entity\Repository\GroupRepository;
use Pim\Bundle\MagentoConnectorBundle\Guesser\NormalizerGuesser;
use Pim\Bundle\MagentoConnectorBundle\Guesser\WebserviceGuesser;
use Pim\Bundle\MagentoConnectorBundle\Manager\AttributeManager;
use Pim\Bundle\MagentoConnectorBundle\Manager\CurrencyManager;
use Pim\Bundle\MagentoConnectorBundle\Manager\GroupManager;
use Pim\Bundle\MagentoConnectorBundle\Manager\LocaleManager;
use Pim\Bundle\MagentoConnectorBundle\Mapper\MappingCollection;
use Pim\Bundle\MagentoConnectorBundle\Merger\MagentoMappingMerger;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\ConfigurableNormalizer;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\ProductNormalizer;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientParameters;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientParametersRegistry;
use Pim\Bundle\MagentoConnectorBundle\Webservice\Webservice;
use Prophecy\Argument;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @author    Willy Mesnage <willy.mesnage@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ConfigurableProcessorSpec extends ObjectBehavior
{
    function let(
        WebserviceGuesser $webserviceGuesser,
        NormalizerGuesser $normalizerGuesser,
        LocaleManager $localeManager,
        MagentoMappingMerger $storeViewMappingMerger,
        CurrencyManager $currencyManager,
        ChannelManager $channelManager,
        MagentoMappingMerger $categoryMappingMerger,
        MagentoMappingMerger $attributeMappingMerger,
        GroupManager $groupManager,
        AttributeManager $attributeManager,
        Webservice $webservice,
        MappingCollection $mappingCollection,
        ProductNormalizer $productNormalizer,
        GroupRepository $groupRepository,
        ConfigurableNormalizer $configurableNormalizer,
        Group $group,
        MagentoSoapClientParametersRegistry $clientParametersRegistry,
        MagentoSoapClientParameters $clientParameters,
        StepExecution $stepExecution,
        EventDispatcher $eventDispatcher,
        Channel $channel,
        Category $category
    ) {
        $this->beConstructedWith(
            $webserviceGuesser,
            $normalizerGuesser,
            $localeManager,
            $storeViewMappingMerger,
            $currencyManager,
            $channelManager,
            $categoryMappingMerger,
            $attributeMappingMerger,
            $groupManager,
            $clientParametersRegistry,
            $attributeManager
        );

        $this->setStepExecution($stepExecution);
        $this->setEventDispatcher($eventDispatcher);

        $clientParametersRegistry->getInstance(null, null, null, '/api/soap/?wsdl', 'default', null, null)->willReturn(
            $clientParameters
        );
        $webserviceGuesser->getWebservice($clientParameters)->willReturn($webservice);

        $storeViewMappingMerger->getMapping()->willReturn($mappingCollection);

        $normalizerGuesser->getProductNormalizer(
            $clientParameters,
            null,
            4,
            1,
            null
        )->willReturn($productNormalizer);

        $webservice->getStoreViewsList()->willReturn(
            [
                [
                    'store_id' => '1',
                    'code' => 'default',
                    'website_id' => '1',
                    'group_id' => '1',
                    'name' => 'Default Store View',
                    'sort_order' => '0',
                    'is_active' => '1',
                ],
            ]
        );

        $webservice->getAllAttributes()->willReturn(
            [
                'name' => [
                    'attribute_id' => '71',
                    'code'         => 'name',
                    'type'         => 'text',
                    'required'     => '1',
                    'scope'        => 'store',
                ],
            ]
        );

        $webservice->getAllAttributesOptions()->willReturn([]);

        $categoryMappingMerger->getMapping()->willReturn($mappingCollection);
        $attributeMappingMerger->getMapping()->willReturn($mappingCollection);

        $normalizerGuesser->getConfigurableNormalizer(
            $clientParameters,
            $productNormalizer,
            Argument::type('\Pim\Bundle\MagentoConnectorBundle\Manager\PriceMappingManager'),
            4
        )->willReturn($configurableNormalizer);

        $groupManager->getRepository()->willReturn($groupRepository);

        $group->getId()->willReturn(1);

        $channelManager->getChannelByCode('magento')->willReturn($channel);

        $this->setChannel('magento');

        $channel->getCategory()->willReturn($category);

        $category->getId()->willReturn(1);
    }

    function it_processes_products(
        $groupRepository,
        $webservice,
        $group,
        $configurableNormalizer,
        Product $product,
        ArrayCollection $groupProducts
    ) {
        $groupRepository->getVariantGroupIds()->willReturn([0, 1]);

        $product->getGroups()->willReturn([$group]);

        $group->getCode()->willReturn('abcd');

        $group->getProducts()->willReturn($groupProducts);

        $configurable = ['group' => $group, 'products' => [$product]];

        $webservice->getConfigurablesStatus(['1' => $configurable])->shouldBeCalled()->willReturn(
            [['sku' => 'conf-abcd']]
        );

        $configurableNormalizer->normalize($configurable, 'MagentoArray', Argument::any())->shouldBeCalled();

        $this->process([$product]);
    }

    function it_processes_products_even_if_magento_configurable_doesnt_exist(
        $groupRepository,
        $webservice,
        $group,
        $configurableNormalizer,
        Product $product,
        Family $family,
        ArrayCollection $groupProducts
    ) {
        $groupRepository->getVariantGroupIds()->willReturn([0, 1]);

        $product->getGroups()->willReturn([$group]);
        $product->getFamily()->shouldBeCalled()->willReturn($family);

        $group->getCode()->willReturn('abcd');

        $family->getCode()->willReturn('family_code');

        $group->getProducts()->willReturn($groupProducts);

        $configurable = ['group' => $group, 'products' => [$product]];

        $webservice->getConfigurablesStatus(['1' => $configurable])->shouldBeCalled()->willReturn(
            [['sku' => 'conf-adcb']]
        );
        $webservice->getAttributeSetId('family_code')->shouldBeCalled()->willReturn('attrSet_code');

        $configurableNormalizer->normalize($configurable, 'MagentoArray', Argument::any())->shouldBeCalled();

        $this->process([$product]);
    }

    function it_throws_an_exception_if_a_normalization_error_occured(
        $groupRepository,
        $webservice,
        $group,
        $configurableNormalizer,
        $eventDispatcher,
        Product $product,
        Family $family,
        ArrayCollection $groupProducts
    ) {
        $groupRepository->getVariantGroupIds()->willReturn([0, 1]);

        $product->getGroups()->willReturn([$group]);
        $product->getFamily()->shouldBeCalled()->willReturn($family);

        $group->getCode()->willReturn('abcd');

        $family->getCode()->willReturn('family_code');

        $group->getProducts()->willReturn($groupProducts);

        $configurable = ['group' => $group, 'products' => [$product]];

        $webservice->getConfigurablesStatus(['1' => $configurable])->shouldBeCalled()->willReturn(
            [['sku' => 'conf-adcb']]
        );
        $webservice->getAttributeSetId('family_code')->shouldBeCalled()->willReturn('attrSet_code');

        $configurableNormalizer
            ->normalize($configurable, 'MagentoArray', Argument::any())
            ->willThrow(
                'Pim\Bundle\MagentoConnectorBundle\Normalizer\Exception\NormalizeException'
            );

        $eventDispatcher->dispatch(
            EventInterface::INVALID_ITEM,
            Argument::type('Akeneo\Bundle\BatchBundle\Event\InvalidItemEvent')
        )->shouldBeCalled();

        $this->process([$product]);
    }

    function it_throws_an_exception_if_a_soap_call_error_occured_during_normalization(
        $groupRepository,
        $webservice,
        $group,
        $configurableNormalizer,
        Product $product,
        Family $family,
        ArrayCollection $groupProducts
    ) {
        $groupRepository->getVariantGroupIds()->willReturn([0, 1]);

        $product->getGroups()->willReturn([$group]);
        $product->getFamily()->shouldBeCalled()->willReturn($family);

        $group->getCode()->willReturn('abcd');

        $family->getCode()->willReturn('family_code');

        $group->getProducts()->willReturn($groupProducts);

        $configurable = ['group' => $group, 'products' => [$product]];

        $webservice->getConfigurablesStatus(['1' => $configurable])->shouldBeCalled()->willReturn(
            [['sku' => 'conf-adcb']]
        );
        $webservice->getAttributeSetId('family_code')->shouldBeCalled()->willReturn('attrSet_code');

        $configurableNormalizer->normalize($configurable, 'MagentoArray', Argument::any())->willThrow(
            'Pim\Bundle\MagentoConnectorBundle\Webservice\SoapCallException'
        );

        $this->process([$product]);
    }
}

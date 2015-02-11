<?php

namespace spec\Pim\Bundle\MagentoConnectorBundle\Writer;

use Akeneo\Bundle\BatchBundle\Entity\JobExecution;
use Akeneo\Bundle\BatchBundle\Entity\JobInstance;
use Akeneo\Bundle\BatchBundle\Entity\StepExecution;
use Akeneo\Bundle\BatchBundle\Event\EventInterface;
use PhpSpec\ObjectBehavior;
use Pim\Bundle\CatalogBundle\Manager\ChannelManager;
use Pim\Bundle\MagentoConnectorBundle\Guesser\WebserviceGuesser;
use Pim\Bundle\MagentoConnectorBundle\Manager\ConfigurableExportManager;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientParameters;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientParametersRegistry;
use Pim\Bundle\MagentoConnectorBundle\Webservice\Webservice;
use Prophecy\Argument;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Class DeltaConfigurableWriterSpec
 *
 * @author    Damien Carcel (https://github.com/damien-carcel)
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class DeltaConfigurableWriterSpec extends ObjectBehavior
{
    function let(
        ChannelManager $channelManager,
        ConfigurableExportManager $configExportManager,
        EventDispatcher $eventDispatcher,
        JobExecution $jobExecution,
        JobInstance $jobInstance,
        MagentoSoapClientParameters $clientParameters,
        MagentoSoapClientParametersRegistry $clientParamsRegistry,
        StepExecution $stepExecution,
        Webservice $webservice,
        WebserviceGuesser $webserviceGuesser
    ) {
        $this->beConstructedWith(
            $webserviceGuesser,
            $channelManager,
            $clientParamsRegistry,
            $configExportManager
        );
        $this->setStepExecution($stepExecution);
        $this->setEventDispatcher($eventDispatcher);

        $clientParamsRegistry
            ->getInstance(null, null, null, '/api/soap/?wsdl', 'default', null, null)
            ->willReturn($clientParameters);
        $webserviceGuesser->getWebservice($clientParameters)->willReturn($webservice);

        $stepExecution->getJobExecution()->willReturn($jobExecution);
        $jobExecution->getJobInstance()->willReturn($jobInstance);
    }

    function it_updates_a_product(
        $webservice,
        $stepExecution,
        $configExportManager,
        $jobInstance
    ) {
        $products = [
            'batch_1' => [
                'product_1' => [
                    'default' => [
                        'sku',
                    ],
                    'en_US'   => [],
                    'images'  => [],
                ],
            ],
        ];

        $webservice->getImages('sku', 'default')->willReturn([]);
        $webservice->sendProduct(['sku'])->shouldBeCalled();
        $webservice->sendImages(Argument::any())->shouldBeCalled();
        $webservice->updateProductPart(Argument::any())->shouldBeCalled();

        $stepExecution->incrementSummaryInfo('product_sent')->shouldBeCalled();
        $stepExecution->incrementSummaryInfo('product_image_sent')->shouldBeCalledTimes(1);
        $stepExecution->incrementSummaryInfo('product_translation_sent')->shouldBeCalledTimes(1);

        $this->write($products);

        //$configExportManager->setLastExportDate('sku', $jobInstance)->shouldBeCalled();
    }

    function it_creates_a_product(
        $webservice,
        $stepExecution,
        $configExportManager,
        $jobInstance
    ) {
        $products = [
            'batch_1' => [
                'product_1' => [
                    'default' => [
                        'something',
                        'another',
                        'sku',
                        'again',
                        'lastone',
                    ],
                    'en_US'   => [],
                    'images'  => [],
                ],
            ],
        ];

        $webservice->getImages('sku', 'default')->willReturn([]);
        $webservice->sendProduct(['something', 'another', 'sku', 'again', 'lastone'])->shouldBeCalled();
        $webservice->sendImages(Argument::any())->shouldBeCalled();
        $webservice->updateProductPart(Argument::any())->shouldBeCalled();

        $stepExecution->incrementSummaryInfo('product_sent')->shouldBeCalled();
        $stepExecution->incrementSummaryInfo('product_image_sent')->shouldBeCalledTimes(1);
        $stepExecution->incrementSummaryInfo('product_translation_sent')->shouldBeCalledTimes(1);

        $this->write($products);

        //$configExportManager->setLastExportDate('sku', $jobInstance)->shouldBeCalled();
    }

    function it_updates_a_product_and_prunes_old_images(
        $webservice,
        $stepExecution,
        $configExportManager,
        $jobInstance
    ) {
        $products = [
            'batch_1' => [
                'product_1' => [
                    'default' => [
                        'sku',
                    ],
                    'en_US'   => [],
                    'images'  => [],
                ],
            ],
        ];

        $webservice->getImages('sku', 'default')->willReturn([['file' => 'foo'], ['file' => 'bar']]);
        $webservice->deleteImage('sku', 'foo')->shouldBeCalled();
        $webservice->deleteImage('sku', 'bar')->shouldBeCalled();
        $webservice->sendProduct(['sku'])->shouldBeCalled();
        $webservice->updateProductPart(Argument::any())->shouldBeCalled();
        $webservice->sendImages(Argument::any())->shouldBeCalled();

        $stepExecution->incrementSummaryInfo('product_sent')->shouldBeCalled();
        $stepExecution->incrementSummaryInfo('product_image_sent')->shouldBeCalledTimes(1);
        $stepExecution->incrementSummaryInfo('product_translation_sent')->shouldBeCalledTimes(1);

        $this->write($products);

        //$configExportManager->setLastExportDate('sku', $jobInstance)->shouldBeCalled();
    }

    function it_fails_if_something_went_wrong_when_it_updates_a_product(
        $webservice,
        $stepExecution,
        $eventDispatcher
    ) {
        $products = [
            'batch_1' => [
                'product_1' => [
                    'default' => [
                        'sku',
                    ],
                    'en_US'   => [],
                    'images'  => [],
                ],
            ],
        ];

        $webservice->getImages('sku', 'default')->willReturn([]);
        $webservice->sendProduct(['sku'])->willThrow('\Pim\Bundle\MagentoConnectorBundle\Webservice\SoapCallException');
        $webservice->sendImages(Argument::any())->shouldNotBeCalled();
        $webservice->updateProductPart(Argument::any())->shouldNotBeCalled();

        $stepExecution->incrementSummaryInfo('product_sent')->shouldNotBeCalled();
        $stepExecution->incrementSummaryInfo('product_image_sent')->shouldNotBeCalled();
        $stepExecution->incrementSummaryInfo('product_translation_sent')->shouldNotBeCalled(1);
        $stepExecution->addWarning(Argument::cetera())->shouldBeCalled();

        $eventDispatcher
            ->dispatch(
                EventInterface::INVALID_ITEM,
                Argument::type('Akeneo\Bundle\BatchBundle\Event\InvalidItemEvent')
            )
            ->shouldBeCalled();

        $this->write($products);
    }

    function it_fails_if_something_went_wrong_when_it_prunes_images(
        $webservice,
        $stepExecution,
        $eventDispatcher
    ) {
        $products = [
            'batch_1' => [
                'product_1' => [
                    'default' => [
                        'sku',
                    ],
                    'en_US'   => [],
                    'images'  => [],
                ],
            ],
        ];

        $webservice->getImages('sku', 'default')->willReturn([['file' => 'foo'], ['file' => 'bar']]);
        $webservice->deleteImage('sku', 'foo')->willThrow(
            '\Pim\Bundle\MagentoConnectorBundle\Webservice\SoapCallException'
        );
        $webservice->sendProduct(Argument::any())->shouldNotBeCalled();
        $webservice->sendImages(Argument::any())->shouldNotBeCalled();
        $webservice->updateProductPart(Argument::any())->shouldNotBeCalled();

        $stepExecution->incrementSummaryInfo('product_sent')->shouldNotBeCalled();
        $stepExecution->incrementSummaryInfo('product_image_sent')->shouldNotBeCalled();
        $stepExecution->addWarning(Argument::cetera())->shouldBeCalled();

        $eventDispatcher
            ->dispatch(
                EventInterface::INVALID_ITEM,
                Argument::type('Akeneo\Bundle\BatchBundle\Event\InvalidItemEvent')
            )
            ->shouldBeCalled();

        $this->write($products);
    }

    function it_gives_a_configuration_field()
    {
        $this->getConfigurationFields()->shouldReturn(
            [
                'soapUsername'     => [
                    'options' => [
                        'required' => true,
                        'help'     => 'pim_magento_connector.export.soapUsername.help',
                        'label'    => 'pim_magento_connector.export.soapUsername.label',
                    ],
                ],
                'soapApiKey'       => [
                    'type'    => 'text',
                    'options' => [
                        'required' => true,
                        'help'     => 'pim_magento_connector.export.soapApiKey.help',
                        'label'    => 'pim_magento_connector.export.soapApiKey.label',
                    ],
                ],
                'magentoUrl'       => [
                    'options' => [
                        'required' => true,
                        'help'     => 'pim_magento_connector.export.magentoUrl.help',
                        'label'    => 'pim_magento_connector.export.magentoUrl.label',
                    ],
                ],
                'wsdlUrl'          => [
                    'options' => [
                        'required' => true,
                        'help'     => 'pim_magento_connector.export.wsdlUrl.help',
                        'label'    => 'pim_magento_connector.export.wsdlUrl.label',
                        'data'     => '/api/soap/?wsdl',
                    ],
                ],
                'httpLogin'        => [
                    'options' => [
                        'required' => false,
                        'help'     => 'pim_magento_connector.export.httpLogin.help',
                        'label'    => 'pim_magento_connector.export.httpLogin.label',
                    ],
                ],
                'httpPassword'     => [
                    'options' => [
                        'required' => false,
                        'help'     => 'pim_magento_connector.export.httpPassword.help',
                        'label'    => 'pim_magento_connector.export.httpPassword.label',
                    ],
                ],
                'defaultStoreView' => [
                    'options' => [
                        'required' => false,
                        'help'     => 'pim_magento_connector.export.defaultStoreView.help',
                        'label'    => 'pim_magento_connector.export.defaultStoreView.label',
                        'data'     => 'default',
                    ],
                ],
                'channel'          => [
                    'type'    => 'choice',
                    'options' => [
                        'choices'  => null,
                        'required' => true,
                        'help'     => 'pim_magento_connector.export.channel.help',
                        'label'    => 'pim_magento_connector.export.channel.label',
                    ],
                ],
            ]
        );
    }
}

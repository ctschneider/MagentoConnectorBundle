<?php

namespace Pim\Bundle\MagentoConnectorBundle\Writer;

use Akeneo\Bundle\BatchBundle\Item\InvalidItemException;
use Pim\Bundle\MagentoConnectorBundle\Guesser\WebserviceGuesser;
use Pim\Bundle\MagentoConnectorBundle\Manager\DeltaProductExportManager;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientParametersRegistry;
use Pim\Bundle\MagentoConnectorBundle\Webservice\SoapCallException;

/**
 * Delta product association writer
 *
 * @author    Damien Carcel (https://github.com/damien-carcel)
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class DeltaProductAssociationWriter extends ProductAssociationWriter
{
    /** @var DeltaProductExportManager */
    protected $productExportManager;

    /**
     * @param WebserviceGuesser                   $webserviceGuesser
     * @param MagentoSoapClientParametersRegistry $clientParamsRegistry
     * @param DeltaProductExportManager           $productExportManager
     */
    public function __construct(
        WebserviceGuesser $webserviceGuesser,
        MagentoSoapClientParametersRegistry $clientParamsRegistry,
        DeltaProductExportManager $productExportManager
    ) {
        parent::__construct($webserviceGuesser, $clientParamsRegistry);

        $this->productExportManager = $productExportManager;
    }

    /**
     * {@inheritdoc}
     */
    protected function handleProductAssociationCalls(array $associationCalls)
    {
        foreach ($associationCalls['remove'] as $removeCall) {
            try {
                $this->webservice->removeProductAssociation($removeCall);
                $this->productExportManager->updateProductAssociationExport(
                    $this->getJobInstance(),
                    $removeCall['product']
                );
            } catch (SoapCallException $e) {
                throw new InvalidItemException(
                    sprintf(
                        'An error occured during a product association remove call. This may be due to a linked '.
                        'product that doesn\'t exist on Magento side. Error message : %s',
                        $e->getMessage()
                    ),
                    $removeCall
                );
            }
        }

        foreach ($associationCalls['create'] as $createCall) {
            try {
                $this->webservice->createProductAssociation($createCall);
                $this->productExportManager->updateProductAssociationExport(
                    $this->getJobInstance(),
                    $createCall['product']
                );
            } catch (SoapCallException $e) {
                throw new InvalidItemException(
                    sprintf(
                        'An error occured during a product association add call. This may be due to a linked '.
                        'product that doesn\'t exist on Magento side. Error message : %s',
                        $e->getMessage()
                    ),
                    $createCall
                );
            }
        }
    }

    /**
     * @return \Akeneo\Bundle\BatchBundle\Entity\JobInstance
     */
    protected function getJobInstance()
    {
        return $this->stepExecution->getJobExecution()->getJobInstance();
    }
}

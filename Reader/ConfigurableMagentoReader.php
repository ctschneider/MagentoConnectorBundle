<?php

namespace Pim\Bundle\MagentoConnectorBundle\Reader;

use Symfony\Component\Validator\Constraints as Assert;
use Pim\Bundle\ImportExportBundle\Reader\ORM\Reader;
use Pim\Bundle\ImportExportBundle\Validator\Constraints\Channel as ChannelConstraint;
use Pim\Bundle\ImportExportBundle\Converter\MetricConverter;
use Pim\Bundle\CatalogBundle\Manager\ChannelManager;
use Pim\Bundle\CatalogBundle\Manager\CompletenessManager;
use Pim\Bundle\CatalogBundle\Entity\Channel;
use Pim\Bundle\MagentoConnectorBundle\Repository\GroupRepository;

/**
 * Product reader
  *
  * @author    Julien Sanchhez <julien@akeneo.com>
  * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
  * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
  */
class ConfigurableMagentoReader extends Reader
{
    /**
     * @var string
     *
     * @Assert\NotBlank(groups={"Execution"})
     * @ChannelConstraint
     */
    protected $channel;

    /** @var GroupRepository */
    protected $groupRepository;

    /** @var ChannelManager */
    protected $channelManager;

    /** @var CompletenessManager */
    protected $completenessManager;

    /* @var MetricConverter */
    protected $metricConverter;

    /**
     * @param GroupRepository     $groupRepository
     * @param ChannelManager      $channelManager
     * @param CompletenessManager $completenessManager
     * @param MetricConverter     $metricConverter
     */
    public function __construct(
        GroupRepository $groupRepository,
        ChannelManager $channelManager,
        CompletenessManager $completenessManager,
        MetricConverter $metricConverter
    ) {
        $this->groupRepository     = $groupRepository;
        $this->channelManager      = $channelManager;
        $this->completenessManager = $completenessManager;
        $this->metricConverter     = $metricConverter;
    }

    /**
     * {@inheritdoc}
     */
    public function read()
    {
        if (!$this->query) {
            $channel = current($this->channelManager->getChannels(array('code' => $this->channel)));
            if (!$channel) {
                throw new \InvalidArgumentException(
                    sprintf('Could not find the channel %s', $this->channel)
                );
            }

            $this->completenessManager->generateChannelCompletenesses($channel);

<<<<<<< Updated upstream
            $this->query = $this->getProductRepository()
                ->buildByChannelAndCompleteness($channel)
                ->getQuery();
=======
            var_dump($this->groupRepository->getChoices());

            // $this->query = $this->getProductRepository()
            //     ->buildByChannelAndCompleteness($channel)
            //     ->getQuery();

            // echo ($this->query->getSQL());
>>>>>>> Stashed changes
        }

        // $products = parent::read();

        // if (is_array($products)) {
        //     $this->metricConverter->convert($products, $channel);
        // }

        // return $products;
    }

    /**
     * Set channel
     * @param string $channel
     */
    public function setChannel($channel)
    {
        $this->channel = $channel;
    }

    /**
     * Get channel
     * @return string
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigurationFields()
    {
        return array(
            'channel' => array(
                'type'    => 'choice',
                'options' => array(
                    'choices'  => $this->channelManager->getChannelChoices(),
                    'required' => true,
                    'select2'  => true,
                    'label'    => 'pim_import_export.export.channel.label',
                    'help'     => 'pim_import_export.export.channel.help'
                )
            )
        );
    }
}

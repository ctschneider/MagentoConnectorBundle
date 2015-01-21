<?php

namespace Pim\Bundle\MagentoConnectorBundle\Normalizer;

use Pim\Bundle\CatalogBundle\Manager\ChannelManager;
use Pim\Bundle\CatalogBundle\Model\AbstractProductMedia;
use Pim\Bundle\CatalogBundle\Manager\MediaManager;
use Pim\Bundle\CatalogBundle\Model\ProductInterface;
use Pim\Bundle\MagentoConnectorBundle\Webservice\Webservice;
use Pim\Bundle\MagentoConnectorBundle\Manager\CategoryMappingManager;
use Pim\Bundle\MagentoConnectorBundle\Manager\AssociationTypeManager;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\Exception\CategoryNotFoundException;
use Pim\Bundle\MagentoConnectorBundle\Mapper\MappingCollection;
use Gedmo\Sluggable\Util\Urlizer;

/**
 * A normalizer to transform a product entity into an array
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductNormalizer extends AbstractNormalizer implements ProductNormalizerInterface
{
    const VISIBILITY = 'visibility';
    const URL_KEY    = 'url_key';
    const NAME       = 'name';
    const ENABLED    = 'status';

    /**
     * @var boolean
     */
    protected $enabled;

    /**
     * @var boolean
     */
    protected $visibility;

    /** @var string */
    protected $currencyCode;

    /**
     * @var MediaManager
     */
    protected $mediaManager;

    /**
     * @var AssociationTypeManager
     */
    protected $associationTypeManager;

    /**
     * @var ProductValueNormalizer
     */
    protected $productValueNormalizer;

    /**
     * Constructor
     * @param ChannelManager         $channelManager
     * @param MediaManager           $mediaManager
     * @param ProductValueNormalizer $productValueNormalizer
     * @param CategoryMappingManager $categoryMappingManager
     * @param AssociationTypeManager $associationTypeManager
     * @param bool                   $enabled
     * @param bool                   $visibility
     * @param bool                   $variantMemberVisibility
     * @param string                 $currencyCode
     * @param string                 $magentoUrl
     */
    public function __construct(
        ChannelManager $channelManager,
        MediaManager $mediaManager,
        ProductValueNormalizer $productValueNormalizer,
        CategoryMappingManager $categoryMappingManager,
        AssociationTypeManager $associationTypeManager,
        $enabled,
        $visibility,
        $variantMemberVisibility,
        $currencyCode,
        $magentoUrl
    ) {
        parent::__construct($channelManager);

        $this->mediaManager            = $mediaManager;
        $this->productValueNormalizer  = $productValueNormalizer;
        $this->categoryMappingManager  = $categoryMappingManager;
        $this->associationTypeManager  = $associationTypeManager;
        $this->enabled                 = $enabled;
        $this->visibility              = $visibility;
        $this->variantMemberVisibility = $variantMemberVisibility;
        $this->currencyCode            = $currencyCode;
        $this->magentoUrl              = $magentoUrl;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = [])
    {
        $processedItem = [];

        $processedItem[$context['defaultStoreView']] = $this->getDefaultProduct(
            $object,
            $context['magentoAttributes'],
            $context['magentoAttributesOptions'],
            $context['attributeSetId'],
            $context['defaultLocale'],
            $context['channel'],
            $context['website'],
            $context['categoryMapping'],
            $context['attributeCodeMapping'],
            $context['pimGrouped'],
            $context['create'],
            $context['defaultStoreView']
        );

        $images = $this->getNormalizedImages(
            $object,
            $object->getIdentifier(),
            $context['smallImageAttribute'],
            $context['baseImageAttribute'],
            $context['thumbnailAttribute']
        );

        if (count($images) > 0) {
            $processedItem[Webservice::IMAGES] = $images;
        }

        //For each storeview, we update the product only with localized attributes
        foreach ($this->getPimLocales($context['channel']) as $locale) {
            $storeView = $this->getStoreViewForLocale(
                $locale->getCode(),
                $context['magentoStoreViews'],
                $context['storeViewMapping']
            );

            //If a locale for this storeview exist in PIM, we create a translated product in this locale
            if ($storeView && $storeView['code'] !== $context['defaultStoreView']) {
                $values = $this->getValues(
                    $object,
                    $context['magentoAttributes'],
                    $context['magentoAttributesOptions'],
                    $locale->getCode(),
                    $context['channel'],
                    $context['categoryMapping'],
                    $context['attributeCodeMapping'],
                    true,
                    $context['pimGrouped']
                );

                $processedItem[$storeView['code']] = [
                    (string) $object->getIdentifier(),
                    $values,
                    $storeView['code'],
                    'sku',
                ];
            } else {
                if ($locale->getCode() !== $context['defaultLocale']) {
                    $this->localeNotFound($locale);
                }
            }
        }

        return $processedItem;
    }

    /**
     * Get all images of a product normalized
     *
     * @param ProductInterface $product
     * @param string           $sku
     *
     * @return array
     */
    public function getNormalizedImages(
        ProductInterface $product,
        $sku = '',
        $smallImageAttribute = '',
        $baseImageAttribute = '',
        $thumbnailAttribute = ''
    ) {
        $imageValues = $product->getValues()->filter(
            function ($value) {
                return $value->getData() instanceof AbstractProductMedia &&
                    in_array($value->getData()->getMimeType(), array('image/jpeg', 'image/png', 'image/gif'));
            }
        );

        if ($sku === '') {
            $sku = $product->getIdentifier();
        }

        $images = [];

        foreach ($imageValues as $imageValue) {
            $data = $imageValue->getData();

            if ($imageData = $this->mediaManager->getBase64($data)) {
                $imageTypes = array_merge(
                    $imageValue->getAttribute()->getCode() == $smallImageAttribute ? [Webservice::SMALL_IMAGE] : [],
                    $imageValue->getAttribute()->getCode() == $baseImageAttribute ? [Webservice::BASE_IMAGE] : [],
                    $imageValue->getAttribute()->getCode() == $thumbnailAttribute ? [Webservice::THUMBNAIL] : []
                );

                $images[] = [
                    (string) $sku,
                    [
                        'file' => [
                            'name'    => $data->getFilename(),
                            'content' => $imageData,
                            'mime'    => $data->getMimeType(),
                        ],
                        'label'    => $data->getFilename(),
                        'position' => 0,
                        'types'    => $imageTypes,
                        'exclude'  => 0
                    ],
                    0,
                    'sku',
                ];
            }
        }

        return $images;
    }

    /**
     * Get the default product with all attributes (ie : event the non localizable ones)
     *
     * @param ProductInterface  $product                  The given product
     * @param array             $magentoAttributes        Attribute list from Magento
     * @param array             $magentoAttributesOptions Attribute options list from Magento
     * @param integer           $attributeSetId           Attribute set id
     * @param string            $defaultLocale            Default locale
     * @param string            $channel                  Channel
     * @param string            $website                  Website name
     * @param MappingCollection $categoryMapping          Root category mapping
     * @param MappingCollection $attributeMapping         Attribute mapping
     * @param string            $pimGrouped               Pim grouped association code
     * @param bool              $create                   Is it a creation ?
     * @param array             $context                  Context
     *
     * @return array The default product data
     */
    protected function getDefaultProduct(
        ProductInterface $product,
        array $magentoAttributes,
        array $magentoAttributesOptions,
        $attributeSetId,
        $defaultLocale,
        $channel,
        $website,
        MappingCollection $categoryMapping,
        MappingCollection $attributeMapping,
        $pimGrouped,
        $create,
        $defaultStoreValue
    ) {
        $sku           = (string) $product->getIdentifier();
        $defaultValues = $this->getValues(
            $product,
            $magentoAttributes,
            $magentoAttributesOptions,
            $defaultLocale,
            $channel,
            $categoryMapping,
            $attributeMapping,
            false,
            $pimGrouped
        );

        $defaultValues['websites'] = [$website];

        if ($create) {
            if ($this->hasGroupedProduct($product, $pimGrouped)) {
                $productType = self::MAGENTO_GROUPED_PRODUCT_KEY;
            } else {
                $productType = self::MAGENTO_SIMPLE_PRODUCT_KEY;
            }

            //For the default storeview we create an entire product
            $defaultProduct = [
                $productType,
                $attributeSetId,
                $sku,
                $defaultValues,
                $defaultStoreValue,
            ];
        } else {
            $defaultProduct = [
                $sku,
                $defaultValues,
                $defaultStoreValue,
                'sku',
            ];
        }

        return $defaultProduct;
    }

    /**
     * Test if a product has grouped products
     * @param ProductInterface $product
     * @param string           $pimGrouped
     *
     * @return boolean
     */
    protected function hasGroupedProduct(ProductInterface $product, $pimGrouped)
    {
        $association = $product->getAssociationForTypeCode($pimGrouped);

        return (null !== $association && count($association->getProducts()) > 0);
    }

    /**
     * Get values array for a given product
     *
     * @param ProductInterface  $product                  The given product
     * @param array             $magentoAttributes        Attribute list from Magento
     * @param array             $magentoAttributesOptions Attribute options list from Magento
     * @param string            $localeCode               The locale to apply
     * @param string            $scopeCode                The akeneo scope
     * @param MappingCollection $categoryMapping          Root category mapping
     * @param MappingCollection $attributeCodeMapping     Attribute mapping
     * @param boolean           $onlyLocalized            If true, only get translatable attributes
     * @param string            $pimGrouped               Pim grouped association code
     *
     * @return array Computed data
     */
    public function getValues(
        ProductInterface $product,
        array $magentoAttributes,
        array $magentoAttributesOptions,
        $localeCode,
        $scopeCode,
        MappingCollection $categoryMapping,
        MappingCollection $attributeCodeMapping,
        $onlyLocalized,
        $pimGrouped = null
    ) {
        $normalizedValues = [];

        $context = [
            'identifier'               => $product->getIdentifier(),
            'scopeCode'                => $scopeCode,
            'localeCode'               => $localeCode,
            'onlyLocalized'            => $onlyLocalized,
            'magentoAttributes'        => $magentoAttributes,
            'magentoAttributesOptions' => $magentoAttributesOptions,
            'attributeCodeMapping'     => $attributeCodeMapping,
            'currencyCode'             => $this->currencyCode,
        ];

        foreach ($product->getValues() as $value) {
            $normalizedValue = $this->productValueNormalizer->normalize($value, 'MagentoArray', $context);
            if ($normalizedValue !== null) {
                $normalizedValues = array_merge(
                    $normalizedValues,
                    $normalizedValue
                );
            }
        }

        $normalizedValues = array_merge(
            $normalizedValues,
            $this->getCustomValue(
                $product,
                $attributeCodeMapping,
                ['categoryMapping' => $categoryMapping, 'scopeCode' => $scopeCode, 'localeCode' => $localeCode, 'pimGrouped' => $pimGrouped]
            )
        );

        ksort($normalizedValues);

        return $normalizedValues;
    }

    /**
     * Get categories for the given product
     * @param ProductInterface  $product
     * @param MappingCollection $categoryMapping
     * @param string            $scopeCode
     *
     * @return array
     */
    protected function getProductCategories(ProductInterface $product, MappingCollection $categoryMapping, $scopeCode)
    {
        $productCategories = [];

        $channelCategoryTree = $this->channelManager->getChannelByCode($scopeCode)->getCategory();

        foreach ($product->getCategories() as $category) {
            if ($category->getRoot() == $channelCategoryTree->getId()) {
                $magentoCategoryId = $this->categoryMappingManager->getIdFromCategory(
                    $category,
                    $this->magentoUrl,
                    $categoryMapping
                );

                if (!$magentoCategoryId) {
                    throw new CategoryNotFoundException(
                        sprintf(
                            'The category %s was not found. Please export categories first or add it to the root '.
                            'category mapping',
                            $category->getLabel()
                        )
                    );
                }

                $productCategories[] = $magentoCategoryId;
            }
        }

        return $productCategories;
    }

    /**
     * Get custom values (not provided by the PIM product)
     * @param ProductInterface  $product
     * @param MappingCollection $attributeCodeMapping
     * @param array             $parameters
     *
     * @return mixed
     */
    protected function getCustomValue(
        ProductInterface $product,
        MappingCollection $attributeCodeMapping,
        array $parameters = []
    ) {
        if ($this->belongsToVariant($product) &&
            null !== $parameters['pimGrouped'] &&
            !$this->hasGroupedProduct($product, $parameters['pimGrouped'])) {
            $visibility = $this->variantMemberVisibility;
        } else {
            $visibility = $this->visibility;
        }

        return [
            strtolower($attributeCodeMapping->getTarget(self::URL_KEY))    => $this->generateUrlKey(
                    $product,
                    $attributeCodeMapping,
                    $parameters['localeCode'],
                    $parameters['scopeCode']
                ),
            strtolower($attributeCodeMapping->getTarget(self::VISIBILITY)) => $visibility,
            strtolower($attributeCodeMapping->getTarget(self::ENABLED))    => (string) ($this->enabled) ? 1 : 2,
            strtolower($attributeCodeMapping->getTarget('created_at'))     => $product->getCreated()
                ->format(AbstractNormalizer::DATE_FORMAT),
            strtolower($attributeCodeMapping->getTarget('updated_at'))     => $product->getUpdated()
                ->format(AbstractNormalizer::DATE_FORMAT),
            strtolower($attributeCodeMapping->getTarget('categories'))     => $this->getProductCategories(
                $product,
                $parameters['categoryMapping'],
                $parameters['scopeCode']
            )
        ];
    }

    /**
     * Check if the product belongs to a variant group
     *
     * @param ProductInterface $product
     *
     * @return boolean
     */
    protected function belongsToVariant(ProductInterface $product)
    {
        foreach ($product->getGroups() as $group) {
            if ($group->getType()->isVariant()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate url key from product name and identifier.
     * The identifier is included to make sure the url_key is unique, as required in Magento
     *
     * If name is localized, the default locale is used to get the value.
     *
     * @param ProductInterface  $product
     * @param MappingCollection $attributeCodeMapping
     * @param string            $localeCode
     * @param string            $scopeCode
     *
     * @return string
     */
    protected function generateUrlKey(
        ProductInterface $product,
        MappingCollection $attributeCodeMapping,
        $localeCode,
        $scopeCode
    ) {
        $identifier = $product->getIdentifier();
        $nameAttribute = $attributeCodeMapping->getSource(self::NAME);

        $name = $product->getValue($nameAttribute, $localeCode, $scopeCode);

        $url = Urlizer::urlize($name.'-'.$identifier);

        return $url;
    }
}

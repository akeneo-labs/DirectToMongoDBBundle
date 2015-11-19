<?php

namespace Pim\Bundle\DirectToMongoDBBundle\Normalizer;

use Akeneo\Bundle\StorageUtilsBundle\MongoDB\MongoObjectsFactory;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Util\ClassUtils;
use Pim\Bundle\CatalogBundle\Model\AttributeInterface;
use Pim\Bundle\CatalogBundle\Model\ProductValueInterface;
use Pim\Bundle\TransformBundle\Normalizer\MongoDB\ProductNormalizer;
use Pim\Bundle\TransformBundle\Normalizer\MongoDB\ProductValueNormalizer as BaseProductValueNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Override of the MongoDB/ProductValueNormalizer to
 * fix some bugs
 *
 * @author    Benoit Jacquemont <benoit@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductValueNormalizer extends BaseProductValueNormalizer
{
    /** @var ManagerRegistry */
    protected $managerRegistry;

    /**
     * @param MongoObjectsFactory $mongoFactory
     * @param ManagerRegistry     $managerRegistry
     */
    public function __construct(MongoObjectsFactory $mongoFactory, ManagerRegistry $managerRegistry)
    {
        parent::__construct($mongoFactory);

        $this->managerRegistry = $managerRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($value, $format = null, array $context = [])
    {
        if (!$this->normalizer instanceof NormalizerInterface) {
            throw new \LogicException('Serializer must be a normalizer');
        }

        $productCollection = $context[ProductNormalizer::MONGO_COLLECTION_NAME];
        $productId = $context[ProductNormalizer::MONGO_ID];
        $databaseName = $context[ProductNormalizer::MONGO_DATABASE_NAME];

        $data = [];
        $data['_id'] = $this->mongoFactory->createMongoId();
        $data['attribute'] = $value->getAttribute()->getId();
        $data['entity'] = $this->mongoFactory->createMongoDBRef($productCollection, $productId, $databaseName);

        if (null !== $value->getLocale()) {
            $data['locale'] = $value->getLocale();
        }
        if (null !== $value->getScope()) {
            $data['scope'] = $value->getScope();
        }

        $attribute   = $value->getAttribute();
        $backendType = $attribute->getBackendType();
        $key         = $this->getKeyForValue($value, $attribute, $backendType);
        $data[$key]  = $this->normalizeValueData($value->getData(), $backendType, $context);

        return $data;
    }

    /**
     * Decide what is the key used for data inside the normalized product value
     *
     * @param ProductValueInterface $value
     * @param AttributeInterface    $attribute
     * @param string                $backendType
     *
     * @return string
     */
    protected function getKeyForValue(ProductValueInterface $value, AttributeInterface $attribute, $backendType)
    {
        if ('options' === $backendType) {
            return 'optionIds';
        }

        $refDataName = $attribute->getReferenceDataName();
        if (null !== $refDataName) {
            if ('reference_data_options' === $backendType) {
                return $this->getReferenceDataFieldName($value, $refDataName);
            }

            return $refDataName;
        }

        return $backendType;
    }

    /**
     * Search in Doctrine mapping what is the field name defined for the specified reference data
     *
     * @param ProductValueInterface $value
     * @param string                $refDataName
     *
     * @throws \LogicException
     *
     * @return string
     */
    protected function getReferenceDataFieldName(ProductValueInterface $value, $refDataName)
    {
        $valueClass = ClassUtils::getClass($value);
        $manager    = $this->managerRegistry->getManagerForClass($valueClass);
        $metadata   = $manager->getClassMetadata($valueClass);
        $fieldName  = $metadata->getFieldMapping($refDataName);

        if (!isset($fieldName['idsField'])) {
            throw new \LogicException(sprintf('No field name defined for reference data "%s"', $refDataName));
        }

        return $fieldName['idsField'];
    }
}

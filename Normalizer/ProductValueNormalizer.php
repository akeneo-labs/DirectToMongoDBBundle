<?php

namespace Pim\Bundle\DirectToMongoDBBundle\Normalizer;

use Akeneo\Bundle\StorageUtilsBundle\MongoDB\MongoObjectsFactory;
use Doctrine\Common\Collections\Collection;
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
    /**
     * {@inheritdoc}
     */
    public function normalize($value, $format = null, array $context = [])
    {
        if (!$this->normalizer instanceof NormalizerInterface) {
            throw new \LogicException('Serializer must be a normalizer');
        }

        $productId = $context[ProductNormalizer::MONGO_ID];
        $productCollection = $context[ProductNormalizer::MONGO_COLLECTION_NAME];

        $data = [];
        $data['_id'] = $this->mongoFactory->createMongoId();
        $data['attribute'] = $value->getAttribute()->getId();
        $data['entity'] = $this->mongoFactory->createMongoDBRef($productCollection, $productId);

        if (null !== $value->getLocale()) {
            $data['locale'] = $value->getLocale();
        }
        if (null !== $value->getScope()) {
            $data['scope'] = $value->getScope();
        }

        $backendType = $value->getAttribute()->getBackendType();

        if ('options' !== $backendType) {
            $data[$backendType] = $this->normalizeValueData($value->getData(), $backendType, $context);
        } else {
            $data['optionIds'] = $this->normalizeValueData($value->getData(), $backendType, $context);
        }

        return $data;
    }
}

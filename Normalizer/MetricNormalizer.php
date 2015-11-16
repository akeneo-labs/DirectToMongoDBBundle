<?php

namespace Pim\Bundle\DirectToMongoDBBundle\Normalizer;

use Akeneo\Bundle\MeasureBundle\Convert\MeasureConverter;
use Akeneo\Bundle\MeasureBundle\Manager\MeasureManager;
use Akeneo\Bundle\StorageUtilsBundle\MongoDB\MongoObjectsFactory;
use Doctrine\Common\Collections\Collection;
use Pim\Bundle\CatalogBundle\Model\MetricInterface;
use Pim\Bundle\CatalogBundle\Model\ProductValueInterface;
use Pim\Bundle\TransformBundle\Normalizer\MongoDB\MetricNormalizer as BaseMetricNormalizer;
use Pim\Bundle\TransformBundle\Normalizer\MongoDB\ProductNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Override of the MongoDB/MetricNormalizer to
 * fix some bugs
 *
 * @author    Benoit Jacquemont <filips@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MetricNormalizer extends BaseMetricNormalizer
{
    /**
     * {@inheritdoc}
     */
    public function normalize($metric, $format = null, array $context = [])
    {
        $data = [];
        $data['_id']    = $this->mongoFactory->createMongoId();
        $data['family'] = $metric->getFamily();

        if (null === $metric->getData() || "" === $metric->getData() ||
            null === $metric->getUnit() || "" === $metric->getUnit()) {
            return $data;
        }

        $this->createMetricBaseValues($metric);

        $data['unit']     = $metric->getUnit();
        $data['data']     = $metric->getData();
        $data['baseUnit'] = $metric->getBaseUnit();
        $data['baseData'] = $metric->getBaseData();

        return $data;
    }
}

<?php

namespace Pim\Bundle\DirectToMongoDBBundle\Saver;

use Akeneo\Bundle\StorageUtilsBundle\MongoDB\MongoObjectsFactory;
use Akeneo\Component\StorageUtils\Saver\SavingOptionsResolverInterface;
use Akeneo\Component\StorageUtils\StorageEvents;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Util\ClassUtils;
use Pim\Bundle\CatalogBundle\Doctrine\Common\Saver\ProductSaver as BaseProductSaver;
use Pim\Bundle\CatalogBundle\Manager\CompletenessManager;
use Pim\Bundle\CatalogBundle\Model\ProductInterface;
use Pim\Bundle\DirectToMongoDBBundle\Versioning\BulkVersionPersister;
use Pim\Bundle\TransformBundle\Normalizer\MongoDB\ProductNormalizer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Direct To Db Mongo bulk product saver
 *
 * @author    Benoit Jacquemont <benoit@akeneo.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductSaver extends BaseProductSaver
{
    /**@var BulkVersionPersister */
    protected $versionPersister;

    /** @var NormalizerInterface */
    protected $normalizer;

    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /** @var MongoObjectsFactory */
    protected $mongoFactory;

    /** @var string */
    protected $productClass;

    /** @var Collection */
    protected $collection;

    /**
     * @param ObjectManager                  $om
     * @param CompletenessManager            $completenessManager
     * @param SavingOptionsResolverInterface $optionsResolver
     * @param EventDispatcherInterface       $eventDispatcher
     */
    public function __construct(
        ObjectManager $om,
        CompletenessManager $completenessManager,
        SavingOptionsResolverInterface $optionsResolver,
        EventDispatcherInterface $eventDispatcher,
        BulkVersionPersister $versionPersister,
        NormalizerInterface $normalizer,
        MongoObjectsFactory $mongoFactory,
        $productClass
    ) {
        parent::__construct($om, $completenessManager, $optionsResolver, $eventDispatcher);

        $this->versionPersister = $versionPersister;
        $this->normalizer       = $normalizer;
        $this->mongoFactory     = $mongoFactory;
        $this->productClass     = $productClass;

        $this->collection = $this->objectManager->getDocumentCollection($this->productClass);
    }

    /**
     * {@inheritdoc}
     *
     * Override to do a massive save for the products
     */
    public function saveAll(array $products, array $options = [])
    {
        if (empty($products)) {
            return;
        }

        $this->eventDispatcher->dispatch(StorageEvents::PRE_SAVE_ALL, new GenericEvent($products));

        $allOptions = $this->optionsResolver->resolveSaveAllOptions($options);

        $itemOptions = $allOptions;
        $itemOptions['flush'] = false;

        $productsToInsert = [];
        $productsToUpdate = [];
        foreach ($products as $product) {
            if (null === $product->getId()) {
                $productsToInsert[] = $product;
                $product->setId($this->mongoFactory->createMongoId());
            } else {
                $productsToUpdate[] = $product;
            }
        }

        $insertDocs = $this->getDocsFromProducts($productsToInsert);
        $updateDocs = $this->getDocsFromProducts($productsToUpdate);

        if (count($insertDocs) > 0) {
            $this->insertDocuments($insertDocs);
        }

        if (count($updateDocs) > 0) {
            $this->updateDocuments($updateDocs);
        }

        $this->versionPersister->bulkPersist($products);

        $this->eventDispatcher->dispatch(StorageEvents::POST_SAVE_ALL, new GenericEvent($products));
    }

    /**
     * Normalize products into their MongoDB document representation
     *
     * @param ProductInterface[] $products
     *
     * @return array
     */
    protected function getDocsFromProducts(array $products)
    {
        $context = [ProductNormalizer::MONGO_COLLECTION_NAME => $this->collection->getName()];

        $docs = [];
        foreach ($products as $product) {
            $docs[] = $this->normalizer->normalize($product, ProductNormalizer::FORMAT, $context);
        }

        return $docs;
    }

    /**
     * Insert the provided products documents into MongoDB
     * with the batch insert method
     *
     * @param array $docs
     */
    protected function insertDocuments($docs)
    {
        $this->collection->batchInsert($docs);
    }

    /**
     * Apply update from the provided products documents into MongoDB
     *
     * @param array $docs
     */
    protected function updateDocuments($docs)
    {
        foreach ($docs as $doc) {
            $criteria = [
                '_id' => $doc['_id']
            ];
            $this->collection->update($criteria, $doc);
        }
    }
}


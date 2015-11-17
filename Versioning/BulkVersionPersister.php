<?php

namespace Pim\Bundle\DirectToMongoDBBundle\Versioning;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pim\Bundle\TransformBundle\Normalizer\MongoDB\VersionNormalizer;
use Pim\Bundle\VersioningBundle\Builder\VersionBuilder;
use Pim\Bundle\VersioningBundle\Event\BuildVersionEvent;
use Pim\Bundle\VersioningBundle\Event\BuildVersionEvents;
use Pim\Bundle\VersioningBundle\Manager\VersionContext;
use Pim\Bundle\VersioningBundle\Manager\VersionManager;
use Pim\Bundle\VersioningBundle\Model\VersionableInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Service to massively insert versions.
 * Useful for bulk saving of versionable objects.
 *
 * @author    Benoit Jacquemont <benoit@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class BulkVersionPersister
{
    /** @var DocumentManager */
    protected $documentManager;

    /** @var VersionManager */
    protected $versionManager;

    /** @var VersionBuilder */
    protected $versionBuilder;

    /** @var VersionContext */
    protected $versionContext;

    /** @var string */
    protected $versionClass;

    /** @var NormalizerInterface */
    protected $normalizerInterface;

    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /**
     * @param VersionBuilder           $versionBuilder
     * @param VersionManager           $versionManager
     * @param VersionContext           $versionContext
     * @param NormalizerInterface      $normalizer
     * @param DocumentManager          $documentManager
     * @param EventDispatcherInterface $eventDispatcher
     * @param string                   $versionClass
     */
    public function __construct(
        VersionBuilder $versionBuilder,
        VersionManager $versionManager,
        VersionContext $versionContext,
        NormalizerInterface $normalizer,
        DocumentManager $documentManager,
        EventDispatcherInterface $eventDispatcher,
        $versionClass
    ) {
        $this->versionBuilder  = $versionBuilder;
        $this->versionManager  = $versionManager;
        $this->versionContext  = $versionContext;
        $this->normalizer      = $normalizer;
        $this->documentManager = $documentManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->versionClass    = $versionClass;
    }

    /**
     * Bulk generates and inserts full version records for the provided versionable entities
     * in MongoDB.
     * Return an array of ids of documents that have really changed since the last version.
     *
     * @param array $versionables
     *
     * @return array
     */
    public function bulkPersist(array $versionables)
    {
        $versions = [];
        $changedDocIds = [];

        $event = $this->eventDispatcher->dispatch(BuildVersionEvents::PRE_BUILD, new BuildVersionEvent());
        if (null !== $event && null !== $event->getUsername()) {
            $author = $event->getUsername();
        }

        foreach ($versionables as $versionable) {
            $previousVersion = $this->getPreviousVersion($versionable);

            $context = $this->versionContext->getContextInfo(get_class($versionable));
            $newVersion = $this->versionBuilder->buildVersion($versionable, $author, $previousVersion, $context);

            if (count($newVersion->getChangeSet()) > 0) {
                $versions[] = $newVersion;
                $changedDocIds = $versionable->getId();
            }

            if (null !== $previousVersion) {
                $this->documentManager->detach($previousVersion);
            }
        }

        $mongodbVersions = [];

        foreach ($versions as $version) {
            $mongodbVersions[] = $this->normalizer->normalize($version, VersionNormalizer::FORMAT);
        }

        if (count($mongodbVersions) > 0) {
            $collection = $this->documentManager->getDocumentCollection($this->versionClass);
            $collection->batchInsert($mongodbVersions);
        }

        return $changedDocIds;
    }

    /**
     * Get the last available version for the provided document
     *
     * @param VersionableInterface $versionable
     *
     * @return Version
     */
    protected function getPreviousVersion(VersionableInterface $versionable)
    {
        $versionCollection = $this->documentManager->getDocumentCollection($this->versionClass);

        $resourceName = get_class($versionable);
        $resourceId = $versionable->getId();

        $version = $this->documentManager
            ->createQueryBuilder($this->versionClass)
            ->field('resourceName')->equals($resourceName)
            ->field('resourceId')->equals($resourceId)
            ->limit(1)
            ->sort('loggedAt', 'desc')
            ->getQuery()
            ->getSingleResult();

        return $version;
    }
}

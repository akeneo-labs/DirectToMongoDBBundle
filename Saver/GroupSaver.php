<?php
namespace Pim\Bundle\DirectToMongoDBBundle\Saver;

use Akeneo\Component\StorageUtils\Saver\BulkSaverInterface;
use Akeneo\Component\StorageUtils\Saver\SaverInterface;
use Akeneo\Component\StorageUtils\Saver\SavingOptionsResolverInterface;
use Akeneo\Component\StorageUtils\StorageEvents;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Util\ClassUtils;
use Pim\Bundle\CatalogBundle\Doctrine\Common\Saver\GroupSaver as BaseGroupSaver;
use Pim\Bundle\CatalogBundle\Manager\ProductTemplateApplierInterface;
use Pim\Bundle\CatalogBundle\Manager\ProductTemplateMediaManager;
use Pim\Bundle\CatalogBundle\Model\GroupInterface;
use Pim\Bundle\CatalogBundle\Model\ProductInterface;
use Pim\Bundle\VersioningBundle\Manager\VersionContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Override of the group saver to fix version management
 *
 * @author    Benoit Jacquemont <benoit@akeneo.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class GroupSaver extends BaseGroupSaver
{
    /**
     * {@inheritdoc}
     */
    public function save($group, array $options = [])
    {
        if (!$group instanceof GroupInterface) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Expects a "Pim\Bundle\CatalogBundle\Model\GroupInterface", "%s" provided.',
                    ClassUtils::getClass($group)
                )
            );
        }

        $this->eventDispatcher->dispatch(StorageEvents::PRE_SAVE, new GenericEvent($group));

        $options = $this->optionsResolver->resolveSaveOptions($options);

        $this->versionContext->addContextInfo(
            sprintf('Comes from variant group %s', $group->getCode()),
            $this->productClassName
        );

        if ($group->getType()->isVariant()) {
            $template = $group->getProductTemplate();
            if (null !== $template) {
                $this->templateMediaManager->handleProductTemplateMedia($template);
            }
        }

        $this->objectManager->persist($group);
        if (true === $options['flush']) {
            $this->objectManager->flush();
        }

        if ($group->getType()->isVariant() && true === $options['copy_values_to_products']) {
            $this->copyVariantGroupValues($group);
        } else {
            if (count($options['add_products']) > 0) {
                $this->addProducts($options['add_products']);
            }

            if (count($options['remove_products']) > 0) {
                $this->removeProducts($options['remove_products']);
            }
        }

        $this->eventDispatcher->dispatch(StorageEvents::POST_SAVE, new GenericEvent($group));
    }
}

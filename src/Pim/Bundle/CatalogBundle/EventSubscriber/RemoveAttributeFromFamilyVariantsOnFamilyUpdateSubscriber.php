<?php

declare(strict_types=1);

namespace Pim\Bundle\CatalogBundle\EventSubscriber;

use Akeneo\Component\StorageUtils\StorageEvents;
use Pim\Component\Catalog\Model\AttributeInterface;
use Pim\Component\Catalog\Model\FamilyInterface;
use Pim\Component\Catalog\Model\FamilyVariantInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Whenever an attribute is removed from a family, we need to ensure this attribute is removed from every family
 * variants belonging to this family.
 *
 * @author    Samir Boulil <samir.boulil@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class RemoveAttributeFromFamilyVariantsOnFamilyUpdateSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            StorageEvents::PRE_SAVE => 'removeDeletedAttributeFromFamilyVariants',
        ];
    }

    /**
     * Removes the removed attributes from family from the family variants belonging to this family.
     *
     * @param GenericEvent $event
     */
    public function removeDeletedAttributeFromFamilyVariants(GenericEvent $event): void
    {
        $subject = $event->getSubject();

        if (!$subject instanceof FamilyInterface) {
            return;
        }

        $familyAttributeCodes = $subject->getAttributeCodes();
        foreach ($subject->getFamilyVariants() as $familyVariant) {
            $familyVariantsAttributeCodes = $this->getFamilyVariantsAttributeCodes($familyVariant);
            $toRemoveAttributes = $this->getExtraAttributesFamilyVariant(
                $familyAttributeCodes,
                $familyVariantsAttributeCodes
            );

            if (!empty($toRemoveAttributes)) {
                $this->removeAttributeFromFamilyVariantsAttributeSet($familyVariant, $toRemoveAttributes);
            }
        }
    }

    /**
     * @param FamilyVariantInterface $familyVariant
     *
     * @return array
     */
    private function getFamilyVariantsAttributeCodes(FamilyVariantInterface $familyVariant): array
    {
        $getAttributeCodeFunction = function (AttributeInterface $attribute) {
            return $attribute->getCode();
        };

        return array_merge(
            $familyVariant->getAttributes()->map($getAttributeCodeFunction)->toArray(),
            $familyVariant->getAxes()->map($getAttributeCodeFunction)->toArray()
        );
    }

    /**
     * Returns the attribute codes that exists in the family variant and that does not exist in the family.
     *
     * The returned attributes should be removed from the family variant attribute sets.
     *
     * @param array $familyAttributeCodes
     * @param array $familyVariantsAttributeCodes
     *
     * @return array
     */
    private function getExtraAttributesFamilyVariant(
        array $familyAttributeCodes,
        array $familyVariantsAttributeCodes
    ): array {
        return array_diff($familyVariantsAttributeCodes, $familyAttributeCodes);
    }

    /**
     * Removes the attribute in the given array from the variant attribute set of the given family variant.
     *
     * @param FamilyVariantInterface $familyVariant
     * @param array                  $toRemoveAttributes
     */
    private function removeAttributeFromFamilyVariantsAttributeSet(
        FamilyVariantInterface $familyVariant,
        array $toRemoveAttributes
    ): void {
        foreach ($familyVariant->getVariantAttributeSets() as $variantAttributeSet) {
            foreach ($variantAttributeSet->getAttributes() as $attribute) {
                if (in_array($attribute->getCode(), $toRemoveAttributes)) {
                    $variantAttributeSet->removeAttribute($attribute);
                }
            }
        }
    }
}

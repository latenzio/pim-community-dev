<?php

declare(strict_types=1);

namespace Pim\Component\Catalog\Validator\Constraints;

use Pim\Component\Catalog\Model\AttributeInterface;
use Pim\Component\Catalog\Model\FamilyInterface;
use Pim\Component\Catalog\Model\FamilyVariantInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 *
 * @author    Samir Boulil <samir.boulil@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class FamilyAttributesUsedAsAxisValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($family, Constraint $constraint)
    {
        if (!$family instanceof FamilyInterface) {
            return;
        }

        if (!$constraint instanceof FamilyAttributesUsedAsAxis) {
            return;
        }

        foreach ($family->getFamilyVariants() as $familyVariant) {
            $missingAttributesUsedAsAxis = $this->getMissingAttributeCodesUsedAsAxis($family, $familyVariant);
            $this->buildViolationsForMissingAttributesUsedAsAxis(
                $constraint,
                $familyVariant,
                $missingAttributesUsedAsAxis
            );
        }
    }

    /**
     * @param FamilyInterface        $family
     * @param FamilyVariantInterface $familyVariant
     *
     * @return string[]
     */
    private function getMissingAttributeCodesUsedAsAxis(
        FamilyInterface $family,
        FamilyVariantInterface $familyVariant
    ): array {
        $attributeCodesUsedAsAxis = $familyVariant->getAxes()->map(
            function (AttributeInterface $attribute) {
                return $attribute->getCode();
            }
        );

        return array_diff($attributeCodesUsedAsAxis, $family->getAttributeCodes());
    }

    /**
     * @param Constraint             $constraint
     * @param FamilyVariantInterface $familyVariant
     * @param AttributeInterface[]   $missingAttributeCodesUsedAsAxis
     */
    private function buildViolationsForMissingAttributesUsedAsAxis(
        Constraint $constraint,
        FamilyVariantInterface $familyVariant,
        array $missingAttributeCodesUsedAsAxis
    ): void {
        if (0 < count($missingAttributeCodesUsedAsAxis)) {
            foreach ($missingAttributeCodesUsedAsAxis as $missingAttributeUsedAsAxis) {
                $this->context
                    ->buildViolation($constraint->messageAttribute, [
                        '%attribute%' => $missingAttributeUsedAsAxis,
                        '%family_variant%' => $familyVariant->getCode()
                    ])
                    ->addViolation();
            }
        }
    }
}

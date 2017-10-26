<?php

declare(strict_types=1);

namespace tests\integration\Pim\Bundle\CatalogBundle\EventSubscriber;

use Akeneo\Test\Integration\Configuration;
use Akeneo\Test\Integration\TestCase;
use Pim\Component\Catalog\Model\AttributeInterface;
use Pim\Component\Catalog\Model\FamilyVariantInterface;
use Pim\Component\Catalog\Validator\Constraints\FamilyAttributeUsedAsAxis;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Tries to update family attributes using imports.
 *
 * @author    Samir Boulil <samir.boulil@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class UpdateFamilyIntegration extends TestCase
{
    public function testRemovingSimpleAttributeFromFamilyIsAllowed()
    {
        $violations = $this->removeAttributeFromFamily('shoes', 'material');
        $this->assertEquals(0, $violations->count());
    }

    public function testRemovingAxisAttributeOfAFamilyVariantFromFamilyIsNotAllowed()
    {
        $violations = $this->removeAttributeFromFamily('shoes', 'size');
        $this->assertEquals(1, $violations->count());
        $violation = $violations->getIterator()->current();
        $this->assertEquals(FamilyAttributeUsedAsAxis::class, get_class($violation->getConstraint()));
        $this->assertEquals(
            'Attribute "size" is an axis in "shoes_size_color" family variant. It must belong to the family.',
            $violation->getMessage()
        );
    }

    public function testRemovingAttributeOfAFamilyAlsoRemovesItFromTheFamilyVariants()
    {
        $errors = $this->removeAttributeFromFamily('shoes', 'weight');
        $this->assertEquals(0, $errors->count());
        $this->assertAttributeMissingFromFamilyVariants('weight', 'shoes');
    }

    public function testRemovingMultipleAttributesOfAFamilyAlsoRemovesItFromTheFamilyVariants()
    {
        $errors = $this->removeAttributeFromFamily('shoes', 'weight');
        $this->assertEquals(0, $errors->count());
        $this->assertAttributeMissingFromFamilyVariants('weight', 'shoes');
    }

    /**
     * @return Configuration
     */
    protected function getConfiguration(): Configuration
    {
        return $this->catalog->useFunctionalCatalog('catalog_modeling');
    }

    /**
     * @param string $familyCode
     * @param string $attributeCode
     */
    private function removeAttributeFromFamily(
        string $familyCode,
        string $attributeCode
    ): ConstraintViolationListInterface {
        $family = $this->get('pim_catalog.repository.family')->findOneByIdentifier($familyCode);
        $attribute = $this->get('pim_catalog.repository.attribute')->findOneByIdentifier($attributeCode);
        $family->removeAttribute($attribute);
        $violations = $this->get('validator')->validate($family);

        if ($violations->count() === 0) {
            $this->get('pim_catalog.saver.family')->save($family);
        }

        return $violations;
    }

    /**
     * Asserts that the given attribute code does not belong to any variant attribute set related to the given family
     * code
     *
     * @param string $attributeCode
     * @param string $familyCode
     */
    private function assertAttributeMissingFromFamilyVariants(string $attributeCode, string $familyCode): void
    {
        $this->get('doctrine.orm.default_entity_manager')->clear();
        $family = $this->testKernel->getContainer()->get('pim_catalog.repository.family')
            ->findOneByIdentifier($familyCode);
        foreach ($family->getFamilyVariants() as $familyVariant) {
            $familyVariantAttributeCodes = $this->getVariantFamilyAttributeCodes($familyVariant);
            $isAttributeFound = array_search($attributeCode, $familyVariantAttributeCodes);
            $this->assertFalse(
                $isAttributeFound,
                sprintf(
                    'Attribute "%s" found in an attribute set of the family variant "%s"',
                    $attributeCode,
                    $familyVariant->getCode()
                )
            );
        }
    }

    /**
     * @param $familyVariant
     *
     * @return array
     */
    private function getVariantFamilyAttributeCodes(FamilyVariantInterface $familyVariant): array
    {
        return $familyVariant->getAttributes()->map(function (AttributeInterface $attribute) {
            return $attribute->getCode();
        })->toArray();
    }
}

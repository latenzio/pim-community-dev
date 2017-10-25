<?php

declare(strict_types=1);

namespace tests\integration\Pim\Bundle\CatalogBundle\EventSubscriber;

use Akeneo\Test\Integration\Configuration;
use Akeneo\Test\Integration\TestCase;
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
        $errors = $this->removeAttributeFromFamily('shoes', 'material');
        $this->assertEquals(0, $errors->count());
    }

    /**
     * @expectedException  \InvalidArgumentException
     * @expectedExceptionMessage Attribute "eu_shoes_size" is used as axis in at least one family variant. It cannot be removed from family.
     */
    public function testRemovingAxisAttributeOfAFamilyVariantFromFamilyIsNotAllowed()
    {
        $errors = $this->removeAttributeFromFamily('shoes', 'size');
        $this->assertEquals(1, $errors->count());
        $error = $errors->getIterator()->current();
        $this->assertEquals(FamilyAttributeUsedAsAxis::class, get_class($error->getConstraint()));
    }

    /**
     * @return Configuration
     */
    protected function getConfiguration()
    {
        return $this->catalog->useFunctionalCatalog('catalog_modeling');
    }

    /**
     * @param string $familyCode
     * @param string $attributeCode
     */
    private function removeAttributeFromFamily(string $familyCode, string $attributeCode)
    : ConstraintViolationListInterface {
        $family = $this->get('pim_catalog.repository.family')->findOneByIdentifier($familyCode);
        $attribute = $this->get('pim_catalog.repository.attribute')->findOneByIdentifier($attributeCode);
        $family->removeAttribute($attribute);
        $errors = $this->get('validator')->validate($family);

        if (count($errors) > 0) {
            $this->get('pim_catalog.saver.family')->save($family);
        }

        return $errors;
    }
}

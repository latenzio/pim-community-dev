<?php

declare(strict_types=1);

namespace tests\integration\Pim\Bundle\CatalogBundle\EventSubscriber;

use Akeneo\Test\Integration\Configuration;
use Akeneo\Test\Integration\TestCase;

/**
 * @author    Samir Boulil <samir.boulil@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class UpdateFamilyIntegration extends TestCase
{
    public function testRemovingSimpleAttributeFromFamilyIsAllowed()
    {
        $hasThrown = false;
        try {
            $this->removeAttributeFromFamily('shoes', 'material');
        } catch (\Exception $e) {
            $hasThrown = true;
        }

        $this->assertFalse($hasThrown);
    }

    /**
     * @expectedException  \InvalidArgumentException
     * @expectedExceptionMessage Attribute "eu_shoes_size" is used as axis in at least one family variant. It cannot be removed from family.
     */
    public function testRemovingAxisAttributeOfAFamilyVariantFromFamilyIsNotAllowed()
    {
        $this->removeAttributeFromFamily('shoes', 'eu_shoes_size');
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
    {
        $family = $this->get('pim_catalog.repository.family')->findOneByIdentifier($familyCode);
        $attribute = $this->get('pim_catalog.repository.attribute')->findOneByIdentifier($attributeCode);
        $family->removeAttribute($attribute);
        $this->get('validator')->validate($family);
        $this->get('pim_catalog.saver.family')->save($family);
    }
}

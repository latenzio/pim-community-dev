<?php

namespace Pim\Bundle\EnrichBundle\Normalizer;

use Pim\Bundle\CatalogBundle\Filter\CollectionFilterInterface;
use Pim\Bundle\VersioningBundle\Manager\VersionManager;
use Pim\Component\Catalog\Model\FamilyInterface;
use Pim\Component\Catalog\Model\FamilyVariantInterface;
use Pim\Component\Catalog\Repository\AttributeRepositoryInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Class FamilyNormalizer
 *
 * @author Alexandr Jeliuc <alex@jeliuc.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class FamilyNormalizer implements NormalizerInterface
{
    /** @var array */
    protected $supportedFormats = ['internal_api'];

    /** @var NormalizerInterface */
    protected $familyNormalizer;

    /** @var NormalizerInterface */
    protected $attributeNormalizer;

    /** @var CollectionFilterInterface */
    protected $collectionFilter;

    /** @var AttributeRepositoryInterface */
    protected $attributeRepository;

    /** @var VersionManager */
    protected $versionManager;

    /** @var NormalizerInterface */
    protected $versionNormalizer;

    /**
     * @param NormalizerInterface          $familyNormalizer
     * @param NormalizerInterface          $attributeNormalizer
     * @param CollectionFilterInterface    $collectionFilter
     * @param AttributeRepositoryInterface $attributeRepository
     * @param VersionManager               $versionManager
     * @param NormalizerInterface          $versionNormalizer
     */
    public function __construct(
        NormalizerInterface $familyNormalizer,
        NormalizerInterface $attributeNormalizer,
        CollectionFilterInterface $collectionFilter,
        AttributeRepositoryInterface $attributeRepository,
        VersionManager $versionManager,
        NormalizerInterface $versionNormalizer
    ) {
        $this->familyNormalizer = $familyNormalizer;
        $this->attributeNormalizer = $attributeNormalizer;
        $this->collectionFilter = $collectionFilter;
        $this->attributeRepository = $attributeRepository;
        $this->versionManager = $versionManager;
        $this->versionNormalizer = $versionNormalizer;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($family, $format = null, array $context = array())
    {
        $fullAttributes = array_key_exists('full_attributes', $context)
            && true === $context['full_attributes'];

        $normalizedFamily = $this->familyNormalizer->normalize(
            $family,
            'standard',
            $context
        );

        $normalizedFamily['attributes'] = $this->normalizeAttributes($family, $fullAttributes, $context);

        $normalizedFamily['attribute_requirements'] = $this->normalizeRequirements(
            $normalizedFamily['attribute_requirements'],
            $fullAttributes
        );
        $normalizedFamily['attributes_used_as_axis'] = $this->getAllAttributeCodesUsedAsAxis($family);

        $firstVersion = $this->versionManager->getOldestLogEntry($family);
        $lastVersion = $this->versionManager->getNewestLogEntry($family);

        $created = null === $firstVersion ? null :
            $this->versionNormalizer->normalize($firstVersion, 'internal_api', $context);
        $updated = null === $lastVersion ? null :
            $this->versionNormalizer->normalize($lastVersion, 'internal_api', $context);

        $normalizedFamily['meta'] = [
            'id'      => $family->getId(),
            'form'    => 'pim-family-edit-form',
            'created' => $created,
            'updated' => $updated,
        ];

        return $normalizedFamily;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($family, $format = null)
    {
        return $family instanceof FamilyInterface &&
            in_array($format, $this->supportedFormats);
    }

    /**
     * Fetches attributes by code and normalizes them
     *
     * @param FamilyInterface $family
     * @param boolean         $fullAttributes
     * @param array           $context
     *
     * @return array
     */
    protected function normalizeAttributes(FamilyInterface $family, $fullAttributes, $context)
    {
        $attributes = $this->attributeRepository->findAttributesByFamily($family);

        if ($fullAttributes) {
            $attributes = $this->collectionFilter->filterCollection(
                $attributes,
                'pim.internal_api.attribute.view'
            );
        }

        $normalizedAttributes = [];
        foreach ($attributes as $attribute) {
            $normalizedAttributes[] = $fullAttributes ?
                ['code' => $attribute->getCode()] :
                $this->attributeNormalizer->normalize($attribute, 'internal_api', $context);
        }

        return $normalizedAttributes;
    }

    /**
     * Normalize the requirements
     *
     * It filters the requirements to the viewable ones
     *
     * @param array $requirements
     * @param bool  $fullAttributes
     *
     * @return array
     */
    protected function normalizeRequirements($requirements, $fullAttributes)
    {
        $result = [];

        foreach ($requirements as $channel => $attributeCodes) {
            $attributes = $this->attributeRepository->findBy(['code' => $attributeCodes]);

            if ($fullAttributes) {
                $attributes = $this->collectionFilter->filterCollection(
                    $attributes,
                    'pim.internal_api.attribute.view'
                );
            }

            $result[$channel] = array_map(function ($attribute) {
                return $attribute->getCode();
            }, $attributes);
        }

        return $result;
    }

    /**
     * Returns a list of attributes used as axis in the family variants.
     *
     * @param FamilyInterface $family
     *
     * @return string[]
     */
    private function getAllAttributeCodesUsedAsAxis(FamilyInterface $family): array
    {
        $attributesUsedAsAxis = [];
        foreach ($family->getFamilyVariants() as $familyVariant) {
            $attributesAxisCodes = $this->getAttributeAxisCodesForFamilyVariant($familyVariant);
            $attributesUsedAsAxis = array_replace($attributesUsedAsAxis, $attributesAxisCodes);
        }

        return $attributesUsedAsAxis;
    }

    /**
     * Returns the attributes codes of the given family variant axes.
     *
     * @param FamilyVariantInterface $familyVariant
     *
     * @return string[]
     */
    private function getAttributeAxisCodesForFamilyVariant(FamilyVariantInterface $familyVariant): array
    {
        $attributesAxisCodes = array_map(function ($attribute) {
            return $attribute->getCode();
        }, $familyVariant->getAxes()->toArray());

        return $attributesAxisCodes;
    }
}

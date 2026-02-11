<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\ConsistencyCheck\ImageManager\Repository;

use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Result;
use OxidEsales\ConsistencyCheck\ImageManager\Dto\ImageCollectionInterface;
use OxidEsales\ConsistencyCheck\ImageManager\Entity\MediaImageEntityInterface;
use OxidEsales\ConsistencyCheck\ImageManager\Exception\ImageDatabaseRepositoryException;
use OxidEsales\ConsistencyCheck\ImageManager\Factory\ImageCollectionFactoryInterface;
use OxidEsales\ConsistencyCheck\ImageManager\Factory\ImageDataTypeFactoryInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Database\QueryBuilderFactoryInterface;

/**
 * Repository for fetching images from OXID 8.0 media tables (oxmedia, oxproduct_media).
 */
class MediaImageDatabaseRepository implements MediaImageRepositoryInterface
{
    private const MEDIA_DIRECTORY = '/out/pictures/ddmedia';

    public function __construct(
        private readonly QueryBuilderFactoryInterface $queryBuilderFactory,
        private readonly ImageDataTypeFactoryInterface $imageDataTypeFactory,
        private readonly ImageCollectionFactoryInterface $imageCollectionFactory,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getImages(MediaImageEntityInterface $entity): ImageCollectionInterface
    {
        try {
            $queryBuilder = $this->queryBuilderFactory->create();

            // Build query based on media type
            // OXID 8.0: oxmedia uses lowercase column names: id, path, type
            // OXID 8.0: oxproduct_media uses: id, product_id, media_id, position
            if ($entity->getMediaType() === 'product') {
                $queryBuilder
                    ->select('DISTINCT m.path')
                    ->from('oxmedia', 'm')
                    ->innerJoin('m', 'oxproduct_media', 'pm', 'm.id = pm.media_id')
                    ->where('m.path IS NOT NULL')
                    ->andWhere("m.path != ''");
            } else {
                $queryBuilder
                    ->select('DISTINCT path')
                    ->from('oxmedia')
                    ->where('path IS NOT NULL')
                    ->andWhere("path != ''");
            }

            /** @var Result $result */
            $result = $queryBuilder->executeQuery();

            $imageCollection = $this->imageCollectionFactory->create();
            while ($data = $result->fetchAssociative()) {
                if (empty($data['path'])) {
                    continue;
                }
                $imageCollection->add(
                    $this->imageDataTypeFactory->createFromFileDetails(
                        fieldName: $entity->getMediaType(),
                        imageName: $data['path'],
                        directory: self::MEDIA_DIRECTORY,
                    )
                );
            }

            return $imageCollection;
        } catch (DBALException) {
            throw new ImageDatabaseRepositoryException('oxmedia', $entity->getMediaType());
        }
    }
}

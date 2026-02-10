<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\ConsistencyCheck\ImageManager\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use OxidEsales\ConsistencyCheck\ImageManager\Dto\ImageCollectionInterface;
use OxidEsales\ConsistencyCheck\ImageManager\Entity\MediaImageEntityInterface;
use OxidEsales\ConsistencyCheck\ImageManager\Exception\ImageDatabaseRepositoryException;
use OxidEsales\ConsistencyCheck\ImageManager\Factory\ImageCollectionFactoryInterface;
use OxidEsales\ConsistencyCheck\ImageManager\Factory\ImageDataTypeFactoryInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Database\ConnectionProviderInterface;

/**
 * Repository for fetching images from OXID 8.0 media tables (oxmedia, oxproduct_media).
 */
class MediaImageDatabaseRepository implements MediaImageRepositoryInterface
{
    private const MEDIA_DIRECTORY = '/out/pictures/ddmedia';

    public function __construct(
        private readonly ConnectionProviderInterface $connectionProvider,
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
            $connection = $this->connectionProvider->get();
            $result = $connection->executeQuery($this->getQueryForMediaType($entity->getMediaType()));

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

    /**
     * Get the SQL query for a specific media type.
     */
    private function getQueryForMediaType(string $mediaType): string
    {
        return match ($mediaType) {
            'product' => <<<SQL
                SELECT DISTINCT m.OXFILENAME as path
                FROM oxmedia m
                INNER JOIN oxproduct_media pm ON m.OXID = pm.OXMEDIAID
                WHERE m.OXFILENAME IS NOT NULL AND m.OXFILENAME != ''
                SQL,
            default => <<<SQL
                SELECT DISTINCT OXFILENAME as path
                FROM oxmedia
                WHERE OXFILENAME IS NOT NULL AND OXFILENAME != ''
                SQL,
        };
    }
}

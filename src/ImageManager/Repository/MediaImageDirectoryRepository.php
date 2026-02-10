<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\ConsistencyCheck\ImageManager\Repository;

use OxidEsales\ConsistencyCheck\ImageManager\Dto\ImageCollectionInterface;
use OxidEsales\ConsistencyCheck\ImageManager\Entity\MediaImageEntityInterface;
use OxidEsales\ConsistencyCheck\ImageManager\Factory\ImageCollectionFactoryInterface;
use OxidEsales\ConsistencyCheck\ImageManager\Factory\ImageDataTypeFactoryInterface;
use OxidEsales\ConsistencyCheck\ImageManager\Utils\FileSystemUtilsInterface;

/**
 * Repository for reading media images from filesystem (OXID 8.0 media directory).
 */
class MediaImageDirectoryRepository implements MediaImageRepositoryInterface
{
    private const MEDIA_DIRECTORY = '/out/pictures/ddmedia';

    public function __construct(
        private readonly FileSystemUtilsInterface $fileSystemUtils,
        private readonly ImageDataTypeFactoryInterface $imageFactory,
        private readonly ImageCollectionFactoryInterface $imageCollectionFactory,
    ) {
    }

    public function getImages(MediaImageEntityInterface $entity): ImageCollectionInterface
    {
        $directoryPath = self::MEDIA_DIRECTORY;
        $imageCollection = $this->imageCollectionFactory->create();

        if (!$this->fileSystemUtils->directoryExists($directoryPath)) {
            return $imageCollection;
        }

        $files = $this->fileSystemUtils->getFilesInDirectory($directoryPath);

        foreach ($files as $fileName) {
            $imageCollection->add(
                $this->imageFactory->createFromFileDetails(
                    $entity->getMediaType(),
                    $fileName,
                    $directoryPath,
                )
            );
        }

        return $imageCollection;
    }
}

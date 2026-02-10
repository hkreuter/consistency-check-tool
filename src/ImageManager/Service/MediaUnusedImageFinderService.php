<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\ConsistencyCheck\ImageManager\Service;

use OxidEsales\ConsistencyCheck\ImageManager\Dto\ImageCollectionInterface;
use OxidEsales\ConsistencyCheck\ImageManager\Entity\MediaImageEntityInterface;
use OxidEsales\ConsistencyCheck\ImageManager\Factory\ImageCollectionFactoryInterface;
use OxidEsales\ConsistencyCheck\ImageManager\Repository\MediaImageRepositoryInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;

/**
 * Service for finding unused media images (OXID 8.0 media system).
 */
class MediaUnusedImageFinderService implements MediaUnusedImageFinderServiceInterface
{
    public function __construct(
        private readonly MediaImageRepositoryInterface $mediaDatabaseRepository,
        private readonly MediaImageRepositoryInterface $mediaDirectoryRepository,
        private readonly ImageCollectionFactoryInterface $imageCollectionFactory,
        private readonly ImageUsageCheckerInterface $imageUsageChecker,
        private readonly PsrLoggerInterface $logger,
    ) {
    }

    public function getUnusedImages(MediaImageEntityInterface $entity): ImageCollectionInterface
    {
        try {
            $usedImageCollection = $this->mediaDatabaseRepository->getImages($entity);
            $allImageCollection = $this->mediaDirectoryRepository->getImages($entity);

            $unusedImages = $this->imageCollectionFactory->create();

            foreach ($allImageCollection->getAll() as $image) {
                if (!$this->imageUsageChecker->isUsed($image, $usedImageCollection)) {
                    $unusedImages->add($image);
                }
            }

            return $unusedImages;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Error processing media entity %s (type: %s): %s',
                $entity->getName(),
                $entity->getMediaType(),
                $e->getMessage()
            ));

            return $this->imageCollectionFactory->create();
        }
    }
}

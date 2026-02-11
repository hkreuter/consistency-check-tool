<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace ImageManager\Repository;

use OxidEsales\ConsistencyCheck\ImageManager\Dto\ImageCollectionInterface;
use OxidEsales\ConsistencyCheck\ImageManager\DataType\ImageDataTypeInterface;
use OxidEsales\ConsistencyCheck\ImageManager\Entity\MediaImageEntity;
use OxidEsales\ConsistencyCheck\ImageManager\Entity\MediaImageEntityInterface;
use OxidEsales\ConsistencyCheck\ImageManager\Exception\ImageDatabaseRepositoryException;
use OxidEsales\ConsistencyCheck\ImageManager\Factory\ImageCollectionFactoryInterface;
use OxidEsales\ConsistencyCheck\ImageManager\Factory\ImageDataTypeFactoryInterface;
use OxidEsales\ConsistencyCheck\ImageManager\Repository\MediaImageDatabaseRepository;
use OxidEsales\ConsistencyCheck\ImageManager\Repository\MediaImageRepositoryInterface;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;
use OxidEsales\EshopCommunity\Internal\Framework\Database\ConnectionProviderInterface;
use OxidEsales\EshopCommunity\Tests\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

class MediaImageDatabaseRepositoryTest extends IntegrationTestCase
{
    #[Test]
    public function itReturnsAllMediaImagesAsImageDataTypeObjects(): void
    {
        $image1 = uniqid() . '.jpg';
        $image2 = uniqid() . '.png';
        $productId = uniqid();

        $connectionProvider = ContainerFacade::get(ConnectionProviderInterface::class);

        // Insert media records and link them to a product
        $mediaId1 = $this->insertMediaRecord($connectionProvider, $image1);
        $mediaId2 = $this->insertMediaRecord($connectionProvider, $image2);
        $this->insertProductMediaRecord($connectionProvider, $productId, $mediaId1, 1);
        $this->insertProductMediaRecord($connectionProvider, $productId, $mediaId2, 2);

        // Also insert a media record not linked to any product (should still be found)
        $image3 = uniqid() . '.gif';
        $this->insertMediaRecord($connectionProvider, $image3);

        $entity = new MediaImageEntity('ProductMedia', 'product');

        $image1Stub = $this->createStub(ImageDataTypeInterface::class);
        $image2Stub = $this->createStub(ImageDataTypeInterface::class);
        $imageDataTypeFactoryMock = $this->createMock(ImageDataTypeFactoryInterface::class);
        $imageDataTypeFactoryMock
            ->method('createFromFileDetails')
            ->willReturnCallback(function ($fieldName, $imageName, $directory) use ($image1, $image2, $image1Stub, $image2Stub) {
                if ($imageName === $image1) {
                    return $image1Stub;
                }
                if ($imageName === $image2) {
                    return $image2Stub;
                }
                return $this->createStub(ImageDataTypeInterface::class);
            });

        $imageCollectionMock = $this->createMock(ImageCollectionInterface::class);
        $imageCollectionMock->expects($this->exactly(2))
            ->method('add');

        $imageCollectionFactoryStub = $this->createStub(ImageCollectionFactoryInterface::class);
        $imageCollectionFactoryStub->method('create')->willReturn($imageCollectionMock);

        $sut = $this->getSut(
            connectionProvider: $connectionProvider,
            imageDataTypeFactory: $imageDataTypeFactoryMock,
            imageCollectionFactory: $imageCollectionFactoryStub,
        );

        $images = $sut->getImages(entity: $entity);

        $this->assertSame($imageCollectionMock, $images);
    }

    #[Test]
    public function getImagesReturnsEmptyCollectionWhenNoMediaLinkedToProducts(): void
    {
        $entity = new MediaImageEntity('ProductMedia', 'product');

        $imageCollectionStub = $this->createStub(ImageCollectionInterface::class);

        $imageCollectionFactoryStub = $this->createStub(ImageCollectionFactoryInterface::class);
        $imageCollectionFactoryStub->method('create')->willReturn($imageCollectionStub);

        $sut = $this->getSut(imageCollectionFactory: $imageCollectionFactoryStub);

        $result = $sut->getImages($entity);

        $this->assertSame($imageCollectionStub, $result);
    }

    #[Test]
    public function itSkipsEmptyFilenames(): void
    {
        $connectionProvider = ContainerFacade::get(ConnectionProviderInterface::class);
        $productId = uniqid();

        // Insert media with empty filename
        $mediaId = $this->insertMediaRecord($connectionProvider, '');
        $this->insertProductMediaRecord($connectionProvider, $productId, $mediaId, 1);

        $entity = new MediaImageEntity('ProductMedia', 'product');

        $imageCollectionMock = $this->createMock(ImageCollectionInterface::class);
        $imageCollectionMock->expects($this->never())->method('add');

        $imageCollectionFactoryStub = $this->createStub(ImageCollectionFactoryInterface::class);
        $imageCollectionFactoryStub->method('create')->willReturn($imageCollectionMock);

        $sut = $this->getSut(
            connectionProvider: $connectionProvider,
            imageCollectionFactory: $imageCollectionFactoryStub,
        );

        $sut->getImages($entity);
    }

    private function insertMediaRecord(
        ConnectionProviderInterface $connectionProvider,
        string $filename
    ): string {
        $mediaId = uniqid();
        $connection = $connectionProvider->get();

        $connection->executeStatement(
            'INSERT INTO oxmedia (OXID, OXFILENAME) VALUES (:id, :filename)',
            ['id' => $mediaId, 'filename' => $filename]
        );

        return $mediaId;
    }

    private function insertProductMediaRecord(
        ConnectionProviderInterface $connectionProvider,
        string $productId,
        string $mediaId,
        int $sort
    ): void {
        $connection = $connectionProvider->get();

        $connection->executeStatement(
            'INSERT INTO oxproduct_media (OXID, OXARTICLEID, OXMEDIAID, OXSORT) VALUES (:id, :productId, :mediaId, :sort)',
            [
                'id' => uniqid(),
                'productId' => $productId,
                'mediaId' => $mediaId,
                'sort' => $sort,
            ]
        );
    }

    private function getSut(
        ?ConnectionProviderInterface $connectionProvider = null,
        ?ImageDataTypeFactoryInterface $imageDataTypeFactory = null,
        ?ImageCollectionFactoryInterface $imageCollectionFactory = null
    ): MediaImageRepositoryInterface {
        $connectionProvider ??= ContainerFacade::get(ConnectionProviderInterface::class);
        $imageDataTypeFactory ??= $this->createStub(ImageDataTypeFactoryInterface::class);
        $imageCollectionFactory ??= $this->createStub(ImageCollectionFactoryInterface::class);

        return new MediaImageDatabaseRepository(
            connectionProvider: $connectionProvider,
            imageDataTypeFactory: $imageDataTypeFactory,
            imageCollectionFactory: $imageCollectionFactory
        );
    }
}

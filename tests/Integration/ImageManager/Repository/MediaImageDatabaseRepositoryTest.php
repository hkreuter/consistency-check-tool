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
use OxidEsales\EshopCommunity\Internal\Framework\Database\QueryBuilderFactoryInterface;
use OxidEsales\EshopCommunity\Tests\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

class MediaImageDatabaseRepositoryTest extends IntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->cleanupMediaTables();
    }

    private function cleanupMediaTables(): void
    {
        $queryBuilderFactory = ContainerFacade::get(QueryBuilderFactoryInterface::class);

        // Clean oxproduct_media first due to foreign key references
        $queryBuilder = $queryBuilderFactory->create();
        $queryBuilder->delete('oxproduct_media')->executeStatement();

        // Then clean oxmedia
        $queryBuilder = $queryBuilderFactory->create();
        $queryBuilder->delete('oxmedia')->executeStatement();
    }

    #[Test]
    public function itReturnsAllMediaImagesAsImageDataTypeObjects(): void
    {
        $image1 = uniqid() . '.jpg';
        $image2 = uniqid() . '.png';
        $productId = uniqid();

        $queryBuilderFactory = ContainerFacade::get(QueryBuilderFactoryInterface::class);

        // Insert media records and link them to a product
        $mediaId1 = $this->insertMediaRecord($queryBuilderFactory, $image1);
        $mediaId2 = $this->insertMediaRecord($queryBuilderFactory, $image2);
        $this->insertProductMediaRecord($queryBuilderFactory, $productId, $mediaId1, 1);
        $this->insertProductMediaRecord($queryBuilderFactory, $productId, $mediaId2, 2);

        // Also insert a media record not linked to any product (should still be found)
        $image3 = uniqid() . '.gif';
        $this->insertMediaRecord($queryBuilderFactory, $image3);

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
            queryBuilderFactory: $queryBuilderFactory,
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
        $queryBuilderFactory = ContainerFacade::get(QueryBuilderFactoryInterface::class);
        $productId = uniqid();

        // Insert media with empty filename
        $mediaId = $this->insertMediaRecord($queryBuilderFactory, '');
        $this->insertProductMediaRecord($queryBuilderFactory, $productId, $mediaId, 1);

        $entity = new MediaImageEntity('ProductMedia', 'product');

        $imageCollectionMock = $this->createMock(ImageCollectionInterface::class);
        $imageCollectionMock->expects($this->never())->method('add');

        $imageCollectionFactoryStub = $this->createStub(ImageCollectionFactoryInterface::class);
        $imageCollectionFactoryStub->method('create')->willReturn($imageCollectionMock);

        $sut = $this->getSut(
            queryBuilderFactory: $queryBuilderFactory,
            imageCollectionFactory: $imageCollectionFactoryStub,
        );

        $sut->getImages($entity);
    }

    private function insertMediaRecord(
        QueryBuilderFactoryInterface $queryBuilderFactory,
        string $filename
    ): string {
        $mediaId = uniqid();
        $queryBuilder = $queryBuilderFactory->create();

        // OXID 8.0: oxmedia uses lowercase columns: id, path, type
        $queryBuilder->insert('oxmedia')
            ->setValue('id', ':id')
            ->setValue('path', ':path')
            ->setValue('type', ':type')
            ->setParameter('id', $mediaId)
            ->setParameter('path', $filename)
            ->setParameter('type', 'image')
            ->executeStatement();

        return $mediaId;
    }

    private function insertProductMediaRecord(
        QueryBuilderFactoryInterface $queryBuilderFactory,
        string $productId,
        string $mediaId,
        int $position
    ): void {
        $queryBuilder = $queryBuilderFactory->create();

        // OXID 8.0: oxproduct_media uses: id, product_id, media_id, position
        $queryBuilder->insert('oxproduct_media')
            ->setValue('id', ':id')
            ->setValue('product_id', ':productId')
            ->setValue('media_id', ':mediaId')
            ->setValue('position', ':position')
            ->setParameter('id', uniqid())
            ->setParameter('productId', $productId)
            ->setParameter('mediaId', $mediaId)
            ->setParameter('position', $position)
            ->executeStatement();
    }

    private function getSut(
        ?QueryBuilderFactoryInterface $queryBuilderFactory = null,
        ?ImageDataTypeFactoryInterface $imageDataTypeFactory = null,
        ?ImageCollectionFactoryInterface $imageCollectionFactory = null
    ): MediaImageRepositoryInterface {
        $queryBuilderFactory ??= ContainerFacade::get(QueryBuilderFactoryInterface::class);
        $imageDataTypeFactory ??= $this->createStub(ImageDataTypeFactoryInterface::class);
        $imageCollectionFactory ??= $this->createStub(ImageCollectionFactoryInterface::class);

        return new MediaImageDatabaseRepository(
            queryBuilderFactory: $queryBuilderFactory,
            imageDataTypeFactory: $imageDataTypeFactory,
            imageCollectionFactory: $imageCollectionFactory
        );
    }
}

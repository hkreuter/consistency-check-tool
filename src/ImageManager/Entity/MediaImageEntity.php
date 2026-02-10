<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\ConsistencyCheck\ImageManager\Entity;

/**
 * Media-based image entity for OXID 8.0
 *
 * Queries images from oxmedia/oxproduct_media tables instead of legacy
 * oxarticles.OXPIC1-12 fields.
 */
class MediaImageEntity implements MediaImageEntityInterface
{
    public function __construct(
        private readonly string $name,
        private readonly string $mediaType,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMediaType(): string
    {
        return $this->mediaType;
    }

    /**
     * Get the query for fetching images from media tables.
     */
    public function getQuery(): string
    {
        return <<<SQL
            SELECT m.OXFILENAME as path, pm.OXSORT as position
            FROM oxmedia m
            JOIN oxproduct_media pm ON m.OXID = pm.OXMEDIAID
            WHERE pm.OXARTICLEID = :productId
            ORDER BY pm.OXSORT
            SQL;
    }
}

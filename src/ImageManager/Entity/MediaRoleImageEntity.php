<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\ConsistencyCheck\ImageManager\Entity;

/**
 * Media role-based image entity for OXID 8.0 (thumbnail, icon)
 *
 * Queries images from oxmedia/oxproduct_media/oxproduct_media_roles tables
 * instead of legacy oxarticles.OXTHUMB or OXICON fields.
 */
class MediaRoleImageEntity implements MediaImageEntityInterface
{
    public function __construct(
        private readonly string $name,
        private readonly string $mediaType,
        private readonly string $role,
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

    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * Get the query for fetching role-specific images from media tables.
     */
    public function getQuery(): string
    {
        return <<<SQL
            SELECT m.OXFILENAME as path
            FROM oxmedia m
            JOIN oxproduct_media pm ON m.OXID = pm.OXMEDIAID
            JOIN oxproduct_media_roles pmr ON pm.OXID = pmr.OXPRODUCTMEDIAID
            WHERE pm.OXARTICLEID = :productId AND pmr.OXROLE = :role
            SQL;
    }
}

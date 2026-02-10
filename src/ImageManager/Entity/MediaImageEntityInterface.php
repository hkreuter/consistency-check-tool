<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidEsales\ConsistencyCheck\ImageManager\Entity;

interface MediaImageEntityInterface
{
    public function getName(): string;
    public function getMediaType(): string;
    public function getQuery(): string;
}

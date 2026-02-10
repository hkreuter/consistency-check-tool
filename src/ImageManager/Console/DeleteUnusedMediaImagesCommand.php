<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\ConsistencyCheck\ImageManager\Console;

use OxidEsales\ConsistencyCheck\ImageManager\Dto\ImageCollectionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Command to delete unused media images (OXID 8.0).
 */
class DeleteUnusedMediaImagesCommand extends AbstractUnusedMediaImagesCommand
{
    protected static $defaultName = 'oe:consistency_check:delete-unused-media-images';

    protected const MESSAGE_DELETED_IMAGES = 'Deleted %d media images for entity %s';
    protected const MESSAGE_COMPLETION = 'Unused media image deletion operation completed.';

    private const COMMAND_DESCRIPTION = 'Deletes unused media images (OXID 8.0).';
    private const COMMAND_OPTION_DRY_RUN = 'Perform a dry run without actual file deletions';

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setDescription(self::COMMAND_DESCRIPTION)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, self::COMMAND_OPTION_DRY_RUN);
    }

    protected function processImages(ImageCollectionInterface $unusedImages, InputInterface $input): int
    {
        return $this->imageManagerService->deleteImages($unusedImages, $input->getOption('dry-run'));
    }

    protected function getMessageProcessedImages(): string
    {
        return self::MESSAGE_DELETED_IMAGES;
    }

    protected function getMessageCompletion(): string
    {
        return self::MESSAGE_COMPLETION;
    }
}

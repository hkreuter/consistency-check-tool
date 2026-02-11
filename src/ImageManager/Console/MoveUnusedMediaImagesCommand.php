<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\ConsistencyCheck\ImageManager\Console;

use OxidEsales\ConsistencyCheck\ImageManager\Dto\ImageCollectionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to move unused media images (OXID 8.0).
 */
#[AsCommand(
    name: 'oe:consistency_check:move-unused-media-images',
    description: 'Moves unused media images to a new destination (OXID 8.0).'
)]
class MoveUnusedMediaImagesCommand extends AbstractUnusedMediaImagesCommand
{
    protected const MESSAGE_MOVED_IMAGES = 'Moved %d media images for entity %s';
    protected const MESSAGE_COMPLETION = 'Unused media image move operation completed.';
    protected const ERROR_DESTINATION_REQUIRED = 'Error: The --destination option is required.';

    private const COMMAND_DESCRIPTION = 'Moves unused media images to a new destination (OXID 8.0).';
    private const COMMAND_OPTION_DESTINATION = 'Destination path (required)';
    private const COMMAND_OPTION_DRY_RUN = 'Simulate the move operation without making changes';

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setDescription(self::COMMAND_DESCRIPTION)
            ->addOption('destination', null, InputOption::VALUE_REQUIRED, self::COMMAND_OPTION_DESTINATION)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, self::COMMAND_OPTION_DRY_RUN);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $destination = $input->getOption('destination');
        if (!$destination) {
            $output->writeln($this->messageFormatter->formatError(self::ERROR_DESTINATION_REQUIRED));
            return Command::INVALID;
        }

        return parent::execute($input, $output);
    }

    protected function processImages(ImageCollectionInterface $unusedImages, InputInterface $input): int
    {
        return $this->imageManagerService->moveImages(
            $unusedImages,
            $input->getOption('destination'),
            $input->getOption('dry-run')
        );
    }

    protected function getMessageProcessedImages(): string
    {
        return self::MESSAGE_MOVED_IMAGES;
    }

    protected function getMessageCompletion(): string
    {
        return self::MESSAGE_COMPLETION;
    }
}

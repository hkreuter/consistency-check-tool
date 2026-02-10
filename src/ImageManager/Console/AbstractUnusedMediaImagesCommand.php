<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\ConsistencyCheck\ImageManager\Console;

use Exception;
use OxidEsales\ConsistencyCheck\ImageManager\Dto\ImageCollectionInterface;
use OxidEsales\ConsistencyCheck\ImageManager\Entity\MediaImageEntityInterface;
use OxidEsales\ConsistencyCheck\ImageManager\Factory\ProgressBarFactoryInterface;
use OxidEsales\ConsistencyCheck\ImageManager\Service\PostCommandLoggerInterface;
use OxidEsales\ConsistencyCheck\ImageManager\Service\MediaUnusedImageFinderServiceInterface;
use OxidEsales\ConsistencyCheck\ImageManager\Service\ImageManagerServiceInterface;
use OxidEsales\ConsistencyCheck\Shared\Service\MessageFormatterServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base command for processing unused media images (OXID 8.0).
 */
abstract class AbstractUnusedMediaImagesCommand extends Command
{
    protected const MESSAGE_PROCESSING = 'Processing unused media images for entity: %s';
    protected const MESSAGE_NO_IMAGES = 'No unused media images found for entity %s';
    protected const MESSAGE_ERROR = 'Error processing media entity %s: %s';
    protected const MESSAGE_ENTITY_EXCEPTION = 'Error processing media entity %s: Check error log for details';

    public function __construct(
        /** @var MediaImageEntityInterface[] */
        protected readonly iterable $mediaEntities,
        protected readonly MediaUnusedImageFinderServiceInterface $mediaImageCheckerService,
        protected readonly ImageManagerServiceInterface $imageManagerService,
        protected readonly MessageFormatterServiceInterface $messageFormatter,
        protected readonly ProgressBarFactoryInterface $progressBarFactory,
        protected readonly LoggerInterface $logger,
        protected readonly PostCommandLoggerInterface $postCommandLogger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'type',
            't',
            InputOption::VALUE_OPTIONAL,
            'Filter by media type (e.g., "product")',
            null
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = $input->getOption('type');
        $filteredEntities = $this->filterEntitiesByType($this->mediaEntities, $type);

        $entitiesArray = iterator_to_array($filteredEntities);
        $progressBar = $this->progressBarFactory->create($output, count($entitiesArray));
        $progressBar->start();

        foreach ($entitiesArray as $entity) {
            try {
                $this->processEntity($entity, $input, $output);
            } catch (Exception $exception) {
                $this->reportError($entity, $exception, $output);
            }
            $progressBar->advance();
        }

        $progressBar->finish();

        $this->postCommandLogger->after($output);

        $this->reportSuccess($output);

        return Command::SUCCESS;
    }

    /**
     * Filter entities by media type.
     *
     * @param iterable<MediaImageEntityInterface> $entities
     * @return iterable<MediaImageEntityInterface>
     */
    private function filterEntitiesByType(iterable $entities, ?string $type): iterable
    {
        if ($type === null) {
            return $entities;
        }

        $filtered = [];
        foreach ($entities as $entity) {
            if (stripos($entity->getMediaType(), $type) !== false) {
                $filtered[] = $entity;
            }
        }
        return $filtered;
    }

    private function processEntity(
        MediaImageEntityInterface $entity,
        InputInterface $input,
        OutputInterface $output
    ): void {
        $unusedImages = $this->mediaImageCheckerService->getUnusedImages($entity);

        if ($unusedImages->getAll()) {
            $this->reportProcessing($entity, $output);
            $affectedImagesCount = $this->processImages($unusedImages, $input);
            if ($affectedImagesCount > 0) {
                $this->reportProcessed($entity, $affectedImagesCount, $output);
            } else {
                $this->reportEntityException($entity, $output);
            }
        } else {
            $this->reportNoImages($entity, $output);
        }
    }

    abstract protected function processImages(ImageCollectionInterface $unusedImages, InputInterface $input): int;

    abstract protected function getMessageProcessedImages(): string;
    abstract protected function getMessageCompletion(): string;

    private function reportProcessing(MediaImageEntityInterface $entity, OutputInterface $output): void
    {
        $entityDetails = $this->getEntityDetailsString($entity);

        $output->writeln("\n" . $this->messageFormatter->formatInfo(self::MESSAGE_PROCESSING, $entityDetails));
        $this->logger->info(sprintf(self::MESSAGE_PROCESSING, $entityDetails));
    }

    private function reportProcessed(
        MediaImageEntityInterface $entity,
        int $affectedImagesCount,
        OutputInterface $output
    ): void {
        $message = $this->getMessageProcessedImages();
        $messageArgs = [$affectedImagesCount, $this->getEntityDetailsString($entity)];

        $output->writeln("\n" . $this->messageFormatter->formatInfo($message, ...$messageArgs));
        $this->logger->info(sprintf($message, ...$messageArgs));
    }

    private function reportNoImages(MediaImageEntityInterface $entity, OutputInterface $output): void
    {
        $entityDetails = $this->getEntityDetailsString($entity);

        $output->writeln("\n" . $this->messageFormatter->formatComment(self::MESSAGE_NO_IMAGES, $entityDetails));
        $this->logger->info(sprintf(self::MESSAGE_NO_IMAGES, $entityDetails));
    }

    private function reportError(
        MediaImageEntityInterface $entity,
        Exception $exception,
        OutputInterface $output
    ): void {
        $messageArgs = [$this->getEntityDetailsString($entity), $exception->getMessage()];

        $this->logger->error(sprintf(static::MESSAGE_ERROR, ...$messageArgs));
        $output->writeln($this->messageFormatter->formatError(static::MESSAGE_ERROR, ...$messageArgs));
    }

    private function reportSuccess(OutputInterface $output): void
    {
        $this->logger->info($this->getMessageCompletion());
        $output->writeln("\n" . $this->messageFormatter->formatInfo($this->getMessageCompletion()));
    }

    private function reportEntityException(MediaImageEntityInterface $entity, OutputInterface $output): void
    {
        $messageArgs = [$this->getEntityDetailsString($entity)];

        $this->logger->error(sprintf(static::MESSAGE_ENTITY_EXCEPTION, ...$messageArgs));
        $output->writeln("\n" . $this->messageFormatter->formatError(self::MESSAGE_ENTITY_EXCEPTION, ...$messageArgs));
    }

    private function getEntityDetailsString(MediaImageEntityInterface $entity): string
    {
        return sprintf('[%s:%s]', $entity->getName(), $entity->getMediaType());
    }
}

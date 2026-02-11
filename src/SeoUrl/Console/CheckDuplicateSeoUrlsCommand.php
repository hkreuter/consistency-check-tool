<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\ConsistencyCheck\SeoUrl\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'oe:consistency_check:check-duplicate-seo-urls',
    description: 'Check for duplicate SEO URLs with collision suffixes'
)]
final class CheckDuplicateSeoUrlsCommand extends AbstractCheckSeoUrlsCommand
{
    private const COMMAND_DESCRIPTION = 'Check for duplicate SEO URLs with collision suffixes';
    private const COMMAND_OPTION_SUFFIX = 'Custom suffix to search for (overrides shop config)';
    private const MESSAGE_NO_RESULTS = 'No duplicate SEO URLs found';
    private const MESSAGE_RESULTS = 'Found %d duplicate SEO URLs';

    protected function configure(): void
    {
        parent::configure();
        $this->addOption('suffix', null, InputOption::VALUE_REQUIRED, self::COMMAND_OPTION_SUFFIX);
    }

    protected function findAllUrls(InputInterface $input): array
    {
        $customSuffix = $input->getOption('suffix');
        return $this->service->findDuplicateUrls($customSuffix);
    }

    protected function getCommandDescription(): string
    {
        return self::COMMAND_DESCRIPTION;
    }

    protected function getNoResultsMessage(): string
    {
        return $this->messageFormatter->formatInfo(self::MESSAGE_NO_RESULTS);
    }

    protected function getResultsMessage(int $count): string
    {
        return $this->messageFormatter->formatComment(self::MESSAGE_RESULTS, $count);
    }
}

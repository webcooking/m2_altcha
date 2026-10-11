<?php

/**
 * Copyright (c) 2026 SAS WEB COOKING
 * @author Vincent Enjalbert <chef@web-cooking.net>
 * @license MIT (see LICENSE)
 */

declare(strict_types=1);

namespace Webcooking\Altcha\Console\Command;

use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Anti-lockout escape hatch: disables ALTCHA per form (or entirely) from the
 * CLI, e.g. `bin/magento altcha:disable admin_login` when locked out of the
 * admin login.
 */
class DisableAltchaCommand extends Command
{
    private const string ARG_FORMS = 'forms';
    private const string OPTION_ALL = 'all';

    /**
     * @param array<string, string> $forms Form key => system.xml group (see etc/di.xml)
     */
    public function __construct(
        private readonly WriterInterface $configWriter,
        private readonly TypeListInterface $cacheTypeList,
        private readonly StoreManagerInterface $storeManager,
        private readonly array $forms = []
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('altcha:disable')
            ->setDescription('Disable ALTCHA protection for the given forms (anti-lockout escape hatch)')
            ->addArgument(
                self::ARG_FORMS,
                InputArgument::IS_ARRAY,
                'Form keys to disable (e.g. admin_login customer_login)'
            )
            ->addOption(
                self::OPTION_ALL,
                null,
                InputOption::VALUE_NONE,
                'Disable the module master switch and every form'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string[] $requested */
        $requested = (array)$input->getArgument(self::ARG_FORMS);
        $all = (bool)$input->getOption(self::OPTION_ALL);

        if (!$all && $requested === []) {
            $output->writeln('<error>Provide at least one form key, or use --all.</error>');
            $output->writeln('Known forms: ' . implode(', ', array_keys($this->forms)));

            return Command::FAILURE;
        }

        $unknown = array_diff($requested, array_keys($this->forms));
        if ($unknown !== []) {
            $output->writeln('<error>Unknown form(s): ' . implode(', ', $unknown) . '</error>');
            $output->writeln('Known forms: ' . implode(', ', array_keys($this->forms)));

            return Command::FAILURE;
        }

        $targets = $all ? array_keys($this->forms) : $requested;
        foreach ($targets as $formKey) {
            $path = sprintf('altcha/%s/%s', $this->forms[$formKey], $formKey);
            $this->disableEverywhere($path);
            $output->writeln(sprintf('Disabled %s (%s, all scopes)', $formKey, $path));
        }
        if ($all) {
            $this->disableEverywhere('altcha/general/enabled');
            $output->writeln('Disabled altcha/general/enabled (all scopes)');
        }

        $this->cacheTypeList->cleanType(ConfigCache::TYPE_IDENTIFIER);
        try {
            $this->cacheTypeList->cleanType('full_page');
        } catch (\Throwable) {
            // Full-page cache type not available; nothing to clean.
        }
        $output->writeln('<info>Config and full-page caches cleaned. Done.</info>');

        return Command::SUCCESS;
    }

    /**
     * Website/store scope rows would override a default-scope "0": remove them
     * so the disable is effective regardless of where the flag was enabled.
     */
    private function disableEverywhere(string $path): void
    {
        $this->configWriter->save($path, '0');
        foreach ($this->storeManager->getWebsites() as $website) {
            $this->configWriter->delete($path, ScopeInterface::SCOPE_WEBSITES, (int)$website->getId());
        }
        foreach ($this->storeManager->getStores() as $store) {
            $this->configWriter->delete($path, ScopeInterface::SCOPE_STORES, (int)$store->getId());
        }
    }
}

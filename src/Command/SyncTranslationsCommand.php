<?php

namespace App\Command;

use App\Service\TranslationManagerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:translations:sync',
    description: 'Synchronize translation files with configured languages',
)]
class SyncTranslationsCommand extends Command
{
    public function __construct(
        private TranslationManagerService $translationManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('domain', InputArgument::OPTIONAL, 'Translation domain to synchronize', 'admin')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Synchronize all domains')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force overwrite existing translations')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $domain = $input->getArgument('domain');
        $all = $input->getOption('all');
        $force = $input->getOption('force');

        $io->title('Synchronization des traductions');

        try {
            if ($all) {
                // Get all translation files and synchronize each domain
                $translationFiles = $this->translationManager->getTranslationFiles();
                $domains = array_keys($translationFiles);
                
                if (empty($domains)) {
                    $io->warning('Aucun fichier de traduction trouvé.');
                    return Command::SUCCESS;
                }

                $io->progressStart(count($domains));
                
                foreach ($domains as $currentDomain) {
                    $this->translationManager->synchronizeWithLanguages($currentDomain);
                    $io->progressAdvance();
                }
                
                $io->progressFinish();
                $io->success(sprintf('Synchronisation terminée pour %d domaines.', count($domains)));
            } else {
                // Synchronize specific domain
                $io->note(sprintf('Synchronisation du domaine: %s', $domain));
                $this->translationManager->synchronizeWithLanguages($domain);
                $io->success(sprintf('Synchronisation terminée pour le domaine: %s', $domain));
            }

            // Show statistics
            $this->showStatistics($io, $all ? array_keys($this->translationManager->getTranslationFiles()) : [$domain]);

        } catch (\Exception $e) {
            $io->error('Erreur lors de la synchronisation: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function showStatistics(SymfonyStyle $io, array $domains): void
    {
        $io->section('Statistiques de traduction');

        foreach ($domains as $domain) {
            $stats = $this->translationManager->getTranslationStats($domain);
            
            if (empty($stats)) {
                continue;
            }

            $io->text(sprintf('<info>Domaine:</info> %s', $domain));
            
            $rows = [];
            foreach ($stats as $locale => $stat) {
                $percentage = $stat['percentage'];
                $status = $percentage >= 100 ? '✅' : ($percentage >= 75 ? '⚠️' : '❌');
                
                $rows[] = [
                    $status,
                    strtoupper($locale),
                    $stat['completed'] . '/' . $stat['total'],
                    $stat['missing'],
                    $percentage . '%'
                ];
            }

            $io->table(
                ['Status', 'Locale', 'Complétées', 'Manquantes', 'Progression'],
                $rows
            );
            $io->newLine();
        }
    }
}

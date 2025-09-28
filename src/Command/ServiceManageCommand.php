<?php

namespace App\Command;

use App\Entity\Language;
use App\Entity\Service;
use App\Entity\ServiceTranslation;
use App\Repository\LanguageRepository;
use App\Repository\ServiceRepository;
use App\Service\ServiceTranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:service:manage',
    description: 'Manage multilingual services and translations',
)]
class ServiceManageCommand extends Command
{
    public function __construct(
        private ServiceRepository $serviceRepository,
        private LanguageRepository $languageRepository,
        private ServiceTranslationService $translationService,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform: list, create-missing, sync, stats')
            ->addOption('language', 'l', InputOption::VALUE_OPTIONAL, 'Target language code')
            ->addOption('source', 's', InputOption::VALUE_OPTIONAL, 'Source language code')
            ->addOption('service', null, InputOption::VALUE_OPTIONAL, 'Service ID or slug')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        switch ($action) {
            case 'list':
                return $this->listServices($io);
            case 'create-missing':
                return $this->createMissingTranslations($io, $input);
            case 'sync':
                return $this->syncTranslations($io, $input);
            case 'stats':
                return $this->showStatistics($io);
            default:
                $io->error('Unknown action. Available actions: list, create-missing, sync, stats');
                return Command::FAILURE;
        }
    }

    private function listServices(SymfonyStyle $io): int
    {
        $services = $this->translationService->getServicesWithTranslationStatus();
        $languages = $this->languageRepository->findActiveLanguages();

        $io->title('Services and Translation Status');

        $headers = ['ID', 'Slug', 'Status', 'Completion'];
        foreach ($languages as $language) {
            $headers[] = $language->getCode();
        }

        $rows = [];
        foreach ($services as $serviceData) {
            $service = $serviceData['service'];
            $row = [
                $service->getId(),
                $service->getSlug(),
                $service->isActive() ? '✓ Active' : '✗ Inactive',
                $serviceData['completionPercentage'] . '%'
            ];

            foreach ($languages as $language) {
                $status = $serviceData['translations'][$language->getCode()];
                if ($status['complete']) {
                    $row[] = '✓';
                } elseif ($status['partial']) {
                    $row[] = '⚠';
                } else {
                    $row[] = '✗';
                }
            }

            $rows[] = $row;
        }

        $io->table($headers, $rows);
        $io->note('✓ = Complete, ⚠ = Partial, ✗ = Missing');

        return Command::SUCCESS;
    }

    private function createMissingTranslations(SymfonyStyle $io, InputInterface $input): int
    {
        $targetLanguage = $input->getOption('language');
        $sourceLanguage = $input->getOption('source');

        if (!$targetLanguage) {
            $languages = $this->languageRepository->findActiveLanguages();
            $choices = [];
            foreach ($languages as $language) {
                $choices[$language->getCode()] = $language->getNativeName();
            }
            $targetLanguage = $io->choice('Select target language:', $choices);
        }

        $language = $this->languageRepository->findByCode($targetLanguage);
        if (!$language) {
            $io->error("Language '{$targetLanguage}' not found.");
            return Command::FAILURE;
        }

        $io->title("Creating missing translations for {$language->getNativeName()} ({$language->getCode()})");

        $created = $this->translationService->createMissingTranslations($targetLanguage, $sourceLanguage);

        if ($created > 0) {
            $io->success("Created {$created} missing translations.");
        } else {
            $io->info('No missing translations to create.');
        }

        return Command::SUCCESS;
    }

    private function syncTranslations(SymfonyStyle $io, InputInterface $input): int
    {
        $serviceOption = $input->getOption('service');
        $targetLanguage = $input->getOption('language');
        $sourceLanguage = $input->getOption('source');

        if ($serviceOption) {
            // Sync specific service
            $service = is_numeric($serviceOption) 
                ? $this->serviceRepository->find($serviceOption)
                : $this->serviceRepository->findBySlug($serviceOption);

            if (!$service) {
                $io->error("Service '{$serviceOption}' not found.");
                return Command::FAILURE;
            }

            $io->title("Syncing translations for service: {$service->getSlug()}");
            
            if ($targetLanguage && $sourceLanguage) {
                $newTranslation = $this->translationService->duplicateTranslation(
                    $service, 
                    $sourceLanguage, 
                    $targetLanguage
                );
                
                if ($newTranslation) {
                    $io->success("Translation duplicated from {$sourceLanguage} to {$targetLanguage}.");
                } else {
                    $io->error('Failed to duplicate translation.');
                }
            } else {
                $io->error('Both source and target languages are required for sync.');
                return Command::FAILURE;
            }
        } else {
            $io->error('Service ID or slug is required for sync.');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function showStatistics(SymfonyStyle $io): int
    {
        $stats = $this->translationService->getGlobalTranslationStatistics();
        
        $io->title('Translation Statistics');

        $headers = ['Language', 'Total Services', 'Translated', 'Complete', 'Missing', 'Completion %'];
        $rows = [];

        foreach ($stats as $langCode => $langStats) {
            $rows[] = [
                $langStats['language']->getNativeName() . ' (' . $langCode . ')',
                $langStats['total_services'],
                $langStats['translated'],
                $langStats['complete'],
                $langStats['missing'],
                round($langStats['completion_percentage'], 1) . '%'
            ];
        }

        $io->table($headers, $rows);

        // Overall statistics
        $totalServices = count($this->serviceRepository->findActiveServices());
        $totalLanguages = count($this->languageRepository->findActiveLanguages());
        $maxPossibleTranslations = $totalServices * $totalLanguages;
        
        $totalTranslated = array_sum(array_column($stats, 'translated'));
        $totalComplete = array_sum(array_column($stats, 'complete'));
        
        $overallCompletion = $maxPossibleTranslations > 0 
            ? round(($totalComplete / $maxPossibleTranslations) * 100, 1)
            : 0;

        $io->section('Overall Statistics');
        $io->definitionList(
            ['Total Services' => $totalServices],
            ['Total Languages' => $totalLanguages],
            ['Total Translations' => $totalTranslated],
            ['Complete Translations' => $totalComplete],
            ['Overall Completion' => $overallCompletion . '%']
        );

        return Command::SUCCESS;
    }
}

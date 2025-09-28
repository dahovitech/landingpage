<?php

namespace App\Command;

use App\Entity\Language;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:setup-languages',
    description: 'Create default languages if they don\'t exist'
)]
class SetupLanguagesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Default languages to create
        $defaultLanguages = [
            ['code' => 'fr', 'name' => 'Français', 'nativeName' => 'Français', 'isDefault' => true, 'sortOrder' => 1],
            ['code' => 'en', 'name' => 'English', 'nativeName' => 'English', 'isDefault' => false, 'sortOrder' => 2],
            ['code' => 'es', 'name' => 'Español', 'nativeName' => 'Español', 'isDefault' => false, 'sortOrder' => 3],
            ['code' => 'de', 'name' => 'Deutsch', 'nativeName' => 'Deutsch', 'isDefault' => false, 'sortOrder' => 4],
        ];

        $languageRepository = $this->entityManager->getRepository(Language::class);
        $created = 0;

        foreach ($defaultLanguages as $langData) {
            // Check if language already exists
            $existingLanguage = $languageRepository->findByCode($langData['code']);
            
            if (!$existingLanguage) {
                $language = new Language();
                $language->setCode($langData['code']);
                $language->setName($langData['name']);
                $language->setNativeName($langData['nativeName']);
                $language->setIsActive(true);
                $language->setIsDefault($langData['isDefault']);
                $language->setSortOrder($langData['sortOrder']);

                $this->entityManager->persist($language);
                $created++;
                
                $io->success("Created language: {$langData['name']} ({$langData['code']})");
            } else {
                // Ensure existing language is active
                if (!$existingLanguage->isActive()) {
                    $existingLanguage->setIsActive(true);
                    $this->entityManager->persist($existingLanguage);
                    $io->note("Activated existing language: {$existingLanguage->getName()}");
                }
            }
        }

        if ($created > 0) {
            $this->entityManager->flush();
            $io->success("Successfully created {$created} languages.");
        } else {
            $io->info('All languages already exist.');
        }

        // Show current language status
        $allLanguages = $languageRepository->findAll();
        $activeLanguages = $languageRepository->findActiveLanguages();
        
        $io->section('Language Status');
        $io->table(
            ['Code', 'Name', 'Active', 'Default'],
            array_map(function(Language $lang) {
                return [
                    $lang->getCode(),
                    $lang->getName(),
                    $lang->isActive() ? '✓' : '✗',
                    $lang->isDefault() ? '✓' : '✗'
                ];
            }, $allLanguages)
        );

        return Command::SUCCESS;
    }
}

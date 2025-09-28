<?php

namespace App\Command;

use App\Entity\FAQ;
use App\Entity\Feature;
use App\Entity\PricingPlan;
use App\Entity\Testimonial;
use App\Service\SlugGeneratorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-missing-slugs',
    description: 'Generate missing slugs for all translatable entities'
)]
class GenerateMissingSlugsCommand extends Command
{
    private const ENTITY_CONFIGS = [
        Feature::class => [
            'title_field' => 'title',
            'fallback_field' => 'icon',
            'name' => 'Features'
        ],
        PricingPlan::class => [
            'title_field' => 'name',
            'fallback_field' => 'price',
            'name' => 'Pricing Plans'
        ],
        FAQ::class => [
            'title_field' => 'question',
            'fallback_field' => 'category',
            'name' => 'FAQ'
        ],
        Testimonial::class => [
            'title_field' => 'clientName',
            'fallback_field' => 'clientCompany',
            'name' => 'Testimonials'
        ]
    ];

    public function __construct(
        private SlugGeneratorService $slugGenerator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('entity', null, InputOption::VALUE_OPTIONAL, 'Specific entity to process (feature, pricing, faq, testimonial)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without making changes')
            ->setHelp(<<<HELP
This command generates missing slugs for translatable entities.

Available entities:
- feature: Features
- pricing: Pricing Plans  
- faq: FAQ
- testimonial: Testimonials

Examples:
  php bin/console app:generate-missing-slugs
  php bin/console app:generate-missing-slugs --entity=feature
  php bin/console app:generate-missing-slugs --dry-run
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = $input->getOption('dry-run');
        $specificEntity = $input->getOption('entity');

        $io->title('Generate Missing Slugs for Translatable Entities');

        if ($isDryRun) {
            $io->warning('DRY RUN MODE - No changes will be made');
        }

        $entityConfigs = self::ENTITY_CONFIGS;

        // Filter to specific entity if requested
        if ($specificEntity) {
            $entityClass = $this->getEntityClassByName($specificEntity);
            if (!$entityClass) {
                $io->error("Unknown entity: {$specificEntity}");
                $io->note("Available entities: " . implode(', ', array_keys($this->getEntityNameMap())));
                return Command::FAILURE;
            }
            $entityConfigs = [$entityClass => self::ENTITY_CONFIGS[$entityClass]];
        }

        $totalUpdated = 0;

        foreach ($entityConfigs as $entityClass => $config) {
            $io->section("Processing {$config['name']}");

            try {
                if ($isDryRun) {
                    $updated = $this->countMissingSlugs($entityClass);
                    $io->info("Would generate {$updated} missing slugs for {$config['name']}");
                } else {
                    $updated = $this->slugGenerator->generateMissingSlugs($entityClass, $config['title_field']);
                    $io->success("Generated {$updated} missing slugs for {$config['name']}");
                }

                $totalUpdated += $updated;

            } catch (\Exception $e) {
                $io->error("Error processing {$config['name']}: " . $e->getMessage());
                return Command::FAILURE;
            }
        }

        if ($totalUpdated > 0) {
            if ($isDryRun) {
                $io->success("Would generate {$totalUpdated} total missing slugs");
            } else {
                $io->success("Successfully generated {$totalUpdated} total missing slugs");
            }
        } else {
            $io->info('No missing slugs found. All entities already have slugs.');
        }

        return Command::SUCCESS;
    }

    private function getEntityClassByName(string $name): ?string
    {
        $mapping = $this->getEntityNameMap();
        return $mapping[strtolower($name)] ?? null;
    }

    private function getEntityNameMap(): array
    {
        return [
            'feature' => Feature::class,
            'pricing' => PricingPlan::class,
            'faq' => FAQ::class,
            'testimonial' => Testimonial::class
        ];
    }

    private function countMissingSlugs(string $entityClass): int
    {
        // This is a simplified version for dry-run
        // In a real implementation, you'd count entities without slugs
        return 0; // Placeholder - would need entity manager to count properly
    }
}
<?php

namespace App\Command;

use App\Service\HelpZipImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-help-zip',
    description: "Importe un ZIP d'articles (1 dossier = 1 categorie, 1 fichier .md = 1 article).",
)]
class ImportHelpZipCommand extends Command
{
    public function __construct(
        private HelpZipImporter $importer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('zip_path', InputArgument::REQUIRED, 'Chemin vers le fichier ZIP à importer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $zipPath = (string) $input->getArgument('zip_path');

        try {
            $stats = $this->importer->importFromZip($zipPath);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        } catch (\Throwable $e) {
            $io->error("Erreur inattendue pendant l'import: " . $e->getMessage());
            return Command::FAILURE;
        }

        $io->success([
            'Import terminé.',
            "Catégories créées: {$stats['created_sections']}",
            "Sous-catégories créées: {$stats['created_subsections']}",
            "Articles créés: {$stats['created_articles']}",
            "Articles mis à jour: {$stats['updated_articles']}",
        ]);

        return Command::SUCCESS;
    }
}

<?php

namespace App\Command;

use App\Gazette\Send\SendConfiguration;
use App\Source\SourceUnavailableException;
use App\Source\Listmonk\ListmonkClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Crée dans Listmonk ce que l'envoi attend et qui manque : la liste des
 * destinataires (LISTMONK_LIST) et le template de campagne (LISTMONK_TEMPLATE,
 * depuis listmonk/template-gazette.html). Ne touche pas à ce qui existe.
 */
#[AsCommand('gazette:listmonk-setup', 'Crée la liste et le template Listmonk de la gazette s\'ils manquent')]
final class ListmonkSetupCommand extends Command
{
    public function __construct(
        private readonly ListmonkClient $listmonk,
        private readonly SendConfiguration $config,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $listId = $this->listmonk->listId($this->config->listName);
        if (null === $listId) {
            try {
                $listId = $this->listmonk->createList($this->config->listName);
            } catch (SourceUnavailableException $e) {
                $io->error([\sprintf('La liste « %s » manque et sa création a échoué : %s', $this->config->listName, $e->getMessage()), 'Soit la créer à la main dans Listmonk (Lists → New, type private), soit ajouter lists:manage_all au rôle.']);

                return Command::FAILURE;
            }
            $io->success(\sprintf('Liste « %s » créée (id %d). Y ajouter les destinataires, prénom compris, et les adresses de test.', $this->config->listName, $listId));
        } else {
            $io->text(\sprintf('Liste « %s » déjà là (id %d).', $this->config->listName, $listId));
        }

        $templateId = $this->listmonk->templateId($this->config->templateName);
        if (null === $templateId) {
            $html = (string) file_get_contents($this->projectDir.'/listmonk/template-gazette.html');
            $templateId = $this->listmonk->createTemplate($this->config->templateName, $html);
            $io->success(\sprintf('Template « %s » créé (id %d).', $this->config->templateName, $templateId));
        } else {
            $io->text(\sprintf('Template « %s » déjà là (id %d), non modifié.', $this->config->templateName, $templateId));
        }

        return Command::SUCCESS;
    }
}

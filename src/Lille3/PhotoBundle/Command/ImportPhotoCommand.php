<?php

namespace Lille3\PhotoBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportPhotoCommand extends ContainerAwareCommand {
	protected function configure() {
		$this
			->setName('photo:import')
			->setDescription('Importe la photo de tout les utilisateurs');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->getContainer()->get('lille3_photo.service')->importAllPhoto($output);
	}
}
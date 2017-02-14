<?php

namespace Lille3\PhotoBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UserPhotoCommand extends ContainerAwareCommand {
	protected function configure() {
		$this
			->setName('photo:user')
			->setDescription('Importe la photo d\'un utilisateur')
			->addArgument('uid', InputArgument::REQUIRED, 'L\'uid de l\'utilisateur dont on veut importer la photo')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Forcer la mise à jour de la photo');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->getContainer()->get('lille3_photo.service')->importUserPhoto($output, $input->getArgument('uid'), $input->hasArgument('force') ? true : false);
	}
}
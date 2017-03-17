<?php

namespace Lille3\PhotoBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeletePhotoCommand extends ContainerAwareCommand {
	protected function configure() {
		$this
			->setName('photo:delete')
			->setDescription('Supprime les photos des utilisateurs absents du LDAP');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->getContainer()->get('lille3_photo.service')->deletePhotos($output);
	}
}
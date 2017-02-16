<?php

namespace Lille3\PhotoBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('lille3_photo');

        $rootNode
			->children()
				->arrayNode('easyid')
					->children()
                                                ->scalarNode('activated')->isRequired()->cannotBeEmpty()->end()
						->scalarNode('username')->isRequired()->cannotBeEmpty()->end()
						->scalarNode('password')->isRequired()->cannotBeEmpty()->end()
						->scalarNode('connection')->isRequired()->cannotBeEmpty()->end()
					->end()
					->validate()
						->ifTrue(function($config) { return ( ((is_bool($config['activated'])) && ( ($config['activated'] == false) || (($config['activated'] == true) && (!oci_connect($config['username'], $config['password'], $config['connection']))))) ); })
							->thenInvalid('Error during Oracle Connection')
						->end()
				->end()
                                ->arrayNode('easyidcomue')                        
                                        ->children()
                                                ->scalarNode('activated')->cannotBeEmpty()->isRequired()->end()
                                                ->scalarNode('dirlocalread')->isRequired()->cannotBeEmpty()->end()  
                                                ->scalarNode('extfile')->isRequired()->cannotBeEmpty()->end()
                                        ->end()
                                        ->validate()
                                                ->ifTrue(function($config) { return (is_bool($config['activated']) && (is_string($config['easyidcomue_dirlocalread'])) && (is_string($config['extfile'])) );})
                                                        ->thenInvalid('Error during verify Easyid COMUE values')
                                                ->end()        
                                ->end()                                                                                
				->arrayNode('photo_db')
					->children()
						->scalarNode('hostname')->isRequired()->cannotBeEmpty()->end()
						->scalarNode('username')->isRequired()->cannotBeEmpty()->end()
						->scalarNode('password')->isRequired()->cannotBeEmpty()->end()
						->scalarNode('database')->isRequired()->cannotBeEmpty()->end()
					->end()
					->validate()
						->ifTrue(function($config) { $co = mysqli_connect($config['hostname'], $config['username'], $config['password'], $config['database']); return (mysqli_errno($co) !== 0);})
						->thenInvalid('Error during Mysql Connection')
					->end()
				->end()
				->scalarNode('path')
					->isRequired()
					->cannotBeEmpty()
					->beforeNormalization()
						->ifTrue(function($path) { return substr($path, -1) !== '/';})
						->then(function($path) { return $path . '/'; })
						->end()
					->validate()
						->ifTrue(function($path) { return !is_dir($path); })
							->thenInvalid('Invalid directory "%s"')
						->end()
				->end()
				->arrayNode('memcached')
					->children()
						->scalarNode('host')->isRequired()->cannotBeEmpty()->end()
						->scalarNode('port')->defaultValue(11211)->end()
					->end()
					->isRequired()
					->cannotBeEmpty()
				->end()
				->variableNode('valid_server')
					->isRequired()
					->cannotBeEmpty()
					->beforeNormalization()
						->ifTrue(function($list) { return is_string($list); })
						->then(function($list) { explode(',', $list); })
						->end()
					->validate()
						->ifTrue(function($list) { return !is_array($list); })
							->thenInvalid('Invalid list of available server')
						->end()
				->end()
				->variableNode('resize')
					->defaultValue(false)
					->beforeNormalization()
						->ifTrue(function($resize) { return is_string($resize) && preg_match('#^([0-9]+)x([0-9]+)$#', $resize); })
						->then(function($resize) { return explode('x', $resize); })
						->end()
					->validate()
						->ifTrue(function($resize) { return !is_array($resize) && !is_null($resize); })
							->thenInvalid('Invalid resize setting')
						->end()
				->end()
				->scalarNode('default')
					->isRequired()
					->cannotBeEmpty()
					->validate()
						->ifTrue(function($path) { return !file_exists($path); })
							->thenInvalid('Invalid file %s')
						->end()
				->end()
				->scalarNode('blocked')
					->isRequired()
					->cannotBeEmpty()
					->validate()
						->ifTrue(function($path) { return !file_exists($path); })
							->thenInvalid('Invalid file %s')
						->end()
				->end()
			->end()
		->end();

        return $treeBuilder;
    }
}
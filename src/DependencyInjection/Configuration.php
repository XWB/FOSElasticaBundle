<?php

/*
 * This file is part of the FOSElasticaBundle package.
 *
 * (c) FriendsOfSymfony <https://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\ElasticaBundle\DependencyInjection;

use Elastic\Elasticsearch\Transport\RequestOptions;
use FOS\ElasticaBundle\Serializer\Callback as SerializerCallback;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    private const SUPPORTED_DRIVERS = ['orm', 'mongodb', 'phpcr'];

    /**
     * If the kernel is running in debug mode.
     */
    private bool $debug;

    public function __construct(bool $debug)
    {
        $this->debug = $debug;
    }

    /**
     * Generates the configuration tree.
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('fos_elastica');
        $rootNode = $treeBuilder->getRootNode();

        $this->addClientsSection($rootNode);
        $this->addIndexesSection($rootNode);
        $this->addIndexTemplatesSection($rootNode);

        $rootNode
            ->children()
                ->scalarNode('default_client')
                    ->info('Defaults to the first client defined')
                ->end()
                ->scalarNode('default_index')
                    ->info('Defaults to the first index defined')
                ->end()
                ->scalarNode('default_manager')->defaultValue('orm')->end()
                ->arrayNode('messenger')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('bus')->defaultValue('messenger.default_bus')->end()
                    ->end()
                ->end()
                ->arrayNode('serializer')
                    ->treatNullLike([])
                    ->children()
                        ->scalarNode('callback_class')->defaultValue(SerializerCallback::class)->end()
                        ->scalarNode('serializer')->defaultValue('serializer')->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * Returns the array node used for "dynamic_templates".
     */
    private function getDynamicTemplateNode(): ArrayNodeDefinition
    {
        $node = $this->createTreeBuilderNode('dynamic_templates');

        $node
            ->arrayPrototype()
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('match')->end()
                        ->scalarNode('unmatch')->end()
                        ->scalarNode('match_mapping_type')->end()
                        ->scalarNode('path_match')->end()
                        ->scalarNode('path_unmatch')->end()
                        ->scalarNode('match_pattern')->end()
                        ->arrayNode('mapping')
                            ->variablePrototype()
                                ->treatNullLike([])
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->validate()
                    ->ifTrue(static function ($v) {
                        return 1 !== \count($v);
                    })
                    ->thenInvalid('Dynamic template should consist of a single named object: %s.')
                ->end()
            ->end()
        ;

        return $node;
    }

    /**
     * Returns the array node used for "properties".
     */
    private function getPropertiesNode(): ArrayNodeDefinition
    {
        $node = $this->createTreeBuilderNode('properties');

        $node
            ->useAttributeAsKey('name')
            ->prototype('variable')
                ->treatNullLike([])
        ;

        return $node;
    }

    /**
     * Returns the array node used for "_id".
     */
    private function getIdNode(): ArrayNodeDefinition
    {
        $node = $this->createTreeBuilderNode('_id');

        $node
            ->children()
            ->scalarNode('path')->end()
            ->end()
        ;

        return $node;
    }

    /**
     * Returns the array node used for "_source".
     */
    private function getSourceNode(): ArrayNodeDefinition
    {
        $node = $this->createTreeBuilderNode('_source');

        $node
            ->children()
                ->arrayNode('excludes')
                    ->useAttributeAsKey('name')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('includes')
                    ->useAttributeAsKey('name')
                    ->prototype('scalar')->end()
                ->end()
                ->scalarNode('compress')->end()
                ->scalarNode('compress_threshold')->end()
                ->scalarNode('enabled')->defaultTrue()->end()
            ->end()
        ;

        return $node;
    }

    /**
     * Returns the array node used for "_routing".
     */
    private function getRoutingNode(): ArrayNodeDefinition
    {
        $node = $this->createTreeBuilderNode('_routing');

        $node
            ->children()
                ->scalarNode('required')->end()
                ->scalarNode('path')->end()
            ->end()
        ;

        return $node;
    }

    /**
     * @return ArrayNodeDefinition
     */
    private function getPersistenceNode()
    {
        $node = $this->createTreeBuilderNode('persistence');

        $node
            ->validate()
                ->ifTrue(function ($v) {
                    return isset($v['driver']) && 'orm' !== $v['driver'] && !empty($v['elastica_to_model_transformer']['hints']);
                })
                    ->thenInvalid('Hints are only supported by the "orm" driver')
            ->end()
            ->children()
                ->scalarNode('driver')
                    ->defaultValue('orm')
                    ->validate()
                    ->ifNotInArray(self::SUPPORTED_DRIVERS)
                        ->thenInvalid('The driver %s is not supported. Please choose one of '.\json_encode(self::SUPPORTED_DRIVERS))
                    ->end()
                ->end()
                ->scalarNode('model')->defaultValue(null)->end()
                ->scalarNode('repository')->end()
                ->scalarNode('identifier')->defaultValue('id')->end()
                ->arrayNode('provider')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('batch_size')->defaultValue(100)->end()
                        ->scalarNode('clear_object_manager')->defaultTrue()->end()
                        ->booleanNode('debug_logging')
                            ->defaultValue($this->debug)
                            ->treatNullLike(true)
                        ->end()
                        ->scalarNode('query_builder_method')->defaultValue('createQueryBuilder')->end()
                        ->scalarNode('locale')->end()
                        ->scalarNode('service')->end()
                    ->end()
                ->end()
                ->arrayNode('listener')
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('insert')->defaultTrue()->end()
                        ->scalarNode('update')->defaultTrue()->end()
                        ->scalarNode('delete')->defaultTrue()->end()
                        ->scalarNode('flush')->defaultTrue()->end()
                        ->booleanNode('defer')->defaultFalse()->end()
                        ->scalarNode('logger')
                            ->defaultFalse()
                            ->treatNullLike('fos_elastica.logger')
                            ->treatTrueLike('fos_elastica.logger')
                        ->end()
                        ->scalarNode('service')->end()
                    ->end()
                ->end()
                ->arrayNode('finder')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('service')->end()
                    ->end()
                ->end()
                ->arrayNode('elastica_to_model_transformer')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('hints')
                            ->prototype('array')
                                ->children()
                                    ->scalarNode('name')->end()
                                    ->scalarNode('value')->end()
                                ->end()
                            ->end()
                        ->end()
                        ->booleanNode('hydrate')->defaultTrue()->end()
                        ->booleanNode('ignore_missing')
                            ->defaultFalse()
                            ->info('Silently ignore results returned from Elasticsearch without corresponding persistent object.')
                        ->end()
                        ->scalarNode('query_builder_method')->defaultValue('createQueryBuilder')->end()
                        ->scalarNode('service')->end()
                    ->end()
                ->end()
                ->arrayNode('model_to_elastica_transformer')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('service')->end()
                    ->end()
                ->end()
                ->arrayNode('persister')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('refresh')
                            ->treatTrueLike('true')
                            ->treatFalseLike('false')
                            ->values(['true', 'wait_for', 'false'])
                        ->end()
                        ->scalarNode('service')->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }

    /**
     * @return ArrayNodeDefinition
     */
    private function getSerializerNode()
    {
        $node = $this->createTreeBuilderNode('serializer');

        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('groups')
                    ->treatNullLike([])
                    ->prototype('scalar')->end()
                ->end()
                ->scalarNode('version')->end()
                ->booleanNode('serialize_null')
                    ->defaultFalse()
                ->end()
            ->end()
        ;

        return $node;
    }

    /**
     * Adds the configuration for the "clients" key.
     */
    private function addClientsSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->fixXmlConfig('client')
            ->children()
                ->arrayNode('clients')
                    ->useAttributeAsKey('id')
                    ->fixXmlConfig('header')
                    ->prototype('array')
                        ->performNoDeepMerging()
                        // Elastica names its properties with camel case, support both
                        ->children()
                            ->arrayNode('hosts')
                                ->requiresAtLeastOneElement()
                                ->prototype('scalar')
                                    ->validate()
                                    ->ifTrue(function ($url) {
                                        return $url && !\str_ends_with($url, '/');
                                    })
                                    ->then(function ($url) {
                                        return $url.'/';
                                    })
                                    ->end()
                                ->end()
                            ->end()
                            ->scalarNode('username')->end()
                            ->scalarNode('password')->end()
                            ->scalarNode('http_client')->end()
                            ->scalarNode('cloud_id')->end()
                            ->scalarNode('retries')->end()
                            ->scalarNode('api_key')->end()
                            ->arrayNode('http_error_codes')
                                ->beforeNormalization()
                                    ->ifTrue(function ($v) { return !\is_array($v); })
                                    ->then(function ($v) { return [$v]; })
                                ->end()
                                ->requiresAtLeastOneElement()
                                ->defaultValue([400, 403])
                                ->prototype('scalar')->end()
                            ->end()
                            ->scalarNode('logger')
                                ->defaultValue($this->debug ? 'fos_elastica.logger' : false)
                                ->treatNullLike('fos_elastica.logger')
                                ->treatTrueLike('fos_elastica.logger')
                            ->end()
                            ->arrayNode('client_config')
                                ->children()
                                    ->scalarNode(RequestOptions::SSL_CERT)->end()
                                    ->scalarNode(RequestOptions::SSL_KEY)->end()
                                    ->scalarNode(RequestOptions::SSL_VERIFY)->end()
                                    ->scalarNode(RequestOptions::SSL_CA)->end()
                                ->end()
                            ->end()
                            ->arrayNode('client_options')
                                ->normalizeKeys(false)
                                ->useAttributeAsKey('name')
                                ->prototype('scalar')->end()
                            ->end()
                            ->arrayNode('headers')
                                ->normalizeKeys(false)
                                ->useAttributeAsKey('name')
                                ->prototype('scalar')->end()
                            ->end()
                            ->scalarNode('timeout')
                                ->defaultValue(30)
                            ->end()
                            ->scalarNode('retry_on_conflict')
                                ->defaultValue(0)
                            ->end()
                            ->scalarNode('connection_strategy')->defaultValue('Simple')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * Adds the configuration for the "indexes" key.
     */
    private function addIndexesSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->fixXmlConfig('index')
            ->children()
                ->arrayNode('indexes')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->treatNullLike([])
                        ->children()
                            ->scalarNode('index_name')
                                ->info('Defaults to the name of the index, but can be modified if the index name is different in ElasticSearch')
                            ->end()
                            ->variableNode('indexable_callback')->end()
                            ->booleanNode('use_alias')->defaultValue(false)->end()
                            ->scalarNode('client')->end()
                            ->scalarNode('finder')
                                ->treatNullLike(true)
                                ->defaultFalse()
                            ->end()
                            ->append($this->getPersistenceNode())
                            ->append($this->getSerializerNode())
                            ->arrayNode('index_prototype')
                                ->children()
                                    ->scalarNode('analyzer')->end()
                                    ->append($this->getPersistenceNode())
                                    ->append($this->getSerializerNode())
                                ->end()
                            ->end()
                            ->variableNode('settings')->defaultValue([])->end()
                            ->booleanNode('date_detection')->end()
                            ->arrayNode('dynamic_date_formats')->prototype('scalar')->end()->end()
                            ->scalarNode('analyzer')->end()
                            ->booleanNode('numeric_detection')->end()
                            ->scalarNode('dynamic')->end()
                        ->end()
                        ->append($this->getIdNode())
                        ->append($this->getPropertiesNode())
                        ->append($this->getDynamicTemplateNode())
                        ->append($this->getSourceNode())
                        ->append($this->getRoutingNode())
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * @return ArrayNodeDefinition
     */
    private function createTreeBuilderNode(string $name)
    {
        return (new TreeBuilder($name))->getRootNode();
    }

    /**
     * Adds the configuration for the "index_templates" key.
     *
     * @return void
     */
    private function addIndexTemplatesSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->fixXmlConfig('index_template')
            ->children()
                ->arrayNode('index_templates')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->treatNullLike([])
                        ->beforeNormalization()
                        ->ifNull()
                        ->thenEmptyArray()
                        ->end()
                        // Support multiple dynamic_template formats to match the old bundle style
                        // and the way ElasticSearch expects them
                        ->beforeNormalization()
                        ->ifTrue(function ($v) {
                            return isset($v['dynamic_templates']);
                        })
                        ->then(function ($v) {
                            $dt = [];
                            foreach ($v['dynamic_templates'] as $key => $type) {
                                if (\is_int($key)) {
                                    $dt[] = $type;
                                } else {
                                    $dt[][$key] = $type;
                                }
                            }

                            $v['dynamic_templates'] = $dt;

                            return $v;
                        })
                        ->end()
                        ->children()
                            ->scalarNode('template_name')
                                ->info('Defaults to the name of the index template, but can be modified if the index name is different in ElasticSearch')
                            ->end()
                            ->arrayNode('index_patterns')
                                ->requiresAtLeastOneElement()
                                ->prototype('scalar')
                                ->end()
                            ->end()
                            ->scalarNode('client')->end()
                            ->variableNode('settings')->defaultValue([])->end()
                            ->booleanNode('date_detection')->end()
                            ->arrayNode('dynamic_date_formats')->prototype('scalar')->end()->end()
                            ->scalarNode('analyzer')->end()
                            ->booleanNode('numeric_detection')->end()
                            ->scalarNode('dynamic')->end()
                        ->end()
                        ->append($this->getIdNode())
                        ->append($this->getPropertiesNode())
                        ->append($this->getDynamicTemplateNode())
                        ->append($this->getSourceNode())
                        ->append($this->getRoutingNode())
                    ->end()
                ->end()
            ->end()
        ;
    }
}

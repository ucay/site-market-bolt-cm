<?php

namespace Bolt\Extension\Bolt\MarketPlace;

use Bolt\Extension\Bolt\MarketPlace\Storage\Entity;
use Bolt\Extension\Bolt\MarketPlace\Storage\Repository;
use Bolt\Extension\Bolt\MarketPlace\Storage\Schema\Table;
use Bolt\Extension\DatabaseSchemaTrait;
use Bolt\Extension\SimpleExtension;
use Bolt\Extension\StorageTrait;
use Pimple as Container;
use Silex\Application;

/**
 * Extension site extension loader
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class MarketPlaceExtension extends SimpleExtension
{
    use DatabaseSchemaTrait;
    use StorageTrait;

    /**
     * {@inheritdoc}
     */
    public function getServiceProviders()
    {
        $providers = parent::getServiceProviders();
        $providers[] = new Provider\MarketPlaceServiceProvider();

        return $providers;
    }

    /**
     * {@inheritdoc}
     */
    protected function registerServices(Application $app)
    {
        $app['resources']->setPath('composer', 'var/cache/composer');
        $app['resources']->setPath('satis', 'var/cache/satis');

        putenv('COMPOSER_HOME=' . $app['resources']->getPath('composer'));

        $this->extendDatabaseSchemaServices();
        $this->extendRepositoryMapping();
    }

    /**
     * {@inheritdoc}
     */
    protected function registerFrontendControllers()
    {
        $app = $this->getContainer();

        return [
            '/' => $app['marketplace.controller.frontend'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerNutCommands(Container $container)
    {
        return [
            new Command\ExtensionTestRunner($container),
            new Command\QueueProcess($container),
            new Command\SatisBuilder($container),
            new Command\SatisBuilderWebIndex($container),
            new Command\SatisJsonUpdate($container),
            new Command\UpdatePackage($container),
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerExtensionTables()
    {
        return [
            'marketplace_package'         => Table\Package::class,
            'marketplace_package_star'    => Table\PackageStar::class,
            'marketplace_package_version' => Table\PackageVersion::class,
            'marketplace_stat_install'    => Table\StatInstall::class,
            'marketplace_stat_webhook'    => Table\StatWebhook::class,
            'marketplace_token'           => Table\Token::class,
            'marketplace_version_build'   => Table\VersionBuild::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerRepositoryMappings()
    {
        return [
            'marketplace_package'         => [Entity\Package::class        => Repository\Package::class],
            'marketplace_package_star'    => [Entity\PackageStar::class    => Repository\PackageStar::class],
            'marketplace_package_version' => [Entity\PackageVersion::class => Repository\PackageVersion::class],
            'marketplace_stat_install'    => [Entity\StatInstall::class => Repository\StatInstall::class],
            'marketplace_stat_webhook'    => [Entity\StatWebhook::class => Repository\StatWebhook::class],
            'marketplace_token'           => [Entity\Token::class          => Repository\Token::class],
            'marketplace_version_build'   => [Entity\VersionBuild::class   => Repository\VersionBuild::class],
        ];
    }
}

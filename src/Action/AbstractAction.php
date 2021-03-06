<?php

namespace Bundle\Site\MarketPlace\Action;

use Bolt\Config;
use Bundle\Site\MarketPlace\Storage\Entity;
use Bundle\Site\MarketPlace\Storage\Repository;
use Bolt\Storage\EntityManager;
use Silex\Application;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Abstract 'Action' class.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
abstract class AbstractAction implements ActionInterface
{
    /** @var Application */
    private $app;

    /**
     * Constructor.
     *
     * @TODO We don't need app for live
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function getAppService($name)
    {
        return $this->app[$name];
    }

    /**
     * @param Entity\Package $package
     *
     * @return array|false
     */
    public function getWebhookData(Entity\Package $package)
    {
        /** @var \Bolt\Extension\BoltAuth\Auth\AccessControl\Session $members */
        $members = $this->getAppService('auth.session');
        if (!$members->hasAuthorisation()) {
            return false;
        }
        if ($package->getAccountId() !== $members->getAuthorisation()->getAccount()->getGuid()) {
            return false;
        }

        /** @var EntityManager $em */
        $em = $this->getAppService('storage');
        /** @var Repository\PackageToken $tokenRepo */
        $tokenRepo = $em->getRepository(Entity\PackageToken::class);
        $tokenEntity = $tokenRepo->getValidPackageToken($package->getId(), 'webhook');

        /** @var Repository\StatWebhook $statWebhookRepo */
        $statWebhookRepo = $em->getRepository(Entity\StatWebhook::class);
        /** @var Entity\StatWebhook $stat */
        $stat = $statWebhookRepo->getLatest($package->getId());
        if ($stat !== false) {
            /** @var Session $session */
            $session = $this->getAppService('session');
            if ($session->get('pending-' . $tokenEntity, false)) {
                return false;
            }
        }

        /** @var UrlGeneratorInterface $urlGen */
        $urlGen = $this->getAppService('url_generator');
        $source = explode('/', ltrim(parse_url($package->getSource(), PHP_URL_PATH), '/'));

        return [
            'callback' => $tokenEntity ? $urlGen->generate('hookListener', ['token' => $tokenEntity->getToken()], UrlGeneratorInterface::ABSOLUTE_URL) : false,
            'latest'   => $stat,
            'user'     => $source[0],
            'repo'     => $source[1],
            'token'    => $tokenEntity,
        ];
    }

    /**
     * @param Entity\Package $package
     *
     * @return array
     */
    protected function getUpdated(Entity\Package $package)
    {
        $em = $this->getAppService('storage');
        /** @var Repository\PackageVersion $repo */
        $repo = $em->getRepository(Entity\PackageVersion::class);

        return [
            'dev'    => $repo->getLatestReleaseForStability($package->getId(), 'dev'),
            'stable' => $repo->getLatestReleaseForStability($package->getId(), 'stable'),
        ];
    }

    /**
     * @param Entity\Package $package
     *
     * @return Entity\PackageVersion[]
     */
    protected function getVersions(Entity\Package $package)
    {
        $em = $this->getAppService('storage');
        /** @var Repository\PackageVersion $repo */
        $repo = $em->getRepository(Entity\PackageVersion::class);
        /** @var Config $config */
        $config = $this->getAppService('config');
        $boltMajorVersions = $config->get('general/bolt_major_versions');

        $versions = [];
        foreach ($boltMajorVersions as $boltMajorVersion) {
            $entity = $repo->getLatestCompatibleVersion($package->getId(), 'stable', $boltMajorVersion . '.99999');
            if ($entity === false) {
                continue;
            }
            $versions[$boltMajorVersion] = $entity;
        }

        return $versions;
    }
}

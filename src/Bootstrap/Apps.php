<?php

namespace Symlex\Bootstrap;

use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;
use Symlex\Bootstrap\Exception\Exception;
use Symfony\Component\Yaml\Parser as YamlParser;

/**
 * @author Michael Mayer <michael@lastzero.net>
 * @license MIT
 */
abstract class Apps extends App
{
    /**
     * @var array
     */
    protected $guestApps = array();

    /**
     * @var array
     */
    protected $guestAppConfig;

    /**
     * @var YamlParser
     */
    protected $yamlParser;

    public function __construct($environment = 'apps', $appPath = '', $debug = false)
    {
        $this->yamlParser = new YamlParser();

        parent::__construct($environment, $appPath, $debug);

        $this->loadGuests();
    }

    abstract protected function findGuestAppConfig();

    abstract protected function getGuestAppInstance();

    abstract protected function configureGuestApp(App $app);

    public function loadGuests()
    {
        $guestConfigFilename = $this->getConfigPath() . '/' . $this->getEnvironment() . '.guests.yml';

        $guests = $this->yamlParser->parse(file_get_contents($guestConfigFilename));

        foreach ($guests as $label => $guestAppConfig) {
            $this->addGuestAppConfig($label, $guestAppConfig);
        }
    }

    public function addGuestAppConfig($label, $guestAppConfig)
    {
        // Replace relative with absolute paths
        foreach ($guestAppConfig as $key => $value) {
            if (substr($key, -4) === 'path' && strpos($value, '/') !== 0) {
                $guestAppConfig[$key] = realpath($this->getAppPath() . '/../' . $value);
            }
        }

        $guestAppConfig = array('label' => $label) + $guestAppConfig;

        $this->guestApps[$label] = $guestAppConfig;
    }

    public function removeGuestAppConfig($label)
    {
        unset($this->guestApps[$label]);
    }

    public function setGuestAppConfig($guestAppConfig)
    {
        $this->guestAppConfig = $guestAppConfig;
    }

    public function getGuestAppConfig()
    {
        if ($this->guestAppConfig) {
            $result = $this->guestAppConfig;
        } else {
            $result = $this->findGuestAppConfig();

            $this->setGuestAppConfig($result);
        }

        return $result;
    }

    public function getContainerCacheFilename(): string
    {
        $environment = $this->getEnvironment();
        $config = $this->getGuestAppConfig();

        $filename = $this->getCachePath() . '/' . $environment . '_' . $config['label'] . '_container.php';

        return $filename;
    }

    protected function getGuestAppConfigPath(): string
    {
        $config = $this->getGuestAppConfig();

        if (isset($config['config_path'])) {
            $result = $config['config_path'];
        } else {
            $result = isset($config['path']) ? $config['path'] . '/config' : $this->getConfigPath();
        }

        return $result;
    }

    protected function getGuestAppPath(): string
    {
        $config = $this->getGuestAppConfig();

        $result = isset($config['path']) ? $config['path'] : $this->getAppPath();

        return $result;
    }

    protected function loadContainerConfiguration()
    {
        parent::loadContainerConfiguration();

        $config = $this->getGuestAppConfig();

        foreach ($config as $key => $value) {
            $this->getContainer()->setParameter('app.' . $key, $value);
        }

        $configPath = $this->getConfigPath();
        $environment = $this->getEnvironment();

        if (isset($config['config'])) {
            $activeAppConfigPath = $this->getGuestAppConfigPath();

            $activeAppConfigLoader = new YamlFileLoader($this->getContainer(), new FileLocator($activeAppConfigPath));

            if (file_exists($activeAppConfigPath . '/' . $config['config'])) {
                $activeAppConfigLoader->load($config['config']);
            }
        }

        $localConfigLoader = new YamlFileLoader($this->getContainer(), new FileLocator($configPath));

        if (file_exists($configPath . '/' . $environment . '.' . $config['label'] . '.yml')) {
            $localConfigLoader->load($environment . $config['label'] . '.yml');
        }

        if (file_exists($configPath . '/' . $environment . '.' . $config['label'] . '.local.yml')) {
            $localConfigLoader->load($environment . $config['label'] . '.local.yml');
        }
    }

    protected function getApplication()
    {
        $this->boot();

        $guestApp = $this->getGuestAppInstance();

        if ($guestApp instanceof App) {
            $guestApp->setContainer($this->getContainer());
        } else {
            throw new Exception('Guest app must be an instance of \Symlex\Bootstrap\App');
        }

        $this->configureGuestApp($guestApp);

        return $guestApp;
    }
}
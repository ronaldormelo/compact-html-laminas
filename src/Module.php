<?php

namespace CompactHtml;
use Laminas\Mvc\MvcEvent;

class Module
{
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    /**
     * onBootstrap() is called once all modules are initialized.
     *
     * @param MvcEvent $event
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function onBootstrap(MvcEvent $event)
    {
        $eventManager = $event->getApplication()->getEventManager();
        $serviceManager = $event->getApplication()->getServiceManager();
        $config = $serviceManager->get('config');

        if(!isset($config['compact-html'])) {

            throw new Exception\RuntimeException('Unable to load configuration; did you forget to create compact-html.global.php ?');
        }

        $listener = new \CompactHtml\CompactHtmlListener($config['compact-html']);

        $listener->attach($eventManager);
    }
}

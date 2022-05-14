<?php

namespace CompactHtml;

use Estrutura\Helpers\Cript;
use Laminas\EventManager\EventInterface;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\EventManager\ListenerAggregateTrait;

class CompactHtmlListener implements ListenerAggregateInterface
{
    use ListenerAggregateTrait;

    /**
     * CompactHtml configuration
     */
    private $config;

    /**
     * @var
     */
    private $referenciaHash;

    /**
     * @var
     */
    private $isCachable;

    /**
     * @param $config
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * @param EventManagerInterface $events
     * @param $priority
     * @return void
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        if (!$this->config['enabled']) {

            return;
        }

        // Do not execute anything in console mode, since there is no need to.
        if (php_sapi_name() == "cli") {
            return;
        }

        if ($this->config['cache']) {

            $this->listeners[] = $events->attach('route', [$this, 'getCache'], -1000);
        }

        $this->listeners[] = $events->attach('finish', [$this, 'onFinish'], 100);

    }

    /**
     * @param EventManagerInterface $events
     * @return void
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {
            $events->detach($listener);
            unset($this->listeners[$index]);
        }
    }

    /**
     * @param EventInterface $e
     * @return void
     */
    public function getCache(EventInterface $e)
    {
        $this->isCachable = true;

        $controller = $e->getTarget();

        if (($controller->getRequest() instanceof \Laminas\Console\Request) && $controller->getRequest()->getMethod() != 'GET') {

            $this->isCachable = false;
            return;
        }

        $route = $e->getRouteMatch()->getParams();

        if ($this->config['cache-options']['list-route-controller']['allow'] == 'none') {

            if (!(($controller->getRequest() instanceof \Laminas\Console\Request) ||
                (in_array($route['controller'], $this->config['cache-options']['list-route-controller']['except'])))) {

                $this->isCachable = false;
                return;
            }
        } else {

            if (($controller->getRequest() instanceof \Laminas\Console\Request) ||
                (in_array($route['controller'], $this->config['cache-options']['list-route-controller']['except']))) {

                $this->isCachable = false;
                return;
            }
        }

        $referencia = '';

        if (isset($route['controller'])) {

            $referencia = ('/' . $route['controller']);
            if (isset($route['action'])) {

                $referencia .= ('/' . $route['action']);

                if (isset($route['id'])) {

                    $referencia .= ('/' . $route['id']);
                }
            }
        }

        if ($referencia && !($controller->getRequest() instanceof \Laminas\Console\Request)) {

            $this->referenciaHash = md5(json_encode($controller->getRequest()->getPost()->toArray()) . $referencia);
        }

        $serviceManager = $e->getApplication()->getServiceManager();
        $auth = $serviceManager->get('Auth\Service\AuthService')->authentication()->getStorage()->read();

        if ($auth) {

            $cacheHash = $this->referenciaHash .
                Cript::enc($auth->id_escritorio) .
                Cript::enc($auth->id_usuario) .
                md5(json_encode($auth->busca));
        } else {

            $cacheHash = $this->referenciaHash;
        }

        if ($this->config['cache-options']['type'] == 'redis') {

            $redis = new \Redis();
            $redis->connect($this->config['cache-options']['cache-redis-options']['host'], $this->config['cache-options']['cache-redis-options']['port']);
            $queryCache = $redis->get($cacheHash);
        } else {

            /* @var $cache \Laminas\Cache\Storage\Filesystem */
            $cache = $serviceManager->get('Laminas\Cache\Storage\Filesystem')->setOptions([
                'cacheDir' => $this->config['cache-options']['cache-storage-options']['cache-dir'],
                'ttl' => $this->config['cache-options']['cache-storage-options']['ttl'],
                'namespaceSeparator' => $this->config['cache-options']['cache-storage-options']['namespace'],
            ]);
            $queryCache = $cache->getItem($cacheHash);

        }

        if ($queryCache) {

            $response = $e->getResponse();
            $response->setContent($queryCache);
            return $response;
        }
    }

    public function onFinish(EventInterface $e)
    {
        $response = $e->getResponse();

        if (get_class($response) == 'Laminas\Console\Response') {

            return;
        }

        $contentTypeHeaders = $response->getHeaders()->get('content-type');

        if ($contentTypeHeaders &&
            (strpos($contentTypeHeaders->toString(), 'application/pdf') !== false ||
                strpos($contentTypeHeaders->toString(), 'image/png') !== false)) {

            $content = $response->getBody();
        } else {

            $content = preg_replace([
                '/\>[^\S ]+/s', // strip whitespaces after tags, except space
                '/[^\S ]+\</s', // strip whitespaces before tags, except space
//            '/<!--(.|\s)*?-->/', // Remove HTML comments
                '#(?://)?<![CDATA[(.*?)(?://)?]]>#s',
                '!\s+!', // shorten multiple whitespace sequences
            ], [
                '>',
                '<',
//            '',
                "//&lt;![CDATA[n" . '1' . "n//]]>",
                ' ',
            ], $response->getBody());

            if ($this->config['cache']) {

                if ($this->isCachable) {

                    $serviceManager = $e->getApplication()->getServiceManager();
                    $auth = $serviceManager->get('Auth\Service\AuthService')->authentication()->getStorage()->read();

                    if ($auth) {

                        $cacheHash = $this->referenciaHash .
                            Cript::enc($auth->id_escritorio) .
                            Cript::enc($auth->id_usuario) .
                            md5(json_encode($auth->busca));
                    } else {

                        $cacheHash = $this->referenciaHash;
                    }

                    if ($this->config['cache-options']['type'] == 'redis') {

                        $redis = new \Redis();
                        $redis->connect($this->config['cache-options']['cache-redis-options']['host'], $this->config['cache-options']['cache-redis-options']['port']);
                        $redis->setex($cacheHash, $this->config['cache-options']['cache-storage-options']['ttl'], $content);

                    } else {

                        /* @var $cache \Laminas\Cache\Storage\Filesystem */
                        $cache = $serviceManager->get('Laminas\Cache\Storage\Filesystem')->setOptions([
                            'namespaceSeparator' => '-pg-',
                            'cacheDir' => $this->config['cache-options']['cache-storage-options']['cache-dir'],
                            'ttl' => $this->config['cache-options']['cache-storage-options']['ttl'],
                            'namespaceSeparator' => $this->config['cache-options']['cache-storage-options']['namespace'],
                        ]);

                        $cache->addItem($cacheHash, $content);
                    }
                }
            }

            if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {

                if ($contentTypeHeaders &&
                    (strpos($contentTypeHeaders->toString(), 'gzip') === false)) {

                    header('Content-Encoding: gzip');
                    $content = gzencode($content, 9);
                }
            }
        }

        $response->setContent($content);
    }
}
<?php

namespace Hautelook\Solarium\Cache;

use Doctrine\Common\Cache\Cache;
use Solarium\Core\Event\Events;
use Solarium\Core\Event\PreCreateRequest as PreCreateRequestEvent;
use Solarium\Core\Event\PreExecuteRequest as PreExecuteRequestEvent;
use Solarium\Core\Event\PostExecuteRequest as PostExecuteRequestEvent;
use Solarium\Core\Plugin\Plugin;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 */
class CachePlugin extends Plugin
{
    // We cannot use constructor injection because the PluginInterface contains a __construct ...

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var CacheProfile
     */
    private $currentRequestCacheProfile;

    public function setCache(Cache $cache)
    {
        $this->cache = $cache;
    }

    protected function initPluginType()
    {
        $dispatcher = $this->client->getEventDispatcher();
        $dispatcher->addListener(Events::PRE_CREATE_REQUEST, array($this, 'onPreCreateRequest'));

        // Should be called before load balancer
        $dispatcher->addListener(Events::PRE_EXECUTE_REQUEST, array($this, 'onPreExecuteRequest'), 100);

        $dispatcher->addListener(Events::POST_EXECUTE_REQUEST, array($this, 'onPostExecuteRequest'));
    }
    
    public function isSelectHandler($handler) 
    {
        return strpos(strtolower($handler), 'select') === 0;
    }

    public function onPreCreateRequest(PreCreateRequestEvent $event)
    {
        $query = $event->getQuery();

        if (null === $query->getOption('cache_lifetime')) {
            $this->currentRequestCacheProfile = null;

            return;
        }

        $this->currentRequestCacheProfile = new CacheProfile(
            $query->getOption('cache_key'),
            $query->getOption('cache_lifetime')
        );
    }

    public function onPreExecuteRequest(PreExecuteRequestEvent $event)
    {
        if (null === $this->currentRequestCacheProfile) {
            return;
        }
        
        if (!$this->isSelectHandler($event->getRequest()->getHandler())) {
            return;
        }

        if (null === $this->currentRequestCacheProfile->getKey()) {
            $this->currentRequestCacheProfile->setKey(
                'solr_' . sha1($event->getRequest()->getUri() . $event->getRequest()->getRawData())
            );
        }
        $key = $this->currentRequestCacheProfile->getKey();

        if (false === $serializedResponse = $this->getCache()->fetch($key)) {
            return;
        }

        if (false === $response = unserialize($serializedResponse)) {
            return;
        }

        $event->setResponse($response);
        $event->stopPropagation();

        // Make sure we do not save the $response in the cache later
        $this->currentRequestCacheProfile = null;
    }

    public function onPostExecuteRequest(PostExecuteRequestEvent $event)
    {
        if (null === $this->currentRequestCacheProfile) {
            return;
        }
        
        if (!$this->isSelectHandler($event->getRequest()->getHandler())) {
            return;
        }

        $this->getCache()->save(
            $this->currentRequestCacheProfile->getKey(),
            serialize($event->getResponse()),
            $this->currentRequestCacheProfile->getLifetime()
        );
    }

    private function getCache()
    {
        if (null === $this->cache) {
            throw new \RuntimeException('The CachePlugin cache was not set.');
        }

        return $this->cache;
    }
}

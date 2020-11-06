<?php

namespace Hautelook\Solarium\Cache;

use Doctrine\Common\Cache\Cache;
use Solarium\Core\Client\Request;
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

        $logQueries = $query->getOption('log_queries');
        $logQueriesFilePath = $query->getOption('log_queries_file_path');

        $this->currentRequestCacheProfile = new CacheProfile(
            $query->getOption('cache_key'),
            $query->getOption('cache_lifetime'),
            $query->getOption('cache_key_priorities'),
            empty($logQueries) ? false : $logQueries,
            empty($logQueriesFilePath) ? null : $logQueriesFilePath
        );
    }

    private function getSerializedResponseByPriorityKeys($sha)
    {
        /**
         * key priorities is an array with available key prefixes ordered in a way that
         * bots key is first and auth user key is last.
         * If bot does not have cache entry we check if another higher priority key has it and we use the result.
         */
        if ($this->currentRequestCacheProfile->getKeyPriorities()) {
            $found = false;
            foreach ($this->currentRequestCacheProfile->getKeyPriorities() as $priorityKeyPrefix) {
                if (!$found) {
                    if (strpos($this->currentRequestCacheProfile->getKey(), $priorityKeyPrefix) === 0) {
                        $found = true;
                    }
                    continue;
                }
                $key = $priorityKeyPrefix . $sha;
                if (false !== $serializedResponse = $this->getCache()->fetch($key)) {
                    return $serializedResponse;
                }
            }
        }

        return false;
    }

    public function onPreExecuteRequest(PreExecuteRequestEvent $event)
    {
        if (null === $this->currentRequestCacheProfile) {
            return;
        }

        if (!$this->isSelectHandler($event->getRequest()->getHandler())) {
            return;
        }

        if ($this->currentRequestCacheProfile->getLogQueries()) {
            $this->logQuery($event->getRequest(), $this->currentRequestCacheProfile->getLogFilePath());
        }

        $keyPrefix = null === $this->currentRequestCacheProfile->getKey()
            ? ''
            : $this->currentRequestCacheProfile->getKey();
        $sha = sha1($event->getRequest()->getUri() . $event->getRequest()->getRawData());
        $this->currentRequestCacheProfile->setKey(
            $keyPrefix . $sha
        );

        $key = $this->currentRequestCacheProfile->getKey();

        if (false === $serializedResponse = $this->getCache()->fetch($key)) {
            if (false === $serializedResponse = $this->getSerializedResponseByPriorityKeys($sha)) {
                return;
            }
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

    private function logQuery(Request $solrRequest, $logFilePath)
    {
        $data = $solrRequest->getUri()
            . $solrRequest->getQueryString()
            . ','
            . $solrRequest->getRawData()
            . PHP_EOL;
        file_put_contents($logFilePath, $data, FILE_APPEND);
    }
}

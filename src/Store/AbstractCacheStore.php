<?php
/**
 * This file is a part of "comely-io/cache" package.
 * https://github.com/comely-io/io/cache"
 *
 * Copyright (c) Furqan A. Siddiqui <hello@furqansiddiqui.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code or visit following link:
 * https://github.com/comely-io/io/cache/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Comely\Cache\Store;

use Comely\Cache\Exception\CacheException;
use Comely\Cache\Servers\CacheServer;

/**
 * Class AbstractCacheStore
 * @package Comely\Cache\Store
 */
abstract class AbstractCacheStore implements CacheStoreInterface, \Serializable
{
    public const ENGINE = null;

    /** @var CacheServer */
    protected $server;

    /**
     * AbstractCacheStore constructor.
     * @param CacheServer $server
     */
    public function __construct(CacheServer $server)
    {
        $this->server = $server;
    }

    /**
     * @return string|null
     */
    public function engine(): ?string
    {
        return static::ENGINE;
    }

    /**
     * @throws CacheException
     */
    final public function __clone()
    {
        throw new CacheException('Cache store instances cannot be cloned');
    }

    /**
     * @return string|void
     * @throws CacheException
     */
    final public function serialize()
    {
        throw new CacheException('Cache store instances cannot be serialized');
    }

    /**
     * @param string $serialized
     * @throws CacheException
     */
    final public function unserialize($serialized)
    {
        throw new CacheException('Cache store instances cannot be serialized');
    }
}
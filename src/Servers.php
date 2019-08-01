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

namespace Comely\Cache;

use Comely\Cache\Servers\CacheServer;

/**
 * Class Servers
 * @package Comely\Cache
 */
class Servers implements \Iterator, \Countable
{
    /** @var Cache */
    private $cache;
    /** @var array */
    private $servers;
    /** @var int */
    private $count;
    /** @var int */
    private $index;

    /**
     * Servers constructor.
     * @param Cache $cache
     */
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
        $this->servers = [];
        $this->count = 0;
        $this->index = 0;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * @param string $type
     * @param string $host
     * @param int $port
     * @param int|null $timeOut
     * @return Servers
     */
    public function add(string $type, string $host, int $port, ?int $timeOut = null): self
    {
        if (!in_array($type, Cache::STORES)) {
            throw new \InvalidArgumentException('Invalid cache server type');
        }

        $server = new CacheServer();
        $server->type = $type;
        $server->hostname = $host;
        $server->port = $port;
        $server->timeOut = $timeOut;

        $this->servers[] = $server;
        $this->count++;
        return $this;
    }

    /**
     * @param string $host
     * @param int $port
     * @param int|null $timeOut
     * @return Servers
     */
    public function redis(string $host, ?int $port = null, ?int $timeOut = null): self
    {
        if (!$port) {
            $port = 6379;
        }

        return $this->add(Cache::REDIS, $host, $port, $timeOut);
    }

    /**
     * @param string $host
     * @param int|null $port
     * @param int|null $timeOut
     * @return Servers
     */
    public function memcached(string $host, ?int $port = null, ?int $timeOut = null): self
    {
        if (!$port) {
            $port = 11211;
        }

        return $this->add(Cache::MEMCACHED, $host, $port, $timeOut);
    }

    /**
     * @return void
     */
    public function rewind(): void
    {
        $this->index = 0;
    }

    /**
     * @return void
     */
    public function next(): void
    {
        ++$this->index;
    }

    /**
     * @return CacheServer
     */
    public function current(): CacheServer
    {
        return $this->servers[$this->index];
    }

    /**
     * @return int
     */
    public function key(): int
    {
        return $this->index;
    }

    /**
     * @return bool
     */
    public function valid(): bool
    {
        return isset($this->servers[$this->index]);
    }
}
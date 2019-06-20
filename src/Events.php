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

use Comely\Utils\Events\Event;
use Comely\Utils\Events\EventsRegister;

/**
 * Class Events
 * @package Comely\Cache
 */
class Events
{
    /** @var EventsRegister */
    private $register;

    /**
     * Events constructor.
     */
    public function __construct()
    {
        $this->register = new EventsRegister();
    }

    /**
     * @return Event
     */
    public function onStored(): Event
    {
        return $this->register->on("cache_on_set");
    }

    /**
     * @return Event
     */
    public function onDelete(): Event
    {
        return $this->register->on("cache_on_del");
    }

    /**
     * @return Event
     */
    public function onFlush(): Event
    {
        return $this->register->on("cache_on_flush");
    }
}
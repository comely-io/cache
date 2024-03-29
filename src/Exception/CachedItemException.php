<?php
/*
 * This file is a part of "comely-io/cache" package.
 * https://github.com/comely-io/cache
 *
 * Copyright (c) Furqan A. Siddiqui <hello@furqansiddiqui.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code or visit following link:
 * https://github.com/comely-io/cache/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Comely\Cache\Exception;

/**
 * Class CachedItemException
 * @package Comely\Cache\Exception
 */
class CachedItemException extends CacheException
{
    public const IS_EXPIRED = 0x64;
    public const UNSERIALIZE_FAIL = 0xc8;
}

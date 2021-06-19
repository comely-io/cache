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

namespace Comely\Cache\Pool;

/**
 * Class BulkOp
 * @package Comely\Cache\Pool
 */
class BulkCacheOp
{
    /** @var int */
    public int $total = 0;
    /** @var int */
    public int $success = 0;
    /** @var int */
    public int $fails = 0;
    /** @var array */
    public array $errors = [];
}

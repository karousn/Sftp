<?php

/*
 * This file is part of the UCSDMath package.
 *
 * (c) UCSD Mathematics | Math Computing Support <mathhelp@math.ucsd.edu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace UCSDMath\Sftp;

/**
 * SftpInterface is the interface implemented by all Sftp classes.
 *
 * @author Daryl Eisner <deisner@ucsd.edu>
 */
interface SftpInterface
{
    /**
     * Constants.
     */
    const REQUIRED_PHP_VERSION = '7.0.0';
    const DEFAULT_CHARSET = 'UTF-8';
    const SOURCE_LOCAL_FILE = 1;
    const SOURCE_CALLBACK = 16;
    const SOURCE_STRING = 2;
}

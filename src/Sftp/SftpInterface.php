<?php

/*
 * This file is part of the UCSDMath package.
 *
 * (c) 2015-2016 UCSD Mathematics | Math Computing Support <mathhelp@math.ucsd.edu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace UCSDMath\Sftp;

/**
 * SftpInterface is the interface implemented by all Sftp classes.
 *
 * Method list: (+) @api.
 *
 * @author Daryl Eisner <deisner@ucsd.edu>
 *
 * @api
 */
interface SftpInterface
{
    /**
     * Constants.
     *
     * @var string FRAMEWORK_MINIMUM_PHP The framework's minimum supported PHP version
     * @var string DEFAULT_CHARSET       The character encoding for the system
     * @var string CRLF                  The carriage return line feed
     * @var bool   REQUIRE_HTTPS         The secure setting TLS/SSL site requirement
     * @var string DEFAULT_TIMEZONE      The local timezone for the server (or set in ini.php)
     * @var int    SOURCE_LOCAL_FILE     The common stream source as local file (type)
     * @var int    SOURCE_CALLBACK       The common stream source as a callback (type)
     * @var int    SOURCE_STRING         The common stream source as inline string (type)
     */
    const FRAMEWORK_MINIMUM_PHP = '7.0.0';
    const DEFAULT_CHARSET       = 'UTF-8';
    const CRLF                  = "\r\n";
    const REQUIRE_HTTPS         = true;
    const DEFAULT_TIMEZONE      = 'America/Los_Angeles';
    const SOURCE_LOCAL_FILE     = 1;
    const SOURCE_CALLBACK       = 16;
    const SOURCE_STRING         = 2;

    //--------------------------------------------------------------------------
}

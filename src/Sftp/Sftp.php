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
 * Sftp is the default implementation of {@link SftpInterface} which
 * provides routine sftp methods that are commonly used throughout the framework.
 *
 * Method list: (+) @api, (-) protected or private visibility.
 *
 *
 * @author Daryl Eisner <deisner@ucsd.edu>
 *
 * @api
 */
class Sftp extends AbstractSftp implements SftpInterface
{
    /**
     * Constants.
     *
     * @var string VERSION  A version number
     *
     * @api
     */
    const VERSION = '1.6.0';

    // --------------------------------------------------------------------------

    /**
     * Properties.
     */

    // --------------------------------------------------------------------------

    /**
     * Constructor.
     *
     * @api
     */
    public function __construct()
    {
        parent::__construct();
    }
}

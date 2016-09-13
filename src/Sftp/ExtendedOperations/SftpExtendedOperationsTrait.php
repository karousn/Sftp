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

namespace UCSDMath\Sftp\ExtendedOperations;

use UCSDMath\Sftp\SftpInterface;

/**
 * SftpExtendedOperationsTrait is the default implementation of {@link SftpExtendedOperationsTraitInterface} which
 * provides routine Sftp methods that are commonly used in the framework.
 *
 * {@link SftpExtendedOperationsTrait} is a trait method implimentation requirement used in this framework.
 * This set is specifically used in Sftp classes.
 *
 * use UCSDMath\Sftp\ExtendedOperations\SftpExtendedOperationsTrait;
 * use UCSDMath\Sftp\ExtendedOperations\SftpExtendedOperationsTraitInterface;
 *
 * Method list: (+) @api, (-) protected or private visibility.
 *
 * (+) SftpInterface touch(string $path);
 * (+) bool toBoolean($trialBool = null);
 * (+) array getStat(string $remoteFileName);
 * (+) array getLstat(string $remoteFileName);
 * (+) array getLs(string $absolutePath = null);
 * (+) string downloadString(string $absolutePathRemoteFile);
 * (+) SftpInterface uploadString(string $absolutePathRemoteFile, string $str);
 *
 * SftpExtendedOperationsTrait provides a common set of implementations where needed. The SftpExtendedOperationsTrait
 * trait and the SftpExtendedOperationsTraitInterface should be paired together.
 *
 * @author Daryl Eisner <deisner@ucsd.edu>
 */
trait SftpExtendedOperationsTrait
{
    /**
     * Properties.
     */

    //--------------------------------------------------------------------------

    /**
     * Abstract Method Requirements.
     */

    //--------------------------------------------------------------------------

    /**
     * List all files in a directory.
     *
     * @internal this is like '/bin/ls' on unix, but returned as array.
     *
     * @param string $absolutePath The directory name preference
     *
     * @return array
     *
     * @api
     */
    public function getLs(string $absolutePath = null): array
    {
        $theDirectoryFiles = (!is_null($absolutePath))
            ? $this->changeDirectory($absolutePath)->netSftp->nlist()
            : $this->netSftp->nlist();

        return $theDirectoryFiles;
    }

    //--------------------------------------------------------------------------

    /**
     * Return specific file information on remote host.
     *
     * @param string $remoteFileName The relative or absolute remote filename
     *
     * @return array The associative arrays with misc information about the files
     *
     * @api
     */
    public function getStat(string $remoteFileName): array
    {
        return $this->netSftp->stat($remoteFileName);
    }

    //--------------------------------------------------------------------------

    /**
     * Return specific file information on remote host.
     *
     * @param string $remoteFileName The relative or absolute remote filename
     *
     * @return array The associative arrays with misc information about the files
     *
     * @api
     */
    public function getLstat(string $remoteFileName): array
    {
        return $this->netSftp->lstat($remoteFileName);
    }

    //--------------------------------------------------------------------------

    /**
     * Update the access/modification date of a file or directory (touch).
     *
     * @param string $path The relative or absolute filename
     *
     * @return SftpInterface The current instance
     *
     * @api
     */
    public function touch(string $path): SftpInterface
    {
        $this->netSftp->touch($path);

        return $this;
    }

    //--------------------------------------------------------------------------

    /**
     * Upload string-to-file.
     *
     * @param string $absolutePathRemoteFile The absolute path to remote file (new)
     * @param string $str                    The variable string
     *
     * @return SftpInterface The current instance
     *
     * @api
     */
    public function uploadString(string $absolutePathRemoteFile, string $str): SftpInterface
    {
        $this->netSftp->put($absolutePathRemoteFile, $str);

        return $this;
    }

    //--------------------------------------------------------------------------

    /**
     * Download file-to-string.
     *
     * @param string $absolutePathRemoteFile The absolute path to remote file
     *
     * @return string
     *
     * @api
     */
    public function downloadString(string $absolutePathRemoteFile): string
    {
        return $this->netSftp->get($absolutePathRemoteFile);
    }

    //--------------------------------------------------------------------------

    /**
     * Boolean checker and parser.
     *
     * May help on configuration or ajax files.
     *
     * @param mixed $trialBool The possible boolean value
     *
     * @return bool The defined input as boolean
     *
     * @api
     */
    public function toBoolean($trialBool = null): bool
    {
        /* String to boolean.
         *
         * 'true' === true      'false' === false
         * '1'    === true      '0'     === false
         *  1     === true       0      === false
         * 'yes'  === true      'no'    === false
         * 'on'   === true      'off'   === false
         *                      ''      === false
         *                      null    === false
         */
        return filter_var($trialBool, FILTER_VALIDATE_BOOLEAN);
    }

    //--------------------------------------------------------------------------
}
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
 * Method list:
 *
 * @method SftpInterface __construct();
 * @method void __destruct();
 * @method getPath();
 * @method renameFile($absolutePath_old, $absolutePath_new);
 * @method renameDirectory($absolutePath_old, $absolutePath_new);
 * @method touch($absolutePath);
 * @method uploadString($absolutePath_remoteFile, $str);
 * @method downloadString($absolutePath_remoteFile);
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
    const VERSION = '1.4.0';

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

    // --------------------------------------------------------------------------

    /**
     * Get directory path.
     *
     * @return string
     *
     * @api
     */
    public function getPath()
    {
        return $this->sftp->pwd();
    }

    // --------------------------------------------------------------------------

    /**
     * Rename a file or directory.
     *
     * @param  string $absolutePath_old  A absolute path to file (in old name)
     * @param  string $absolutePath_new  A absolute path to file (in new name)
     *
     * @api
     */
    public function renameFile($absolutePath_old, $absolutePath_new)
    {
        $this->sftp->rename($absolutePath_old, $absolutePath_new);

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Rename a file or directory.
     *
     * @param  string $absolutePath_old  A absolute path to file (in old name)
     * @param  string $absolutePath_new  A absolute path to file (in new name)
     *
     * @api
     */
    public function renameDirectory($absolutePath_old, $absolutePath_new)
    {
        $this->sftp->rename($absolutePath_old, $absolutePath_new);

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * List all files in a directory.
     *
     * @internal this is like '/bin/ls' on unix, but returned as array.
     *
     * @param  string $absolutePath  A directory name preference
     *
     * @return array
     *
     * @throws \throwInvalidArgumentExceptionError if $absolutePath is not a string/null
     *
     * @api
     */
    public function ls($absolutePath = null)
    {
        if (! is_null($absolutePath)) {
            $theOldDirectoryPath = $this->pwd();
            $this->changeToDirectory($absolutePath);

            $theDirectoryFiles = $this->sftp->nlist();
            $this->changeToDirectory($theOldDirectoryPath);

        } else {
            $theDirectoryFiles = $this->sftp->nlist();
        }

        return $theDirectoryFiles;

    }

    // --------------------------------------------------------------------------

    /**
     * Return specific file information on remote host.
     *
     * @return array
     *
     * @api
     */
    public function stat($remoteFileName)
    {

        return $this->sftp->stat($remoteFileName);

    }

    // --------------------------------------------------------------------------

    /**
     * Return specific file information on remote host.
     *
     * @return array
     *
     * @api
     */
    public function lstat($remoteFileName)
    {
        return $this->sftp->lstat($remoteFileName);
    }

    // --------------------------------------------------------------------------

    /**
     * Update the access/modification date of a file or directory (touch).
     *
     * @param  string $absolutePath  A relative or absolute filename
     *
     * @return int|bool  (bool on error)
     *
     * @throws \throwInvalidArgumentExceptionError if $remoteFileName is not a defined string
     *
     * @api
     */
    public function touch($absolutePath)
    {
        $this->sftp->touch($absolutePath);

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Upload string-to-file.
     *
     * @param  string $absolutePath_remoteFile  A absolute path to remote file (new)
     * @param  string $str                      A variable string
     *
     * @return SftpInterface
     *
     * @api
     */
    public function uploadString($absolutePath_remoteFile, $str)
    {
        $this->sftp->put($absolutePath_remoteFile, $str);

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Download file-to-string.
     *
     * @param  string $absolutePath_remoteFile  A absolute path to remote file
     *
     * @return SftpInterface
     *
     * @api
     */
    public function downloadString($absolutePath_remoteFile)
    {
        return $this->sftp->get($absolutePath_remoteFile);
    }
}

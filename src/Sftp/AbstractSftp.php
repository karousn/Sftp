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

use phpseclib\Net\SCP;
use phpseclib\Net\SSH2;
use phpseclib\Net\SFTP as NetSftp;
use UCSDMath\Functions\ServiceFunctions;
use UCSDMath\Functions\ServiceFunctionsInterface;
use UCSDMath\DependencyInjection\ServiceRequestContainer;

/**
 * AbstractSftp provides an abstract base class implementation of {@link SftpInterface}.
 * Primarily, this services the fundamental implementations for all Sftp classes.
 *
 * This component library is used to service provides secure file access, file transfer,
 * and file management functionalities over any reliable data streams. SFTP is an
 * extension of the Secure Shell protocol (SSH). This is an adapter to the phpseclib
 * library suite.
 *
 * Method list:
 *
 * @method SftpInterface __construct();
 * @method void __destruct();
 * @method connect(array $accountCredentials = null);
 * @method uploadFile($absolutePath_remoteFile, $absolutePath_localFile);
 * @method deleteFile($absolutePath);
 * @method getFileSize($absolutePath);
 * @method downloadFile($absolutePath_remoteFile, $absolutePath_localFile);
 *
 * @method createDirectory($absolutePath);
 * @method deleteDirectory($absolutePath, $recursive = false);
 * @method changeDirectory($absolutePath);
 *
 * @method chmod($mode, $absolutePath, $recursive = false);
 * @method appendToStorageRegister(array $arraySubset);
 *
 * @method toBoolean($trialBool = null);
 * @method logError($method, $message, $trace);
 * @method isValidFtpAccountCredentials(array $accountCredentials);
 *
 * @author Daryl Eisner <deisner@ucsd.edu>
 */
abstract class AbstractSftp implements SftpInterface, ServiceFunctionsInterface
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
     *
     * @var    array         $storageRegister  A set of validation stored data elements
     * @static SftpInterface $instance         A SftpInterface instance
     * @static integer       $objectCount      A SftpInterface instance count
     */
    protected $sftp = null;
    protected $storageRegister = array();
    protected $requiredFtpAccountCredentials = [
        'id',
        'uuid',
        'date',
        'is_encrypted',
        'account_host',
        'account_options',
        'account_username',
        'account_password',
        'default_directory',
        'is_secure_connection'
    ];
    protected static $instance = null;
    protected static $objectCount = 0;

    // --------------------------------------------------------------------------

    /**
     * Constructor.
     *
     * @api
     */
    public function __construct()
    {
        static::$instance = $this;
        static::$objectCount++;
    }

    // --------------------------------------------------------------------------

    /**
     * Destructor.
     *
     * @api
     */
    public function __destruct()
    {
        static::$objectCount--;
    }

    // --------------------------------------------------------------------------

    /**
     * Boolean checker and parser.
     *
     * May help on configuration or ajax files.
     *
     * @param  mixed $trialBool  A possible boolean value
     *
     * @return bool
     *
     * @api
     */
    public function toBoolean($trialBool = null)
    {
        /** String to boolean.
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

    // --------------------------------------------------------------------------

    /**
     * Connect to remote account.
     *
     * @param  array $accountCredentials  A list of remote account credentials
     *
     * @return SftpInterface
     *
     * @throws \InvalidArgumentException if remote connection not established
     */
    public function connect(array $accountCredentials)
    {
        /**
         * Note: on a successful login, the default directory
         * is the home directory (e.g., /home/wwwdyn).
         */
        $this->isValidFtpAccountCredentials($accountCredentials)
            ? $this->appendToStorageRegister($accountCredentials)
            : $this->logError('AbstractSftp::connect()', 'invalid account credentials', 'E076');

        $this->sftp = new NetSftp($this->get('account_host'));
        $this->sftp->login($this->get('account_username'), $this->get('account_password'));

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Log errors to system_logs table.
     *
     * @param  array $method   A method name
     * @param  array $message  A error message
     * @param  array $trace    A unique trace key
     *
     * @return null
     */
    protected function logError($method, $message, $trace)
    {
        ServiceRequestContainer::init()
            ->get('Database')
                ->insertiNetRecordLog(
                    ServiceRequestContainer::init()
                        ->get('Session')
                            ->getPassport('email'),
                    sprintf('-- SFTP Error: %s - [ %s ] [ %s ]', $method, $message, $trace)
                );
    }

    // --------------------------------------------------------------------------

    /**
     * Validate SFTP Account Credentials.
     *
     * @param  array $accountCredentials  A list of remote account credentials
     *
     * @return bool
     */
    protected function isValidFtpAccountCredentials(array $accountCredentials)
    {
        /**
         * Intersect the targets with the haystack and make sure
         * the intersection is precisely equal to the targets.
         */
        return count(array_intersect(
            array_keys($accountCredentials),
            $this->requiredFtpAccountCredentials)
        ) === count($this->requiredFtpAccountCredentials);
    }

    // --------------------------------------------------------------------------

    /**
     * Change directory.
     *
     * @param  string $absolutePath  A directory name preference
     *
     * @return SftpInterface
     *
     * @throws \throwInvalidArgumentExceptionError if $absolutePath is not a defined string
     * @throws \logError if path does not exist on remote host
     *
     * @api
     */
    public function changeDirectory($absolutePath)
    {
        $this->sftp->chdir($absolutePath);

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Create a new directory on the remote host.
     *
     * @return bool  (if created 'true'; if already exists 'false')
     *
     * @api
     */
    public function createDirectory($absolutePath)
    {
        /* Requires absolute PATH. */
        $this->changeDirectory(dirname($absolutePath));
        $this->sftp->mkdir(basename($absolutePath));

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Delete directory and all its contents.
     *
     * @param  string $absolutePath  A absolute path to directory
     * @param  string $recursive     A option to delete recursively
     *
     * @api
     */
    public function deleteDirectory($absolutePath, $recursive = false)
    {
        /* Requires absolute PATH. */
        $this->changeDirectory(dirname($absolutePath));
        $theDirectoryToRemove = basename($absolutePath);
        $this->sftp->delete($theDirectoryToRemove, $this->toBoolean($recursive));

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Return file size.
     *
     * @param  string $absolutePath  A relative or absolute filename
     *
     * @return int|bool  (bool on error)
     *
     * @throws \throwInvalidArgumentExceptionError if $absolutePath is not a defined string
     *
     * @api
     */
    public function getFileSize($absolutePath)
    {
        return $this->sftp->size($absolutePath);
    }

    // --------------------------------------------------------------------------

    /**
     * Upload file to remote account.
     *
     * @param  string $absolutePath_remoteFile  A absolute path to remote file (new)
     * @param  string $absolutePath_localFile   A absolute path to local file
     *
     * @return SftpInterface
     *
     * @api
     */
    public function uploadFile($absolutePath_remoteFile, $absolutePath_localFile)
    {
        if (file_exists($absolutePath_localFile) && is_readable($absolutePath_localFile)) {
            /**
             *  Common stream source types:
             *    - static::SOURCE_STRING
             *    - static::SOURCE_CALLBACK
             *    - static::SOURCE_LOCAL_FILE
             */
            $this->sftp->put($absolutePath_remoteFile, $absolutePath_localFile, static::SOURCE_LOCAL_FILE);

        } else {
            $this->logError(
                'AbstractSftp::uploadFile()',
                'cannot read/find local file (check local path): '. $absolutePath_localFile,
                'E085'
            );
        }

        if ($this->getFileSize($absolutePath_remoteFile) !== filesize($absolutePath_localFile)) {
            $this->logError(
                'AbstractSftp::uploadFile()',
                'remote/local file size != : '. $remote_file_size.'/'.$local_file_size,
                'E086'
            );
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Delete remote file.
     *
     * @param  string $absolutePath  A absolute path to remote file
     *
     * @api
     */
    public function deleteFile($absolutePath)
    {
        $this->sftp->delete($absolutePath);

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Download file from remote account.
     *
     * @param  string $absolutePath_remoteFile  A absolute path to remote file (new)
     * @param  string $absolutePath_localFile   A absolute path to local file
     *
     * @return SftpInterface
     *
     * @api
     */
    public function downloadFile($absolutePath_remoteFile, $absolutePath_localFile)
    {
        $this->sftp->get($absolutePath_remoteFile, $absolutePath_localFile);

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Change remote file permissions (files and directories).
     *
     * @param  string $mode          A permissions mode
     * @param  string $absolutePath  A filename or directory path
     * @param  bool   $recursive     A recursive delete option
     *
     * @return SftpInterface
     *
     * @throws \throwInvalidArgumentExceptionError if $mode is not a defined string
     * @throws \throwInvalidArgumentExceptionError if $absolutePath is not a defined string
     *
     * @api
     */
    public function chmod($mode, $absolutePath, $recursive = false)
    {
        /**
         * Example: $sftp->chmod(0777, '/home/link/public_html', true);
         */
        $this->changeDirectory(dirname($absolutePath));
        $this->sftp->chmod($mode, basename($absolutePath), $this->toBoolean($recursive));

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Combine storageRegister with associated array.
     *
     * @param  array $arraySubset  A array list
     *
     * @return SftpInterface
     *
     * @throws \InvalidArgumentException if the property is not defined
     */
    protected function appendToStorageRegister(array $arraySubset = null)
    {
        if (null === $arraySubset) {
            throw new \InvalidArgumentException(sprintf(
                'You must provide a valid array with some data subset. No data was given for %s. - %s',
                '$this->arraySubset',
                '[fact-A505]'
            ));
        }

        /**
         * Merge both registers and apply the overrides.
         */
        $this->storageRegister = array_merge($this->storageRegister, $arraySubset);
        $arraySubset = array();

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Get directory path.
     *
     * @return string
     *
     * @api
     */
    public function getPwd()
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
    public function getLs($absolutePath = null)
    {
        if (! is_null($absolutePath)) {
            $theOldDirectoryPath = $this->pwd();
            $this->changeDirectory($absolutePath);

            $theDirectoryFiles = $this->sftp->nlist();
            $this->changeDirectory($theOldDirectoryPath);

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
    public function getStat($remoteFileName)
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
    public function getLstat($remoteFileName)
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

    // --------------------------------------------------------------------------

    /**
     * Method implementations inserted.
     *
     * The notation below illustrates visibility: (+) @api, (-) protected or private.
     *
     * @method all();
     * @method init();
     * @method get($key);
     * @method has($key);
     * @method version();
     * @method getClassName();
     * @method getConst($key);
     * @method set($key, $value);
     * @method isString($str);
     * @method getInstanceCount();
     * @method getClassInterfaces();
     * @method __call($callback, $parameters);
     * @method getProperty($name, $key = null);
     * @method doesFunctionExist($functionName);
     * @method isStringKey($str, array $keys);
     * @method throwExceptionError(array $error);
     * @method setProperty($name, $value, $key = null);
     * @method throwInvalidArgumentExceptionError(array $error);
     */
    use ServiceFunctions;
}

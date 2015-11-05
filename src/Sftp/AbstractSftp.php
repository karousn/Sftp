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
 * @method getPwd();
 * @method renameFile($absolutePath_old, $absolutePath_new);
 * @method renameDirectory($absolutePath_old, $absolutePath_new);
 * @method touch($absolutePath);
 * @method uploadString($absolutePath_remoteFile, $str);
 * @method downloadString($absolutePath_remoteFile);
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
     * @var    phpseclib\Net\SFTP  $netSftp          A set of validation stored data elements
     * @var    array               $storageRegister  A set of validation stored data elements
     * @static SftpInterface       $instance         A SftpInterface instance
     * @static integer             $objectCount      A SftpInterface instance count
     */
    protected $netSftp = null;
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

        list($account_host, $account_username, $account_password) = [
            $this->get('account_host'),
            $this->get('account_username'),
            $this->get('account_password')
        ];

        $this->setProperty('netSftp', new NetSftp($account_host));
        $this->netSftp->login($account_username, $account_password);

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Log errors to system_logs table.
     *
     * @param  string $method   A method name
     * @param  string $message  A error message
     * @param  string $trace    A unique trace key
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
     * @api
     */
    public function changeDirectory($absolutePath)
    {
        $this->netSftp->chdir($absolutePath);

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Create a new directory on the remote host.
     *
     * @param  string $absolutePath  A absolute path to directory
     *
     * @return SftpInterface
     *
     * @api
     */
    public function createDirectory($absolutePath)
    {
        /* Requires absolute PATH. */
        $this->changeDirectory(dirname($absolutePath));
        $this->netSftp->mkdir(basename($absolutePath));

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Delete directory and all its contents.
     *
     * @param  string $absolutePath  A absolute path to directory
     * @param  string $recursive     A option to delete recursively
     *
     * @return SftpInterface
     *
     * @api
     */
    public function deleteDirectory($absolutePath, $recursive = false)
    {
        /* Requires absolute PATH. */
        $this->changeDirectory(dirname($absolutePath));
        $theDirectoryToRemove = basename($absolutePath);
        $this->netSftp->delete($theDirectoryToRemove, $this->toBoolean($recursive));

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Return file size.
     *
     * @param  string $absolutePath  A relative or absolute filename
     *
     * @return int|bool
     *
     * @api
     */
    public function getFileSize($absolutePath)
    {
        return $this->netSftp->size($absolutePath);
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
            $this->netSftp->put($absolutePath_remoteFile, $absolutePath_localFile, static::SOURCE_LOCAL_FILE);

        } else {
            $this->logError('AbstractSftp::uploadFile()', 'cannot read/find local file (check local path): ' . $absolutePath_localFile, 'E085');
        }

        if ($this->getFileSize($absolutePath_remoteFile) !== filesize($absolutePath_localFile)) {
            $this->logError(
                'AbstractSftp::uploadFile()',
                'remote/local file size: ' . $this->getFileSize($absolutePath_remoteFile) . '/' . filesize($absolutePath_localFile),
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
     * @return SftpInterface
     *
     * @api
     */
    public function deleteFile($absolutePath)
    {
        $this->netSftp->delete($absolutePath);

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
        $this->netSftp->get($absolutePath_remoteFile, $absolutePath_localFile);

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
     * @api
     */
    public function chmod($mode, $absolutePath, $recursive = false)
    {
        /**
         * Example: $netSftp->chmod(0777, '/home/link/public_html', true);
         */
        $this->changeDirectory(dirname($absolutePath));
        $this->netSftp->chmod($mode, basename($absolutePath), $this->toBoolean($recursive));

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Combine storageRegister with associated array.
     *
     * @param  array $arraySubset  A array list
     *
     * @return SftpInterface
     */
    protected function appendToStorageRegister(array $arraySubset)
    {
        /* Merge both registers and apply the overrides. */
        $this->storageRegister = array_merge($this->storageRegister, $arraySubset);

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
        return $this->netSftp->pwd();
    }

    // --------------------------------------------------------------------------

    /**
     * Rename a file or directory.
     *
     * @param  string $absolutePath_old  A absolute path to file (in old name)
     * @param  string $absolutePath_new  A absolute path to file (in new name)
     *
     * @return SftpInterface
     *
     * @api
     */
    public function renameFile($absolutePath_old, $absolutePath_new)
    {
        $this->netSftp->rename($absolutePath_old, $absolutePath_new);

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Rename a file or directory.
     *
     * @param  string $absolutePath_old  A absolute path to file (in old name)
     * @param  string $absolutePath_new  A absolute path to file (in new name)
     *
     * @return SftpInterface
     *
     * @api
     */
    public function renameDirectory($absolutePath_old, $absolutePath_new)
    {
        $this->netSftp->rename($absolutePath_old, $absolutePath_new);

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
     * @api
     */
    public function getLs($absolutePath = null)
    {
        if (!is_null($absolutePath)) {
            $theOldDirectoryPath = $this->getPwd();
            $this->changeDirectory($absolutePath);

            $theDirectoryFiles = $this->netSftp->nlist();
            $this->changeDirectory($theOldDirectoryPath);

        } else {
            $theDirectoryFiles = $this->netSftp->nlist();
        }

        return $theDirectoryFiles;
    }

    // --------------------------------------------------------------------------

    /**
     * Return specific file information on remote host.
     *
     * @param  string $remoteFileName  A relative or absolute filename
     *
     * @return array
     *
     * @api
     */
    public function getStat($remoteFileName)
    {
        return $this->netSftp->stat($remoteFileName);
    }

    // --------------------------------------------------------------------------

    /**
     * Return specific file information on remote host.
     *
     * @param  string $remoteFileName  A relative or absolute filename
     *
     * @return array
     *
     * @api
     */
    public function getLstat($remoteFileName)
    {
        return $this->netSftp->lstat($remoteFileName);
    }

    // --------------------------------------------------------------------------

    /**
     * Update the access/modification date of a file or directory (touch).
     *
     * @param  string $path  A relative or absolute filename
     *
     * @return SftpInterface
     *
     * @api
     */
    public function touch($path)
    {
        $this->netSftp->touch($path);

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
        $this->netSftp->put($absolutePath_remoteFile, $str);

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Download file-to-string.
     *
     * @param  string $absolutePath_remoteFile  A absolute path to remote file
     *
     * @return string
     *
     * @api
     */
    public function downloadString($absolutePath_remoteFile)
    {
        return $this->netSftp->get($absolutePath_remoteFile);
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

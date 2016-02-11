<?php
declare(strict_types=1);

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
 * Method list: (+) @api, (-) protected or private visibility.
 *
 * (+) SftpInterface __construct();
 * (+) void __destruct();
 * (+) connect(array $accountCredentials = null);
 * (+) uploadFile($absolutePath_remoteFile, $absolutePath_localFile);
 * (+) deleteFile($absolutePath);
 * (+) getFileSize($absolutePath);
 * (+) downloadFile($absolutePath_remoteFile, $absolutePath_localFile);
 *
 * (+) createDirectory($absolutePath);
 * (+) deleteDirectory($absolutePath, $recursive = false);
 * (+) changeDirectory($absolutePath);
 *
 * (+) chmod($mode, $absolutePath, $recursive = false);
 * (+) appendToStorageRegister(array $arraySubset);
 *
 * (+) toBoolean($trialBool = null);
 * (+) logError($method, $message, $trace);
 * (+) isValidFtpAccountCredentials(array $accountCredentials);
 *
 * (+) getPwd();
 * (+) renameFile($absolutePath_old, $absolutePath_new);
 * (+) renameDirectory($absolutePath_old, $absolutePath_new);
 * (+) touch($absolutePath);
 * (+) uploadString($absolutePath_remoteFile, $str);
 * (+) downloadString($absolutePath_remoteFile);
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
    const VERSION = '1.6.0';

    // --------------------------------------------------------------------------

    /**
     * Properties.
     *
     * @var    phpseclib\Net\SFTP  $netSftp          A set of validation stored data elements
     * @var    array               $storageRegister  A set of validation stored data elements
     * @static SftpInterface       $instance         A SftpInterface
     * @static int                 $objectCount      A SftpInterface count
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
     * @param mixed $trialBool  A possible boolean value
     *
     * @return bool
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

    // --------------------------------------------------------------------------

    /**
     * Connect to remote account.
     *
     * @param array $accountCredentials  A list of remote account credentials
     *
     * @return SftpInterface
     */
    public function connect(array $accountCredentials): SftpInterface
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
     * @param string $method   A method name
     * @param string $message  A error message
     * @param string $trace    A unique trace key
     *
     * @return void
     */
    protected function logError(string $method, string $message, string $trace)
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
     * @param array $accountCredentials  A list of remote account credentials
     *
     * @return bool
     */
    protected function isValidFtpAccountCredentials(array $accountCredentials): bool
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
     * @param string $absolutePath  A directory name preference
     *
     * @return SftpInterface
     *
     * @api
     */
    public function changeDirectory(string $absolutePath): SftpInterface
    {
        $this->netSftp->chdir($absolutePath);

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Create a new directory on the remote host.
     *
     * @param string $absolutePath  A absolute path to directory
     *
     * @return SftpInterface
     *
     * @api
     */
    public function createDirectory(string $absolutePath): SftpInterface
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
     * @param string $absolutePath  A absolute path to directory
     * @param bool   $recursive     A option to delete recursively
     *
     * @return SftpInterface
     *
     * @api
     */
    public function deleteDirectory(string $absolutePath, bool $recursive = false): SftpInterface
    {
        /* Requires absolute PATH. */
        $this->netSftp->chdir(dirname($absolutePath));
        $this->netSftp->delete(basename($absolutePath), $this->toBoolean($recursive));

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Return file size.
     *
     * @param string $absolutePath  A relative or absolute filename
     *
     * @return int
     *
     * @api
     */
    public function getFileSize(string $absolutePath): int
    {
        return (int) $this->netSftp->size($absolutePath);
    }

    // --------------------------------------------------------------------------

    /**
     * Upload file to remote account.
     *
     * @param string $absolutePath_remoteFile  A absolute path to remote file (new)
     * @param string $absolutePath_localFile   A absolute path to local file
     *
     * @return SftpInterface
     *
     * @api
     */
    public function uploadFile(string $absolutePath_remoteFile, string $absolutePath_localFile): SftpInterface
    {
        if (file_exists($absolutePath_localFile)
            && is_readable($absolutePath_localFile)
        ) {
            /**
             *  Common stream source types.
             *
             *    - static::SOURCE_STRING
             *    - static::SOURCE_CALLBACK
             *    - static::SOURCE_LOCAL_FILE
             */
            $this->netSftp->put($absolutePath_remoteFile, $absolutePath_localFile, static::SOURCE_LOCAL_FILE);

        } else {
            $this->logError('AbstractSftp::uploadFile()', 'cannot read/find local file (check local path): ' . $absolutePath_localFile, 'E085');
        }

        $this->checkForSameFileSize($absolutePath_remoteFile, $absolutePath_localFile);

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Compare.
     *
     * @param string $remoteFile  A absolute path to remote file (new)
     * @param string $localFile   A absolute path to local file
     *
     * @return SftpInterface
     *
     * @api
     */
    public function checkForSameFileSize(string $remoteFile, string $localFile): SftpInterface
    {
        if ($this->getFileSize($remoteFile) !== filesize($localFile)) {
            $this->logError(
                'AbstractSftp::checkForSameFileSize()',
                'Error: The remote/local file size do not match: ' . $this->getFileSize($remoteFile) . '/' . filesize($localFile),
                'E086'
            );
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Delete remote file.
     *
     * @param string $absolutePath  A absolute path to remote file
     *
     * @return SftpInterface
     *
     * @api
     */
    public function deleteFile(string $absolutePath): SftpInterface
    {
        $this->netSftp->delete($absolutePath);

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Download file from remote account.
     *
     * @param string $absolutePath_remoteFile  A absolute path to remote file (new)
     * @param string $absolutePath_localFile   A absolute path to local file
     *
     * @return SftpInterface
     *
     * @api
     */
    public function downloadFile(string $absolutePath_remoteFile, string $absolutePath_localFile): SftpInterface
    {
        $this->netSftp->get($absolutePath_remoteFile, $absolutePath_localFile);

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Change remote file permissions (files and directories).
     *
     * @param string $mode          A permissions mode
     * @param string $absolutePath  A filename or directory path
     * @param bool   $recursive     A recursive delete option
     *
     * @return SftpInterface
     *
     * @api
     */
    public function chmod(string $mode, string $absolutePath, bool $recursive = false): SftpInterface
    {
        $this->changeDirectory(dirname($absolutePath));
        $this->netSftp->chmod($mode, basename($absolutePath), $this->toBoolean($recursive));

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Combine storageRegister with associated array.
     *
     * @param array $arraySubset  A array list
     *
     * @return SftpInterface
     */
    protected function appendToStorageRegister(array $arraySubset): SftpInterface
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
    public function getPwd(): string
    {
        return $this->netSftp->pwd();
    }

    // --------------------------------------------------------------------------

    /**
     * Rename a file or directory.
     *
     * @param string $absolutePath_old  A absolute path to file (in old name)
     * @param string $absolutePath_new  A absolute path to file (in new name)
     *
     * @return SftpInterface
     *
     * @api
     */
    public function renameFile(string $absolutePath_old, string $absolutePath_new): SftpInterface
    {
        $this->netSftp->rename($absolutePath_old, $absolutePath_new);

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Rename a file or directory.
     *
     * @param string $absolutePath_old  A absolute path to file (in old name)
     * @param string $absolutePath_new  A absolute path to file (in new name)
     *
     * @return SftpInterface
     *
     * @api
     */
    public function renameDirectory(string $absolutePath_old, string $absolutePath_new): SftpInterface
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
     * @param string $absolutePath  A directory name preference
     *
     * @return array
     *
     * @api
     */
    public function getLs(string $absolutePath = null): array
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
     * @param string $remoteFileName  A relative or absolute remote filename
     *
     * @return array  associative arrays with misc information about the files
     *
     * @api
     */
    public function getStat(string $remoteFileName): array
    {
        return $this->netSftp->stat($remoteFileName);
    }

    // --------------------------------------------------------------------------

    /**
     * Return specific file information on remote host.
     *
     * @param string $remoteFileName  A relative or absolute remote filename
     *
     * @return array  associative arrays with misc information about the files
     *
     * @api
     */
    public function getLstat(string $remoteFileName): array
    {
        return $this->netSftp->lstat($remoteFileName);
    }

    // --------------------------------------------------------------------------

    /**
     * Update the access/modification date of a file or directory (touch).
     *
     * @param string $path  A relative or absolute filename
     *
     * @return SftpInterface
     *
     * @api
     */
    public function touch(string $path): SftpInterface
    {
        $this->netSftp->touch($path);

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Upload string-to-file.
     *
     * @param string $absolutePath_remoteFile  A absolute path to remote file (new)
     * @param string $str                      A variable string
     *
     * @return SftpInterface
     *
     * @api
     */
    public function uploadString(string $absolutePath_remoteFile, string $str): SftpInterface
    {
        $this->netSftp->put($absolutePath_remoteFile, $str);

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Download file-to-string.
     *
     * @param string $absolutePath_remoteFile  A absolute path to remote file
     *
     * @return string
     *
     * @api
     */
    public function downloadString(string $absolutePath_remoteFile): string
    {
        return $this->netSftp->get($absolutePath_remoteFile);
    }

    // --------------------------------------------------------------------------

    /**
     * Method implementations inserted:
     *
     * (+) all();
     * (+) init();
     * (+) get($key);
     * (+) has($key);
     * (+) version();
     * (+) getClassName();
     * (+) getConst($key);
     * (+) set($key, $value);
     * (+) isString($str);
     * (+) getInstanceCount();
     * (+) getClassInterfaces();
     * (+) __call($callback, $parameters);
     * (+) getProperty($name, $key = null);
     * (+) doesFunctionExist($functionName);
     * (+) isStringKey($str, array $keys);
     * (+) throwExceptionError(array $error);
     * (+) setProperty($name, $value, $key = null);
     * (+) throwInvalidArgumentExceptionError(array $error);
     */
    use ServiceFunctions;
}

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

use phpseclib\Net\SCP;
use phpseclib\Net\SSH2;
use phpseclib\Net\SFTP as NetSftp;
use UCSDMath\Functions\ServiceFunctions;
use UCSDMath\Functions\ServiceFunctionsInterface;
use UCSDMath\DependencyInjection\ServiceRequestContainer;

/**
 * AbstractSftp provides an abstract base class implementation of {@link SftpInterface}.
 * This service groups a common code base implementation that Sftp extends.
 *
 * Method list: (+) @api, (-) protected or private visibility.
 *
 * (+) SftpInterface __construct();
 * (+) void __destruct();
 * (+) string getPwd();
 * (+) SftpInterface touch(string $path);
 * (+) bool toBoolean($trialBool = null);
 * (+) array getStat(string $remoteFileName);
 * (+) int getFileSize(string $absolutePath);
 * (+) array getLstat(string $remoteFileName);
 * (+) array getLs(string $absolutePath = null);
 * (+) SftpInterface deleteFile(string $absolutePath);
 * (+) SftpInterface connect(array $accountCredentials);
 * (+) SftpInterface changeDirectory(string $absolutePath);
 * (+) SftpInterface createDirectory(string $absolutePath);
 * (+) string downloadString(string $absolutePath_remoteFile);
 * (+) SftpInterface uploadString(string $absolutePath_remoteFile, string $str);
 * (+) SftpInterface checkForSameFileSize(string $remoteFile, string $localFile);
 * (+) SftpInterface renameFile(string $absolutePath_old, string $absolutePath_new);
 * (+) SftpInterface deleteDirectory(string $absolutePath, bool $recursive = false);
 * (+) SftpInterface chmod(string $mode, string $absolutePath, bool $recursive = false);
 * (+) SftpInterface renameDirectory(string $absolutePath_old, string $absolutePath_new);
 * (+) SftpInterface uploadFile(string $absolutePath_remoteFile, string $absolutePath_localFile);
 * (+) SftpInterface downloadFile(string $absolutePath_remoteFile, string $absolutePath_localFile);
 * (-) SftpInterface appendToStorageRegister(array $arraySubset);
 * (-) bool isValidFtpAccountCredentials(array $accountCredentials);
 * (-) bool logError(string $method, string $message, string $trace);
 *
 * @author Daryl Eisner <deisner@ucsd.edu>
 */
abstract class AbstractSftp implements SftpInterface, ServiceFunctionsInterface
{
    /**
     * Constants.
     *
     * @var string VERSION The version number
     *
     * @api
     */
    const VERSION = '1.10.0';

    //--------------------------------------------------------------------------

    /**
     * Properties.
     *
     * @var    phpseclib\Net\SFTP $netSftp         The set of validation stored data elements
     * @static SftpInterface      $instance        The static instance SftpInterface
     * @static int                $objectCount     The static count of SftpInterface
     * @var    array              $storageRegister The stored set of data structures used by this class
     */
    protected $netSftp = null;
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
    protected static $instance    = null;
    protected static $objectCount = 0;
    protected $storageRegister    = [];

    //--------------------------------------------------------------------------

    /**
     * Constructor.
     *
     * @api
     */
    public function __construct()
    {
    }

    //--------------------------------------------------------------------------

    /**
     * Destructor.
     *
     * @api
     */
    public function __destruct()
    {
        static::$objectCount--;
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

    /**
     * Connect to remote account.
     *
     * @param array $accountCredentials The list of remote account credentials
     *
     * @return SftpInterface The current instance
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

    //--------------------------------------------------------------------------

    /**
     * Log errors to system_logs table.
     *
     * @param string $method  The method name
     * @param string $message The error message
     * @param string $trace   The unique trace key
     *
     * @return void
     */
    protected function logError(string $method, string $message, string $trace)
    {
        ServiceRequestContainer::perform()
            ->Database
                ->insertiNetRecordLog(
                    ServiceRequestContainer::perform()
                        ->Session
                            ->getPassport('email'),
                    sprintf('-- SFTP Error: %s - [ %s ] [ %s ]', $method, $message, $trace)
                );
    }

    //--------------------------------------------------------------------------

    /**
     * Validate SFTP Account Credentials.
     *
     * @param array $accountCredentials The list of remote account credentials
     *
     * @return bool
     */
    protected function isValidFtpAccountCredentials(array $accountCredentials): bool
    {
        /**
         * Intersect the targets with the haystack and make sure
         * the intersection is precisely equal to the targets.
         */
        return count(array_intersect(array_keys($accountCredentials), $this->requiredFtpAccountCredentials))
            === count($this->requiredFtpAccountCredentials);
    }

    //--------------------------------------------------------------------------

    /**
     * Change directory.
     *
     * @param string $absolutePath The directory name preference
     *
     * @return SftpInterface The current instance
     *
     * @api
     */
    public function changeDirectory(string $absolutePath): SftpInterface
    {
        $this->netSftp->chdir($absolutePath);

        return $this;
    }

    //--------------------------------------------------------------------------

    /**
     * Create a new directory on the remote host.
     *
     * @param string $absolutePath The absolute path to directory
     *
     * @return SftpInterface The current instance
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

    //--------------------------------------------------------------------------

    /**
     * Delete directory and all its contents.
     *
     * @param string $absolutePath The absolute path to directory
     * @param bool   $recursive    The option to delete recursively
     *
     * @return SftpInterface The current instance
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

    //--------------------------------------------------------------------------

    /**
     * Return file size.
     *
     * @param string $absolutePath The relative or absolute filename
     *
     * @return int
     *
     * @api
     */
    public function getFileSize(string $absolutePath): int
    {
        return (int) $this->netSftp->size($absolutePath);
    }

    //--------------------------------------------------------------------------

    /**
     * Upload file to remote account.
     *
     * @param string $absolutePath_remoteFile The absolute path to remote file (new)
     * @param string $absolutePath_localFile  The absolute path to local file
     *
     * @return SftpInterface The current instance
     *
     * @api
     */
    public function uploadFile(string $absolutePath_remoteFile, string $absolutePath_localFile): SftpInterface
    {
        if (file_exists($absolutePath_localFile) && is_readable($absolutePath_localFile)) {
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

    //--------------------------------------------------------------------------

    /**
     * Compare.
     *
     * @param string $remoteFile The absolute path to remote file (new)
     * @param string $localFile  The absolute path to local file
     *
     * @return SftpInterface The current instance
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

    //--------------------------------------------------------------------------

    /**
     * Delete remote file.
     *
     * @param string $absolutePath The absolute path to remote file
     *
     * @return SftpInterface The current instance
     *
     * @api
     */
    public function deleteFile(string $absolutePath): SftpInterface
    {
        $this->netSftp->delete($absolutePath);

        return $this;
    }

    //--------------------------------------------------------------------------

    /**
     * Download file from remote account.
     *
     * @param string $absolutePath_remoteFile The absolute path to remote file (new)
     * @param string $absolutePath_localFile  The absolute path to local file
     *
     * @return SftpInterface The current instance
     *
     * @api
     */
    public function downloadFile(string $absolutePath_remoteFile, string $absolutePath_localFile): SftpInterface
    {
        $this->netSftp->get($absolutePath_remoteFile, $absolutePath_localFile);

        return $this;
    }

    //--------------------------------------------------------------------------

    /**
     * Change remote file permissions (files and directories).
     *
     * @param string $mode         The permissions mode
     * @param string $absolutePath The filename or directory path
     * @param bool   $recursive    The recursive delete option
     *
     * @return SftpInterface The current instance
     *
     * @api
     */
    public function chmod(string $mode, string $absolutePath, bool $recursive = false): SftpInterface
    {
        $this->changeDirectory(dirname($absolutePath));
        $this->netSftp->chmod($mode, basename($absolutePath), $this->toBoolean($recursive));

        return $this;
    }

    //--------------------------------------------------------------------------

    /**
     * Combine storageRegister with associated array.
     *
     * @param array $arraySubset The array list
     *
     * @return SftpInterface The current instance
     */
    protected function appendToStorageRegister(array $arraySubset): SftpInterface
    {
        /* Merge both registers and apply the overrides. */
        $this->storageRegister = array_merge($this->storageRegister, $arraySubset);

        return $this;
    }

    //--------------------------------------------------------------------------

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

    //--------------------------------------------------------------------------

    /**
     * Rename a file or directory.
     *
     * @param string $absolutePath_old The absolute path to file (in old name)
     * @param string $absolutePath_new The absolute path to file (in new name)
     *
     * @return SftpInterface The current instance
     *
     * @api
     */
    public function renameFile(string $absolutePath_old, string $absolutePath_new): SftpInterface
    {
        $this->netSftp->rename($absolutePath_old, $absolutePath_new);

        return $this;
    }

    //--------------------------------------------------------------------------

    /**
     * Rename a file or directory.
     *
     * @param string $absolutePath_old The absolute path to file (in old name)
     * @param string $absolutePath_new The absolute path to file (in new name)
     *
     * @return SftpInterface The current instance
     *
     * @api
     */
    public function renameDirectory(string $absolutePath_old, string $absolutePath_new): SftpInterface
    {
        $this->netSftp->rename($absolutePath_old, $absolutePath_new);

        return $this;
    }

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
     * @param string $absolutePath_remoteFile The absolute path to remote file (new)
     * @param string $str                     The variable string
     *
     * @return SftpInterface The current instance
     *
     * @api
     */
    public function uploadString(string $absolutePath_remoteFile, string $str): SftpInterface
    {
        $this->netSftp->put($absolutePath_remoteFile, $str);

        return $this;
    }

    //--------------------------------------------------------------------------

    /**
     * Download file-to-string.
     *
     * @param string $absolutePath_remoteFile The absolute path to remote file
     *
     * @return string
     *
     * @api
     */
    public function downloadString(string $absolutePath_remoteFile): string
    {
        return $this->netSftp->get($absolutePath_remoteFile);
    }

    //--------------------------------------------------------------------------

    /**
     * Method implementations inserted:
     *
     * Method list: (+) @api, (-) protected or private visibility.
     *
     * (+) array all();
     * (+) object init();
     * (+) string version();
     * (+) bool isString($str);
     * (+) bool has(string $key);
     * (+) string getClassName();
     * (+) int getInstanceCount();
     * (+) array getClassInterfaces();
     * (+) mixed getConst(string $key);
     * (+) bool isValidUuid(string $uuid);
     * (+) bool isValidEmail(string $email);
     * (+) bool isValidSHA512(string $hash);
     * (+) mixed __call($callback, $parameters);
     * (+) bool doesFunctionExist($functionName);
     * (+) bool isStringKey(string $str, array $keys);
     * (+) mixed get(string $key, string $subkey = null);
     * (+) mixed getProperty(string $name, string $key = null);
     * (+) object set(string $key, $value, string $subkey = null);
     * (+) object setProperty(string $name, $value, string $key = null);
     * (-) Exception throwExceptionError(array $error);
     * (-) InvalidArgumentException throwInvalidArgumentExceptionError(array $error);
     */
    use ServiceFunctions;

    //--------------------------------------------------------------------------
}

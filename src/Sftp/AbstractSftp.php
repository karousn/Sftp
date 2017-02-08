<?php

/*
 * This file is part of the UCSDMath package.
 *
 * (c) 2015-2017 UCSD Mathematics | Math Computing Support <mathhelp@math.ucsd.edu>
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
use UCSDMath\Sftp\ExtendedOperations\SftpExtendedOperationsTrait;
use UCSDMath\Sftp\ExtendedOperations\SftpExtendedOperationsTraitInterface;

/**
 * AbstractSftp provides an abstract base class implementation of {@link SftpInterface}.
 * This service groups a common code base implementation that Sftp extends.
 *
 * Method list: (+) @api, (-) protected or private visibility.
 *
 * (+) SftpInterface __construct();
 * (+) void __destruct();
 * (+) string getPwd();
 * (+) int getFileSize(string $absolutePath);
 * (+) SftpInterface deleteFile(string $absolutePath);
 * (+) SftpInterface connect(array $accountCredentials);
 * (+) SftpInterface changeDirectory(string $absolutePath);
 * (+) SftpInterface createDirectory(string $absolutePath);
 * (+) SftpInterface deleteDirectory(string $absolutePath, bool $recursive = false);
 * (+) SftpInterface chmod(string $mode, string $absolutePath, bool $recursive = false);
 * (+) SftpInterface uploadFile(string $absolutePathRemoteFile, string $absolutePathLocalFile);
 * (+) SftpInterface downloadFile(string $absolutePathRemoteFile, string $absolutePathLocalFile);
 * (-) SftpInterface appendToStorageRegister(array $arraySubset);
 * (-) bool isValidFtpAccountCredentials(array $accountCredentials);
 * (-) bool logError(string $method, string $message, string $trace);
 *
 * @author Daryl Eisner <deisner@ucsd.edu>
 */
abstract class AbstractSftp implements SftpInterface, ServiceFunctionsInterface, SftpExtendedOperationsTraitInterface
{
    /**
     * Constants.
     *
     * @var string VERSION The version number
     *
     * @api
     */
    public const VERSION = '1.14.0';

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
        [$accountHost, $accountUsername, $accountPassword] = [
            $this->get('account_host'),
            $this->get('account_username'),
            $this->get('account_password')
        ];

        $this->setProperty('netSftp', new NetSftp($accountHost));
        $this->netSftp->login($accountUsername, $accountPassword);

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
    protected function logError(string $method, string $message, string $trace): void
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
     * @param string $absolutePathRemoteFile The absolute path to remote file (new)
     * @param string $absolutePathLocalFile  The absolute path to local file
     *
     * @return SftpInterface The current instance
     *
     * @api
     */
    public function uploadFile(string $absolutePathRemoteFile, string $absolutePathLocalFile): SftpInterface
    {
        /**
         * Common stream source types:
         *    - static::SOURCE_STRING
         *    - static::SOURCE_CALLBACK
         *    - static::SOURCE_LOCAL_FILE
         */
        (file_exists($absolutePathLocalFile) && is_readable($absolutePathLocalFile))
            ? $this->netSftp->put($absolutePathRemoteFile, $absolutePathLocalFile, static::SOURCE_LOCAL_FILE)
            : $this->logError('AbstractSftp::uploadFile()', 'cannot read/find local file (check local path): ' . $absolutePathLocalFile, 'E085');

        $this->checkForSameFileSize($absolutePathRemoteFile, $absolutePathLocalFile);

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
     * @param string $absolutePathRemoteFile The absolute path to remote file (new)
     * @param string $absolutePathLocalFile  The absolute path to local file
     *
     * @return SftpInterface The current instance
     *
     * @api
     */
    public function downloadFile(string $absolutePathRemoteFile, string $absolutePathLocalFile): SftpInterface
    {
        $this->netSftp->get($absolutePathRemoteFile, $absolutePathLocalFile);

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
     * Method implementations inserted:
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
     * (+) SftpInterface checkForSameFileSize(string $remoteFile, string $localFile);
     * (+) SftpInterface renameFile(string $absolutePathOld, string $absolutePathNew);
     * (+) SftpInterface renameDirectory(string $absolutePathOld, string $absolutePathNew);
     */
    use SftpExtendedOperationsTrait;

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
     * (+) bool isStringKey(string $str, array $keys);
     * (+) bool doesFunctionExist(string $functionName);
     * (+) mixed get(string $key, string $subkey = null);
     * (+) mixed __call(string $callback, array $parameters);
     * (+) mixed getProperty(string $name, string $key = null);
     * (+) object set(string $key, $value, string $subkey = null);
     * (+) object setProperty(string $name, $value, string $key = null);
     * (-) Exception throwExceptionError(array $error);
     * (-) InvalidArgumentException throwInvalidArgumentExceptionError(array $error);
     */
    use ServiceFunctions;

    //--------------------------------------------------------------------------
}

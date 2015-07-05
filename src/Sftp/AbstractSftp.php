<?php/* * This file is part of the UCSDMath package. * * (c) UCSD Mathematics | Math Computing Support <mathhelp@math.ucsd.edu> * * For the full copyright and license information, please view the LICENSE * file that was distributed with this source code. */namespace UCSDMath\Sftp;use Net_SFTP;use UCSDMath\DependencyInjection\ServiceRequestContainer;use UCSDMath\Functions\ServiceFunctions;use UCSDMath\Functions\ServiceFunctionsInterface;/** * AbstractSftp provides an abstract base class implementation of {@link SftpInterface}. * Primarily, this services the fundamental implementations for all Sftp classes. * * This component library is used to service provides secure file access, file transfer, * and file management functionalities over any reliable data streams. SFTP is an * extension of the Secure Shell protocol (SSH). This is an adapter to the phpseclib * library suite. * * Method list: * * @see (+) __construct(); * @see (+) __destruct(); * @see (+) connect(array $accountCredentials = null); * @see (+) uploadFile($absolutePath_remoteFile, $absolutePath_localFile); * @see (+) deleteFile($absolutePath); * @see (+) renameFile($absolutePath_old, $absolutePath_new); * @see (+) getFileSize($absolutePath); * @see (+) getPath(); * @see (+) downloadFile($absolutePath_remoteFile, $absolutePath_localFile); * * @see (+) createDirectory($absolutePath); * @see (+) deleteDirectory($absolutePath, $recursive = false); * @see (+) changeDirectory($absolutePath); * @see (+) renameDirectory($absolutePath_old, $absolutePath_new); * * @see (+) chmod($mode, $absolutePath, $recursive = false); * @see (+) uploadString($absolutePath_remoteFile, $str); * @see (+) downloadString($absolutePath_remoteFile); * @see (+) appendToStorageRegister(array $arraySubset); * @see (+) touch($absolutePath); * * @see (-) toBoolean($trialBool = null); * @see (-) logError($method, $message, $trace); * @see (-) isValidFtpAccountCredentials(array $accountCredentials); * * @author Daryl Eisner <deisner@ucsd.edu> */abstract class AbstractSftp implements SftpInterface, ServiceFunctionsInterface{    /**     * Constants.     */    const VERSION = '1.0.3';    /**     * Properties.     *     * @var    array         $storageRegister  A set of validation stored data elements     * @static SftpInterface $instance         A SftpInterface instance     * @static integer       $objectCount      A SftpInterface instance count     */    protected $sftp = null;    protected $storageRegister = array();    protected static $instance = null;    protected static $objectCount = 0;    /**     * Constructor.     *     * @api     */    public function __construct()    {        static::$instance = $this;        static::$objectCount++;    }    /**     * Destructor.     */    public function __destruct()    {        static::$objectCount--;    }    /**     * Boolean checker and parser.     *     * May help on configuration or ajax files.     *     * @param  mixed $trialBool  A possible boolean value     *     * @return bool     *     * @api     */    public function toBoolean($trialBool = null)    {        /** Fix a troubled param: $trialBool */        $booleanValue = !is_string($trialBool) ? (bool) $trialBool : null ;        switch (strtolower($trialBool)) {            case 'true':            case 'yes':            case 'on':            case '1':            case 'y':                $booleanValue = true;                break;            default:                $booleanValue = false;                break;        }        return $booleanValue;    }    /**     * Connect to remote account.     *     * @param  array $accountCredentials  A list of remote account credentials     *     * @return SftpInterface     *     * @throws \InvalidArgumentException if remote connection not established     */    public function connect(array $accountCredentials = null)    {        null !== $accountCredentials            && $this->isValidFtpAccountCredentials($accountCredentials)            ? $this->appendToStorageRegister($accountCredentials)            : $this->logError('AbstractSftp::connect()', 'invalid account credentials', 'E076');        $this->sftp = new \Net_SFTP($this->get('account_host'));        true === $this->sftp->login($this->get('account_username'), $this->get('account_password'))            ? null            : $this->logError('AbstractSftp::connect()', 'account connection failed', 'E077');        /**         * Note: on a successful login, the default directory         * is the home directory (e.g., /home/wwwdyn).         */        return $this;    }    /**     * Log errors to inet_logs table.     *     * @param  array $method   A method name     * @param  array $message  A error message     * @param  array $trace    A unique trace key     *     * @return null     */    protected function logError($method, $message, $trace)    {        ServiceRequestContainer::init()            ->get('Database')                ->insertiNetRecordLog(                    ServiceRequestContainer::init()                        ->get('Session')                            ->getPassport('email'),                        sprintf('-- SFTP Error: %s - [ %s ] [ %s ]', $method, $message, $trace)                    );    }    /**     * Validate SFTP Account Credentials.     *     * @param  array $accountCredentials  A list of remote account credentials     *     * @return bool     */    protected function isValidFtpAccountCredentials(array $accountCredentials)    {        return            10 === count(array_keys($accountCredentials))            && in_array('id', array_keys($accountCredentials))            && in_array('uuid', array_keys($accountCredentials))            && in_array('date', array_keys($accountCredentials))            && in_array('is_encrypted', array_keys($accountCredentials))            && in_array('account_host', array_keys($accountCredentials))            && in_array('account_options', array_keys($accountCredentials))            && in_array('account_username', array_keys($accountCredentials))            && in_array('account_password', array_keys($accountCredentials))            && in_array('default_directory', array_keys($accountCredentials))            && in_array('is_secure_connection', array_keys($accountCredentials));    }    /**     * Change directory.     *     * @param  string $absolutePath  A directory name preference     *     * @return SftpInterface     *     * @throws \throwInvalidArgumentExceptionError if $absolutePath is not a defined string     * @throws \logError if path does not exist on remote host     *     * @api     */    public function changeDirectory($absolutePath)    {        $this->isString($absolutePath)            ? null            : $this->throwInvalidArgumentExceptionError([                'datatype|string|{$absolutePath}',                __METHOD__,                __CLASS__,                'A053'            ]);        true === $this->sftp->chdir($absolutePath)            ? null            : $this->logError(                'AbstractSftp::changeDirectory()',                'remote directory does not exist: '. $absolutePath,                'E078'            );        return $this;    }    /**     * Create a new directory on the remote host.     *     * @return bool  (if created 'true'; if already exists 'false')     *     * @api     */    public function createDirectory($absolutePath)    {        /**         *  Requires absolute PATH.         */        $this->isString($absolutePath)            ? null            : $this->throwInvalidArgumentExceptionError([                'datatype|string|{$absolutePath}',                __METHOD__,                __CLASS__,                'A111'            ]);        $this->changeDirectory(dirname($absolutePath));        true === $this->sftp->mkdir(basename($absolutePath))            ? null            : $this->logError(                'AbstractSftp::createDirectory()',                'cannot create directory (already exists): '. $absolutePath,                'E079'            );        return $this;    }    /**     * Delete directory and all its contents.     *     * @param  string $absolutePath  A absolute path to directory     * @param  string $recursive     A option to delete recursively     *     * @api     */    public function deleteDirectory($absolutePath, $recursive = false)    {        /** interpret recursive option */        $boolSet = [false => false, true => true];        /**         *  Requires absolute PATH.         */        $this->changeDirectory(dirname($absolutePath));        $theDirectoryToRemove = basename($absolutePath);        $this->sftp->delete($theDirectoryToRemove, $boolSet[$this->toBoolean($recursive)]);        /**         *         true === $this->sftp->delete($theDirectoryToRemove, $boolSet[$this->toBoolean($recursive)])         *             ? null         *             : $this->logError(         *                 'AbstractSftp::deleteDirectory()',         *                 'cannot delete directory (options|existance): '. $absolutePath,         *                 'E080'         *             );         */        return $this;    }    /**     * Return file size.     *     * @param  string $absolutePath  A relative or absolute filename     *     * @return int|bool  (bool on error)     *     * @throws \throwInvalidArgumentExceptionError if $absolutePath is not a defined string     *     * @api     */    public function getFileSize($absolutePath)    {        $this->isString($absolutePath)            ? null            : $this->throwInvalidArgumentExceptionError([                'datatype|string|{$absolutePath}',                __METHOD__,                __CLASS__,                'A052'            ]);        return $this->sftp->size($absolutePath);    }    /**     * Get directory path.     *     * @return string     *     * @api     */    public function getPath()    {        return $this->sftp->pwd();    }    /**     * Upload file to remote account.     *     * @param  string $absolutePath_remoteFile  A absolute path to remote file (new)     * @param  string $absolutePath_localFile   A absolute path to local file     *     * @return SftpInterface     *     * @api     */    public function uploadFile($absolutePath_remoteFile, $absolutePath_localFile)    {        if (file_exists($absolutePath_localFile)            && is_readable($absolutePath_localFile)        ) {            true === $this->sftp->put($absolutePath_remoteFile, $absolutePath_localFile, NET_SFTP_LOCAL_FILE)                ? null                : $this->logError(                    'AbstractSftp::uploadFile()',                    'cannot upload file (check local path): '. $absolutePath_localFile,                    'E084'                );        } else {            $this->logError(                'AbstractSftp::uploadFile()',                'cannot read/find file (check local path): '. $absolutePath_localFile,                'E085'            );        }        $local_file_size  = filesize($absolutePath_localFile);        $remote_file_size = $this->getFileSize($absolutePath_remoteFile);        $remote_file_size === $local_file_size            ? null            : $this->logError(                  'AbstractSftp::uploadFile()',                  'remote/local file size != : '. $remote_file_size.'/'.$local_file_size,                  'E086'              );        return $this;    }    /**     * Delete remote file.     *     * @param  string $absolutePath  A absolute path to remote file     *     * @api     */    public function deleteFile($absolutePath)    {        true === $this->sftp->delete($absolutePath)            ? null            : $this->logError(                'AbstractSftp::deleteFile()',                'cannot delete file (check remote path): '. $absolutePath,                'E082'            );        return $this;    }    /**     * Rename a file or directory.     *     * @param  string $absolutePath_old  A absolute path to file (in old name)     * @param  string $absolutePath_new  A absolute path to file (in new name)     *     * @api     */    public function renameFile($absolutePath_old, $absolutePath_new)    {        true === $this->sftp->rename($absolutePath_old, $absolutePath_new)            ? null            : $this->logError(                'AbstractSftp::renameFile()',                'cannot rename file (check remote path): '. $absolutePath_old,                'E082'            );        return $this;    }    /**     * Rename a file or directory.     *     * @param  string $absolutePath_old  A absolute path to file (in old name)     * @param  string $absolutePath_new  A absolute path to file (in new name)     *     * @api     */    public function renameDirectory($absolutePath_old, $absolutePath_new)    {        true === $this->sftp->rename($absolutePath_old, $absolutePath_new)            ? null            : $this->logError(                'AbstractSftp::renameDirectory()',                'cannot rename directory (check remote path): '. $absolutePath_old,                'E082'            );        return $this;    }    /**     * Download file from remote account.     *     * @param  string $absolutePath_remoteFile  A absolute path to remote file (new)     * @param  string $absolutePath_localFile   A absolute path to local file     *     * @return SftpInterface     *     * @api     */    public function downloadFile($absolutePath_remoteFile, $absolutePath_localFile)    {        true === $this->sftp->get($absolutePath_remoteFile, $absolutePath_localFile)            ? null            : $this->logError(                'AbstractSftp::downloadFile()',                'cannot download file (check remote/local paths): '. $absolutePath_remoteFile,                'E085'            );        return $this;    }    /**     * Change remote file permissions (files and directories).     *     * @param  string $mode          A permissions mode     * @param  string $absolutePath  A filename or directory path     * @param  bool   $recursive     A recursive delete option     *     * @return SftpInterface     *     * @throws \throwInvalidArgumentExceptionError if $mode is not a defined string     * @throws \throwInvalidArgumentExceptionError if $absolutePath is not a defined string     *     * @api     */    public function chmod($mode, $absolutePath, $recursive = false)    {        $this->isString($mode) || is_int($mode)            ? null            : $this->throwInvalidArgumentExceptionError([                'datatype|string|integer|{$mode}',                __METHOD__,                __CLASS__,                'A111'            ]);        $this->isString($absolutePath)            ? null            : $this->throwInvalidArgumentExceptionError([                'datatype|string|{$absolutePath}',                __METHOD__,                __CLASS__,                'A111'            ]);        $this->changeDirectory(dirname($absolutePath));        /** interpret recursive option */        $boolSet = [false => false, true => true];        /**         * Example: $sftp->chmod(0777, '/home/link/public_html', true);         */        true === $this->sftp->chmod($mode, basename($absolutePath), $boolSet[$this->toBoolean($recursive)])            ? null            : $this->logError(                'AbstractSftp::createDirectory()',                'cannot change permissions (check file path): '. $absolutePath,                'E079'            );        return $this;    }    /**     * Upload string-to-file.     *     * @param  string $absolutePath_remoteFile  A absolute path to remote file (new)     * @param  string $str                      A variable string     *     * @return SftpInterface     *     * @api     */    public function uploadString($absolutePath_remoteFile, $str)    {        true === $this->sftp->put($absolutePath_remoteFile, $str)            ? null            : $this->logError(                'AbstractSftp::uploadString()',                'cannot upload string-to-file (check local path): '. $absolutePath_localFile,                'E081'            );        return $this;    }    /**     * Download file-to-string.     *     * @param  string $absolutePath_remoteFile  A absolute path to remote file     *     * @return SftpInterface     *     * @api     */    public function downloadString($absolutePath_remoteFile)    {        return $this->sftp->get($absolutePath_remoteFile);    }    /**     * Combine storageRegister with associated array.     *     * @param  array $arraySubset  A array list     *     * @return SftpInterface     *     * @throws \InvalidArgumentException if the property is not defined     */    protected function appendToStorageRegister(array $arraySubset = null)    {        if (null === $arraySubset) {            throw new \InvalidArgumentException(sprintf(                'You must provide a valid array with some data subset. No data was given for %s. - %s',                '$this->arraySubset',                '[fact-A505]'            ));        }        /**         * Merge both registers and apply the overrides.         */        $this->storageRegister = array_merge($this->storageRegister, $arraySubset);        $arraySubset = array();        return $this;    }    /**     * List all files in a directory.     *     * @internal this is like '/bin/ls' on unix, but returned as array.     *     * @param  string $absolutePath  A directory name preference     *     * @return array     *     * @throws \throwInvalidArgumentExceptionError if $absolutePath is not a string/null     *     * @api     */    public function ls($absolutePath = null)    {        $this->isString($absolutePath)            || is_null($absolutePath)            ? null            : $this->throwInvalidArgumentExceptionError([                'datatype|string|null|{$absolutePath}',                __METHOD__,                __CLASS__,                'A053'            ]);        if (! is_null($absolutePath)) {            $theOldDirectoryPath = $this->pwd();            $this->changeToDirectory($absolutePath);            $theDirectoryFiles = $this->sftp->nlist();            $this->changeToDirectory($theOldDirectoryPath);        } else {            $theDirectoryFiles = $this->sftp->nlist();        }        return $theDirectoryFiles;    }    /**     * Return specific file information on remote host.     *     * @return array     *     * @api     */    public function stat($remoteFileName)    {        return $this->sftp->stat($remoteFileName);    }    /**     * Return specific file information on remote host.     *     * @return array     *     * @api     */    public function lstat($remoteFileName)    {        return $this->sftp->lstat($remoteFileName);    }    /**     * Update the access/modification date of a file or directory (touch).     *     * @param  string $absolutePath  A relative or absolute filename     *     * @return int|bool  (bool on error)     *     * @throws \throwInvalidArgumentExceptionError if $remoteFileName is not a defined string     *     * @api     */    public function touch($absolutePath)    {        $this->isString($absolutePath)            ? null            : $this->throwInvalidArgumentExceptionError([                'datatype|string|{$absolutePath}',                __METHOD__,                __CLASS__,                'A052'            ]);        true === $this->sftp->touch($absolutePath)            ? null            : $this->logError(                'AbstractSftp::touch()',                'touch file failed (check local path): '. $absolutePath,                'E081'            );        return $this;    }    /**     * Method implementations inserted.     *     * The notation below illustrates visibility: (+) @api, (-) protected or private.     *     * @see (+) all();     * @see (+) init();     * @see (+) get($key);     * @see (+) has($key);     * @see (+) version();     * @see (+) getClassName();     * @see (+) getConst($key);     * @see (+) set($key, $value);     * @see (+) isString($string);     * @see (+) getInstanceCount();     * @see (+) getClassInterfaces();     * @see (+) getProperty($name, $key = null);     * @see (-) doesFunctionExist($functionName);     * @see (+) isStringKey($string, array $keys);     * @see (-) throwExceptionError(array $error);     * @see (+) setProperty($name, $value, $key = null);     * @see (-) throwInvalidArgumentExceptionError(array $error);     */    use ServiceFunctions;}/** * Other commands that may be useful: * * $sftp->touch($remote_file); * $sftp->chown($remote_file, $uid); * $sftp->chown($remote_dir, $uid, true); // recursive * $sftp->chmod(0777, $remote_file); * $sftp->chmod(0777, $remote_dir, true); // recursive * $sftp->chgrp($remote_file, $gid); * $sftp->chgrp($remote_file, $gid, true); // recursive * $sftp->lstat($remote_file); * $sftp->stat($remote_file); * $sftp->size($remote_file); * $sftp->delete($remote_file); * $sftp->delete($remote_dir, true);       // recursive * $sftp->rename($remote_file, $remote_newfilename); * $sftp->nlist(); * $sftp->rawlist(); * $sftp->mkdir('test');   // create directory 'test' * $sftp->chdir('test');   // open directory 'test' * $sftp->pwd();           // show that we're in the 'test' directory * $sftp->chdir('..');     // go back to the parent directory * $sftp->rmdir('test');   // delete the directory. If file inside, then $sftp->delete('test', true); * echo $sftp->get($remote_file);                // outputs the contents to the screen * $sftp->get($remote_file, $local_file);        // copies remote file to local from the SFTP server * $sftp->put($remote_file, $my_string );        // stream text to a remote file * $sftp->put($remote_file, $local_file, NET_SFTP_LOCAL_FILE);  // upload file to remote host SFTP */
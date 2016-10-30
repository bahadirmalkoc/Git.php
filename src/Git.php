<?php

namespace Pub\Git;

use InvalidArgumentException;
use Pub\Git\GitException as Exception;
use Pub\Git\GitProcessException as ProcessException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Process\ProcessUtils;

// TODO: Add remove tag
// TODO: Make an addAll command
// TODO: Add remote

/**
 * Git Interface Class
 *
 * This class enables the creating, reading, and manipulation
 * of git repositories.
 *
 * @class  Git
 */
class Git {

    /**
     * Git executable location
     *
     * @var string
     */
    private static $bin;

    /**
     * @var string
     */
    private $repoPath;

    /**
     * @var bool
     */
    private $bare = false;

    /**
     * @var int
     */
    private $timeout;

    /**
     * @var ProcessBuilder
     */
    private $processBuilder;

    /**
     * @var bool
     */
    private $gracefulFail = true;

    /**
     * @var string
     */
    private $lastStdError = '';


    /**
     * Sets git executable path.
     *
     * @param string $path executable location
     */
    public static function setBin($path) {
        static::$bin = $path;
    }

    /**
     * Gets git executable path
     */
    public static function getBin():string {
        return static::$bin;
    }

    /**
     * Find git binary using executable finder.
     * This is called by constructor once automatically, unless the binary is explicitly stated.
     */
    public static function findBin() {
        $executableFinder = new ExecutableFinder();

        if (DIRECTORY_SEPARATOR === '\\') {
            $default = 'git';
            $executableFinder->setSuffixes(['']);
        } else {
            $default = '/usr/bin/git';
        }

        static::$bin = $executableFinder->find('git', $default);
    }

    /**
     * Clones a repo into a directory and then returns a GitRepo object
     * for the newly created local repo
     *
     * @param string $repoPath Repository path
     * @param string $remote Remote repository
     * @param bool   $bare Make a bare Git repository. (--bare)
     *
     * @return Git
     * @throws GitException In case of a git error
     */
    public static function cloneRepository(string $repoPath, string $remote, bool $bare = false):Git {
        $repo = new static($repoPath, true);

        $repo->cloneRemote($remote);

        return $repo;
    }

    /**
     * Opens/creates a git repository. Also searches binary.
     *
     * @param string $repoPath Repository path
     * @param bool   $create Create directory and initiate it if not exists?
     * @param string $remote If given, repository will be created with the given remote. Indicates create=true.
     * @param bool   $bare Make the cloned bare repository bare.
     */
    public function __construct(string $repoPath, bool $create = false, string $remote = null, bool $bare = false) {
        if (!static::$bin) {
            static::findBin();
        }

        $this->processBuilder = new ProcessBuilder();
        $this->processBuilder->setPrefix(static::getBin());
        $this->processBuilder->inheritEnvironmentVariables();
        $this->processBuilder->setWorkingDirectory($repoPath);

        if ($remote !== null) {
            $create = true;
        }

        $this->setRepoPath($repoPath, $create, $remote, $bare);
    }

    /**
     * Set the repository's path
     *
     * Accepts the repository path
     *
     * @param string $repoPath Repository path
     * @param bool   $create Create directory and initiate it if not exists?
     * @param string $remote If given, repository will be created with the given remote. Indicates create=true.
     * @param bool   $bare Make the cloned bare repository bare.
     *
     * @throws GitException When the given path is not a repository or cannot create a repository in the directory
     */
    protected function setRepoPath(string $repoPath, bool $create, string $remote = null, bool $bare = false) {
        if ($newPath = realpath($repoPath)) {
            $repoPath = $newPath;
            if (is_dir($repoPath)) {
                // Is this a work tree?
                if (file_exists($repoPath . '/.git') && is_dir($repoPath . '/.git')) {
                    $this->repoPath = $repoPath;
                    $this->bare     = false;
                    // Is this a bare repo?
                } else if (is_file($repoPath . '/config')) {
                    $parse_ini = parse_ini_file($repoPath . '/config');
                    if ($parse_ini['bare']) {
                        $this->repoPath = $repoPath;
                        $this->bare     = true;
                    }
                } else {
                    if ($create) {
                        $this->repoPath = $repoPath;
                        if ($remote) {
                            $this->bare = $bare;
                            $this->cloneRemote($remote, $bare);
                        } else {
                            $this->run('init');
                        }
                    } else {
                        throw new Exception('"' . $repoPath . '" is not a git repository');
                    }
                }
            } else {
                throw new Exception('"' . $repoPath . '" is not a directory');
            }
        } else {
            if ($create) {
                if ($parent = realpath(dirname($repoPath))) {
                    mkdir($repoPath);
                    $this->repoPath = $repoPath;
                    if ($remote) {
                        $this->bare = $bare;
                        $this->cloneRemote($remote, $bare);
                    } else {
                        $this->run('init');
                    }
                } else {
                    throw new Exception('cannot create repository in non-existent directory');
                }
            } else {
                throw new Exception('"' . $repoPath . '" does not exist');
            }
        }
    }

    /**
     * Returns the repository path
     *
     * @return string
     */
    public function getRepoPath(): string {
        return $this->repoPath;
    }

    /**
     * Returns the last stdError message after running a git command. Useful if the git command returns messages with
     * stdError. An example is the clone command parsing progress to stdError.
     *
     * @return string
     */
    public function getLastStdError(): string {
        return $this->lastStdError;
    }

    /**
     * Returns if the repository is bare or not
     *
     * @return boolean
     */
    public function isBare(): bool {
        return $this->bare;
    }

    /**
     * Get the path to the git repo directory (eg. the ".git" directory)
     *
     * @return string
     */
    public function getGitDirectoryPath():string {
        return ($this->bare) ? $this->repoPath : $this->repoPath . "/.git";
    }

    /**
     * Get timeout of a git process in seconds
     *
     * @return int
     */
    public function getTimeout(): int {
        return $this->timeout;
    }

    /**
     * Sets the timeout of a git process in seconds
     *
     * @param int $timeout
     */
    public function setTimeout(int $timeout) {
        $this->processBuilder->setTimeout($timeout);
        $this->timeout = $timeout;
    }

    /**
     * Set if the git commands should gracefully fail. If this setting is on, the commands will not only fail with
     * a non zero error code but also the STDERR should also not be empty. However if both STDERR and STDOUT is empty
     * then it is again a fatal error. By default this is on.
     *
     * @param boolean $gracefulFail
     */
    public function setGracefulFail(bool $gracefulFail) {
        $this->gracefulFail = $gracefulFail;
    }

    /**
     * Check if the git commands should gracefully fail. If this setting is off, the commands will fail only with
     * a non zero error code. By the default setting is on.
     *
     * @return boolean
     */
    public function isGracefulFail(): bool {
        return $this->gracefulFail;
    }

    /**
     * Run a command
     *
     * @param array $arguments Arguments to pass to the process
     *
     * @return string
     * @throws GitException When a git command is failed
     * @throws GitProcessException When a git process is failed to be started
     */
    protected function runCommand(array $arguments):string {
        $this->lastStdError = '';
        $this->processBuilder->setArguments($arguments);
        $process = $this->processBuilder->getProcess();
        try {
            $process->run();
        } catch (RuntimeException $e) {
            throw new ProcessException($e->getMessage(), $e->getCode(), $e);
        }

        $stdErr = $this->lastStdError = $process->getErrorOutput();
        if ($process->isSuccessful()) {
            return $process->getOutput();
        }

        if ($this->isGracefulFail()) {
            $stdOut = $process->getOutput();

            if (!$stdErr && !$stdOut) {
                throw new GitException('No output returned from git command', 1500);
            } else if (!$stdErr) {
                return $stdOut;
            }
        }

        throw new GitException($stdErr, $process->getExitCode());
    }

    /**
     * Run a git command in the git repository
     *
     * @param string $command Command to run
     * @param array  $arguments Additional arguments (in order) to include with the command
     *
     * @return string The command output
     * @throws GitException If the git command fails. The error message from git (from STDERR) is relayed with
     *     exception if available.
     * @throws GitProcessException If the git process fails.
     */
    public function run(string $command, array $arguments = []):string {
        $pass = array_merge([
            trim($command)
        ], $arguments);

        return $this->runCommand($pass);
    }

    /**
     * Runs a 'git status' call
     *
     * @param bool $excludeUntracked Excludes untracked files on status
     *
     * @return string Returns the command output
     */
    public function status($excludeUntracked = false):string {
        $arguments = [];
        if ($excludeUntracked) {
            $arguments[] = '-uno';
        }

        return $this->run('status', $arguments);
    }

    /**
     * Runs a `git add` call
     *
     * @param array|string $files Files to add. If you want to specify more than one `pathspec`, use the array format.
     * @param bool         $verbose Verbose command (--verbose)
     * @param bool         $force Allow adding otherwise ignored files. (--force)
     * @param bool         $test Don't actually add the file(s), just show if they exist and/or will be ignored.
     *     (--dry-run)
     *
     * @return string Returns the command output
     */
    public function add($files = '*', bool $verbose = true, bool $force = false, bool $test = false):string {
        $arguments = [];
        if ($verbose) {
            $arguments[] = '--verbose';
        }
        if ($force) {
            $arguments[] = '--force';
        }
        if ($test) {
            $arguments[] = '--dry-run';
        }

        if (is_array($files)) {
            $arguments = array_merge($arguments, $files);
        } else if (is_string($files)) {
            $arguments[] = $files;
        } else {
            throw new InvalidArgumentException(sprintf('Expecting array or string, found %s', gettype($files)));
        }

        return $this->run('add', $arguments);
    }

    /**
     * Runs a `git rm` call
     *
     * @param array|string $files Files to remove. If you want to specify more than one `pathspec`, use the array
     *     format.
     * @param bool         $cached Use this option to unstage and remove paths only from the index. Working tree files,
     *     whether modified or not, will be left alone. (--cached)
     * @param bool         $force Override the up-to-date check. (--force)
     * @param bool         $verbose Normally outputs one line (in the form of an rm command) for each file removed.
     *     Making this false, suppresses that output.
     * @param bool         $test Don’t actually remove any file(s). Instead, just show if they exist in the index and
     *     would otherwise be removed by the command. (--dry-run)
     *
     * @return string Returns the command output
     */
    public function rm($files = "*", bool $cached = false, bool $force = false, bool $verbose = true, bool $test = false):string {
        $arguments = [];
        if ($cached) {
            $arguments[] = '--cached';
        }
        if ($force) {
            $arguments[] = '--force';
        }
        if (!$verbose) {
            $arguments[] = '--quiet';
        }
        if ($test) {
            $arguments[] = '--dry-run';
        }

        if (is_array($files)) {
            $arguments = array_merge($arguments, $files);
        } else if (is_string($files)) {
            $arguments[] = $files;
        } else {
            throw new InvalidArgumentException(sprintf('Expecting array or string, found %s', gettype($files)));
        }

        return $this->run('rm', $arguments);
    }

    /**
     * Runs a `git commit` call
     *
     * @param string $message Commit message (--message)
     * @param bool   $all Tell the command to automatically stage files that have been modified and deleted, but new
     *     files you have not told Git about are not affected. (--all)
     * @param bool   $verbose Show unified diff between the HEAD commit and what would be committed at the bottom of
     *     the commit message template to help the user describe the commit by reminding what changes the commit has.
     *     (--verbose)
     * @param bool   $test Do not create a commit, but show a list of paths that are to be committed, paths with local
     *     changes that will be left uncommitted and paths that are untracked. (--dry-run)
     *
     * @return string Returns the command output
     */
    public function commit(string $message, bool $all = true, bool $verbose = true, bool $test = false):string {
        $arguments = [];
        if ($all) {
            $arguments[] = '--all';
        }
        if ($verbose) {
            $arguments[] = '--verbose';
        }
        if ($test) {
            $arguments[] = '--dry-run';
        }

        $arguments[] = '--message=\'' . $message . '\'';

        return $this->run('commit', $arguments);
    }

    /**
     * Runs a `git clone` call to clone the current repository
     * into a different directory
     *
     * @param string $target Target directory
     * @param bool   $bare Make a bare Git repository. (--bare)
     *
     * @return string Returns the command output
     */
    public function cloneTo(string $target, bool $bare = false):string {
        $arguments   = [];
        $arguments[] = '--local';
        if ($bare) {
            $arguments[] = '--bare';
        }
        $arguments[] = $this->repoPath;
        $arguments[] = $target;

        return $this->run('clone', $arguments);
    }

    /**
     * Runs a `git clone` call to clone a remote repository
     * into the current repository
     *
     * @param string $remote Remote repository that will be cloned into this repository
     * @param bool   $bare Make a bare Git repository. (--bare)
     * @param bool   $verbose Run verbosely. Does not affect the reporting of progress status to the standard error
     *     stream. (--verbose)
     *
     * @return string Returns the command output
     */
    protected function cloneRemote(string $remote, bool $bare = false, bool $verbose = true):string {
        $arguments = [];
        if ($bare) {
            $arguments[] = '--bare';
        }
        if ($verbose) {
            $arguments[] = '--verbose';
        }
        $arguments[] = $remote;
        $arguments[] = $this->repoPath;

        return $this->run('clone', $arguments);
    }

    /**
     * Runs a `git clean` call
     *
     * @param bool $deleteDirs Remove untracked directories in addition to untracked files. If an untracked directory
     *     is managed by a different Git repository, it is not removed (-d)
     * @param bool $force If the Git configuration variable clean.requireForce is not set to false, git clean will
     *     refuse to delete files or directories unless given this option. (--force)
     * @param bool $test Don’t actually remove anything, just show what would be done. (--dry-run)
     *
     * @return string Returns the command output
     */
    public function clean(bool $deleteDirs = false, bool $force = false, bool $test = false):string {
        $arguments = [];
        if ($deleteDirs) {
            $arguments[] = '-d';
        }
        if ($force) {
            $arguments[] = '--force';
        }
        if ($test) {
            $arguments[] = '--dry-run';
        }

        return $this->run('clean', $arguments);
    }

    /**
     * Runs a `git branch` call
     *
     * @param string $branch branch name
     *
     * @return string Returns the command output
     */
    public function createBranch(string $branch):string {
        return $this->run('branch', [
            $branch
        ]);
    }

    /**
     * Runs a `git branch -[d|D]` call
     *
     * @param string $branch Branch name
     * @param bool   $force Allow deleting the branch irrespective of its merged status. (--force)
     * @param bool   $remotes Delete the remote-tracking branches.
     *
     * @return string Returns the command output
     */
    public function deleteBranch(string $branch, bool $force = false, bool $remotes = false):string {
        $arguments = [];
        if ($force) {
            $arguments[] = '-D';
        } else {
            $arguments[] = '-d';
        }
        if ($remotes) {
            $arguments[] = '-r';
        }

        $arguments[] = $branch;
        
        return $this->run('branch', $arguments);
    }

    /**
     * Runs a `git branch` call
     *
     * @param bool $keepAsterisk Keep asterisk mark on active branch
     *
     * @return array Returns the list of local branches
     */
    public function listBranches(bool $keepAsterisk = false):array {
        $branchArray = explode("\n", $this->run('branch'));
        foreach ($branchArray as $i => &$branch) {
            $branch = trim($branch);
            if (!$keepAsterisk) {
                $branch = str_replace("* ", "", $branch);
            }
            if ($branch == "") {
                unset($branchArray[$i]);
            }
        }

        return $branchArray;
    }

    /**
     * Lists remote branches (using `git branch -r`).
     *
     * Also strips out the HEAD reference (e.g. "origin/HEAD -> origin/master").
     *
     * @return array Returns the list of remote branches
     */
    public function listRemoteBranches():array {
        $branchArray = explode("\n", $this->run('branch', ['-r']));
        foreach ($branchArray as $i => &$branch) {
            $branch = trim($branch);
            if ($branch == "" || strpos($branch, 'HEAD -> ') !== false) {
                unset($branchArray[$i]);
            }
        }

        return $branchArray;
    }

    /**
     * Returns name of active branch
     *
     * @param bool $keepAsterisk Keep asterisk mark on branch name
     *
     * @return string Returns the name of the active branch
     */
    public function activeBranch(bool $keepAsterisk = false):string {
        $branchArray  = $this->listBranches(true);
        $activeBranch = preg_grep("/^\*/", $branchArray);
        reset($activeBranch);
        if ($keepAsterisk) {
            return current($activeBranch);
        } else {
            return str_replace("* ", "", current($activeBranch));
        }
    }

    /**
     * Runs a `git checkout` call
     *
     * @param string $branch Branch name
     * @param bool   $detach Prepare to work on top of <commit>, by detaching HEAD at it (see "DETACHED HEAD" section),
     *     and updating the index and the files in the working tree. (--detach)
     *
     * @return string Returns the command output
     */
    public function checkout(string $branch, bool $detach = false):string {
        $arguments = [];
        if ($detach) {
            $arguments[] = '--detach';
        }
        $arguments[] = $branch;

        return $this->run('checkout', $arguments);
    }


    /**
     * Runs a `git merge` call
     *
     * @param string $branch Branch to be merged
     * @param bool   $test Perform the merge but pretend the merge failed and do not autocommit (--no-commit)
     *
     * @return string Returns the command output
     */
    public function merge(string $branch, bool $test = false):string {
        $arguments   = [];
        $arguments[] = '--no-ff';
        if ($test) {
            $arguments[] = '--no-commit';
        }
        $arguments[] = $branch;

        return $this->run('merge', $arguments);
    }


    /**
     * Runs a git fetch on the current branch
     *
     * @return string Returns the command output
     */
    public function fetch():string {
        $arguments = [];

        return $this->run('fetch', $arguments);
    }

    /**
     * Add a new annotated tag on the current position
     *
     * @param string $tag Tag name
     * @param string $message Optional message
     * @param bool   $force Replace an existing tag with the given name (instead of failing) (--force)
     * @param string $commit The object that the new tag will refer to, usually a commit. Defaults to HEAD.
     *
     * @return string Returns the command output
     */
    public function addTag(string $tag, string $message = null, bool $force = false, $commit = null):string {
        if ($message === null) {
            $message = $tag;
        }

        $arguments   = [];
        $arguments[] = '--annotate';
        $arguments[] = '--message=\'' . $message . '\'';
        if ($force) {
            $arguments[] = '--force';
        }
        $arguments[] = $tag;
        if ($commit !== null) {
            $arguments[] = $commit;
        }

        return $this->run('tag', $arguments);
    }

    /**
     * List all the available repository tags.
     *
     * @param string $pattern Shell wildcard pattern to match tags against.
     *
     * @return array Available repository tags.
     */
    public function listTags(string $pattern = null):array {
        $arguments   = [];
        $arguments[] = '-l';
        if ($pattern) {
            $arguments[] = $pattern;
        }

        $tagArray = explode("\n", $this->run('tag', $arguments));
        foreach ($tagArray as $i => &$tag) {
            $tag = trim($tag);
            if ($tag == '') {
                unset($tagArray[$i]);
            }
        }

        return $tagArray;
    }

    /**
     * Push specific branch to a remote
     *
     * @param string $remote Name of remote branch
     * @param string $branch Name of local branch
     * @param bool   $tags All refs under refs/tags are pushed, in addition to refspecs explicitly listed on the
     *     command line. (--tags)
     * @param bool   $test Do everything except actually send the updates. (--dry-run)
     *
     * @return string Returns the command output
     */
    public function push(string $remote = null, string $branch = null, bool $tags = false, bool $test = false):string {
        $arguments = [];

        if ($tags) {
            $arguments[] = '--tags';
        }
        if ($test) {
            $arguments[] = '--dry-run';
        }
        if ($remote !== null) {
            $arguments[] = $remote;
        }
        if ($branch !== null) {
            $arguments[] = $branch;
        }

        return $this->run('push', $arguments);
    }

    /**
     * Pull specific branch from remote
     *
     * @param string $remote Name of remote branch
     * @param string $branch Name of local branch
     * @param bool   $test Perform the merge but pretend the merge failed and do not autocommit (--no-commit)
     *
     * @return string Returns the command output
     */
    public function pull(string $remote = null, string $branch = null, bool $test = false):string {
        $arguments = [];

        $arguments[] = '--no-ff';
        if ($test) {
            $arguments[] = '--no-commit';
        }
        if ($remote !== null) {
            $arguments[] = $remote;
        }
        if ($branch !== null) {
            $arguments[] = $branch;
        }

        return $this->run('pull', $arguments);
    }

    /**
     * List log entries.
     *
     * @param string $format Format for --pretty=format:
     *
     * @return string
     */
    public function log($format = null) {
        if ($format === null) {
            return $this->run('log');
        } else {
            return $this->run('log', ['--pretty=format:"' . $format . '"']);
        }
    }

    /**
     * Sets the project description.
     *
     * @param string $description
     */
    public function setDescription($description) {
        $path = $this->getGitDirectoryPath();
        file_put_contents($path . "/description", $description);
    }

    /**
     * Gets the project description.
     *
     * @return string
     */
    public function getDescription() {
        $path = $this->getGitDirectoryPath();

        return file_get_contents($path . "/description");
    }

    /**
     * Sets custom environment options for calling Git.
     *
     * @param string $key Key for the environment variable
     * @param string $value Value for the environment variable. Use null to delete the variable.
     *
     */
    public function setEnv(string $key, string $value) {
        $this->processBuilder->setEnv($key, $value);
    }

}

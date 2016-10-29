<?php

namespace Pub\Git;

use InvalidArgumentException;
use Pub\Git\GitException as Exception;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\ProcessBuilder;

// TODO: Add remove tag

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
     * @var array
     */
    private $envOpts = array();

    /**
     * @var ProcessBuilder
     */
    private $processBuilder;


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
    public static function getBin() {
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
     *
     * @return Git
     * @throws GitException In case of a git error
     */
    public static function cloneRepository(string $repoPath, string $remote) {
        $repo = new static($repoPath, true);

        $repo->cloneRemote($remote);

        return $repo;
    }

    /**
     * Opens/creates a git repository. Also searches binary.
     *
     * @param   string $repoPath Repository path
     * @param   bool   $create Create directory and initiate it if not exists?
     *
     * @throws GitException When the given path is not a repository or cannot create a repository in the directory
     */
    public function __construct(string $repoPath, bool $create = false) {
        if (!static::$bin) {
            static::findBin();
        }

        $this->processBuilder = new ProcessBuilder();
        $this->processBuilder->setPrefix(static::getBin());
        
        $this->setRepoPath($repoPath, $create);
    }

    /**
     * Set the repository's path
     *
     * Accepts the repository path
     *
     * @param   string $repoPath Repository path
     * @param   bool   $createNew Create directory and initiate it if not exists?
     *
     * @throws GitException When the given path is not a repository or cannot create a repository in the directory
     */
    private function setRepoPath(string $repoPath, $createNew = false) {
        if ($newPath = realpath($repoPath)) {
            $repoPath = $newPath;
            if (is_dir($repoPath)) {
                // Is this a work tree?
                if (file_exists($repoPath . "/.git") && is_dir($repoPath . "/.git")) {
                    $this->repoPath = $repoPath;
                    $this->bare     = false;
                    // Is this a bare repo?
                } else if (is_file($repoPath . "/config")) {
                    $parse_ini = parse_ini_file($repoPath . "/config");
                    if ($parse_ini['bare']) {
                        $this->repoPath = $repoPath;
                        $this->bare     = true;
                    }
                } else {
                    if ($createNew) {
                        $this->repoPath = $repoPath;
                        $this->run('init');
                    } else {
                        throw new Exception('"' . $repoPath . '" is not a git repository');
                    }
                }
            } else {
                throw new Exception('"' . $repoPath . '" is not a directory');
            }
        } else {
            if ($createNew) {
                if ($parent = realpath(dirname($repoPath))) {
                    mkdir($repoPath);
                    $this->repoPath = $repoPath;
                    $this->run('init');
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
     * @access public
     * @return string
     */
    public function gitDirectoryPath() {
        return ($this->bare) ? $this->repoPath : $this->repoPath . "/.git";
    }

    /**
     * Run a command in the git repository
     *
     * Accepts a shell command to run
     *
     * @param   string $command Command to run
     *
     * @return string
     * @throws GitException
     */
    protected function runCommand($command) {
        $descriptorspec = array(
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );
        $pipes          = array();
        /* Depending on the value of variables_order, $_ENV may be empty.
         * In that case, we have to explicitly set the new variables with
         * putenv, and call proc_open with env=null to inherit the reset
         * of the system.
         *
         * This is kind of crappy because we cannot easily restore just those
         * variables afterwards.
         *
         * If $_ENV is not empty, then we can just copy it and be done with it.
         */
        if (count($_ENV) === 0) {
            $env = null;
            foreach ($this->envOpts as $k => $v) {
                putenv(sprintf("%s=%s", $k, $v));
            }
        } else {
            $env = array_merge($_ENV, $this->envOpts);
        }
        $cwd      = $this->repoPath;
        $resource = proc_open($command, $descriptorspec, $pipes, $cwd, $env);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $status = trim(proc_close($resource));
        if ($status) {
            throw new Exception($stderr);
        }

        return $stdout;
    }

    /**
     * Run a git command in the git repository
     *
     * Accepts a git command to run
     *
     * @access  public
     *
     * @param   string $command Command to run
     *
     * @return  string
     */
    public function run($command) {
        return $this->runCommand(Git::getBin() . ' ' . $command);
    }

    /**
     * Runs a 'git status' call
     *
     * @param bool $excludeUntracked Excludes untracked files on status
     *
     * @return string
     */
    public function status($excludeUntracked = false) {
        $untrackedString = '';
        if ($excludeUntracked) {
            $untrackedString = ' -uno';
        }

        return $this->run('status' . $untrackedString);
    }

    /**
     * Runs a `git add` call
     *
     * @param   array|string $files Files to add.
     *
     * @return  string
     */
    public function add($files = '*') {
        if (is_array($files)) {
            $files = '"' . implode('" "', $files) . '"';
        } else if (is_string($files)) {

        } else {
            throw new InvalidArgumentException(sprintf('Expecting array or string, found %s', gettype($files)));
        }

        return $this->run("add $files -v");
    }

    /**
     * Runs a `git rm` call
     *
     * Accepts a list of files to remove
     *
     * @param array|string $files Files to remove
     * @param bool         $cached Use the --cached flag?
     *
     * @return string
     */
    public function rm($files = "*", $cached = false) {
        if (is_array($files)) {
            $files = '"' . implode('" "', $files) . '"';
        } else if (is_string($files)) {

        } else {
            throw new InvalidArgumentException(sprintf('Expecting array or string, found %s', gettype($files)));
        }

        return $this->run("rm " . ($cached ? '--cached ' : '') . $files);
    }


    /**
     * Runs a `git commit` call
     *
     * @param   string  $message Commit message
     * @param   boolean $commitAll Should all files be committed automatically (-a flag)
     *
     * @return  string
     */
    public function commit($message = '', $commitAll = true) {
        $flags = $commitAll ? '-av' : '-v';

        return $this->run("commit " . $flags . " -m " . escapeshellarg($message));
    }

    /**
     * Runs a `git clone` call to clone the current repository
     * into a different directory
     *
     * @param string $target Target directory
     *
     * @return string
     */
    public function cloneTo($target) {
        return $this->run("clone --local " . $this->repoPath . " $target");
    }

    /**
     * Runs a `git clone` call to clone a different repository
     * into the current repository
     *
     * @param   string $source source directory
     *
     * @return  string
     */
    public function cloneFrom($source) {
        return $this->run("clone --local $source " . $this->repoPath);
    }

    /**
     * Runs a `git clone` call to clone a remote repository
     * into the current repository
     *
     * @param   string $remote reference path
     *
     * @return  string
     */
    public function cloneRemote($remote) {
        return $this->run("clone $remote " . $this->repoPath);
    }

    /**
     * Runs a `git clean` call
     *
     * @param bool $deleteDirs Delete directories?
     * @param bool $force Force clean?
     *
     * @return  string
     */
    public function clean($deleteDirs = false, $force = false) {
        return $this->run("clean" . (($force) ? " -f" : "") . (($deleteDirs) ? " -d" : ""));
    }

    /**
     * Runs a `git branch` call
     *
     * @param string $branch branch name
     *
     * @return string
     */
    public function createBranch($branch) {
        return $this->run("branch $branch");
    }

    /**
     * Runs a `git branch -[d|D]` call
     *
     * @param string $branch Branch name
     * @param bool   $force Force branch
     *
     * @return string
     */
    public function deleteBranch($branch, $force = false) {
        return $this->run("branch " . (($force) ? '-D' : '-d') . " $branch");
    }

    /**
     * Runs a `git branch` call
     *
     * @param   bool $keepAsterisk Keep asterisk mark on active branch
     *
     * @return  array
     */
    public function listBranches($keepAsterisk = false) {
        $branchArray = explode("\n", $this->run("branch"));
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
     * @return  array
     */
    public function listRemoteBranches() {
        $branchArray = explode("\n", $this->run("branch -r"));
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
     * @return string
     */
    public function activeBranch($keepAsterisk = false) {
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
     * @param string $branch branch name
     *
     * @return string
     */
    public function checkout($branch) {
        return $this->run("checkout $branch");
    }


    /**
     * Runs a `git merge` call
     *
     * @param   string $branch branch to be merged
     *
     * @return  string
     */
    public function merge($branch) {
        return $this->run("merge $branch --no-ff");
    }


    /**
     * Runs a git fetch on the current branch
     *
     * @return  string
     */
    public function fetch() {
        return $this->run("fetch");
    }

    /**
     * Add a new tag on the current position
     *
     * @param string $tag Tag name
     * @param string $message Optional message
     *
     * @return string
     */
    public function addTag($tag, $message = null) {
        if ($message === null) {
            $message = $tag;
        }

        return $this->run("tag -a $tag -m " . escapeshellarg($message));
    }

    /**
     * List all the available repository tags.
     *
     * @param    string $pattern Shell wildcard pattern to match tags against.
     *
     * @return    array                Available repository tags.
     */
    public function listTags($pattern = null) {
        $tagArray = explode("\n", $this->run("tag -l $pattern"));
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
     *
     * @return string
     */
    public function push($remote, $branch) {
        return $this->run("push --tags $remote $branch");
    }

    /**
     * Pull specific branch from remote
     *
     * @param string $remote Name of remote branch
     * @param string $branch Name of local branch
     *
     * @return string
     */
    public function pull($remote, $branch) {
        return $this->run("pull $remote $branch");
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
            return $this->run('log --pretty=format:"' . $format . '"');
        }
    }

    /**
     * Sets the project description.
     *
     * @param string $description
     */
    public function setDescription($description) {
        $path = $this->gitDirectoryPath();
        file_put_contents($path . "/description", $description);
    }

    /**
     * Gets the project description.
     *
     * @return string
     */
    public function getDescription() {
        $path = $this->gitDirectoryPath();

        return file_get_contents($path . "/description");
    }

    /**
     * Sets custom environment options for calling Git
     *
     * @param string $key Key for the environment variable
     * @param string $value Value for the environment variable
     *
     */
    public function setEnv($key, $value) {
        $this->envOpts[$key] = $value;
    }

}

<?php

namespace Pub\Git;

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
    protected static $bin = '/usr/bin/git';

    /**
     * Constructing this class is not allowed
     */
    private function __construct() {
    }

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
     * Sets up library for use in a default Windows environment
     */
    public static function windowsMode() {
        static::setBin('git');
    }

    /**
     * Create a new git repository
     *
     * @param string $repoPath Repository creation path
     *
     * @return GitRepo
     * @throws GitException In case of a git error
     */
    public static function create($repoPath) {
        return new GitRepo($repoPath, true);
    }

    /**
     * Open an existing git repository
     *
     * Accepts a repository path
     *
     * @param   string $repoPath repository path
     *
     * @return  GitRepo
     * @throws GitException In case of a git error
     */
    public static function open($repoPath) {
        return new GitRepo($repoPath);
    }

    /**
     * Clones a repo into a directory and then returns a GitRepo object
     * for the newly created local repo
     *
     * @param   string $repoPath Repository path
     * @param   string $remote Remote repository
     *
     * @return  GitRepo
     * @throws GitException In case of a git error
     */
    public static function cloneRepository(string $repoPath, string $remote) {
        $repo = static::create($repoPath);

        $repo->cloneRemote($remote);

        return $repo;
    }

    /**
     * Checks if a variable is an instance of GitRepo
     *
     * Accepts a variable
     *
     * @access  public
     *
     * @param   mixed $var variable
     *
     * @return  bool
     */
    public static function isRepo($var) {
        return is_object($var) && $var instanceof GitRepo;
    }

}

// Use windows mode if detected
if (DIRECTORY_SEPARATOR === '\\') {
    Git::windowsMode();
}
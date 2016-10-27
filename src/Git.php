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
     * Accepts a creation path, and, optionally, a source path
     *
     * @param   string $repo_path repository path
     * @param   string $source directory to source
     *
     * @return  GitRepo
     */
    public static function create($repo_path, $source = null) {
        return GitRepo::create_new($repo_path, $source);
    }

    /**
     * Open an existing git repository
     *
     * Accepts a repository path
     *
     * @param   string $repo_path repository path
     *
     * @return  GitRepo
     */
    public static function open($repo_path) {
        return new GitRepo($repo_path);
    }

    /**
     * Clones a remote repo into a directory and then returns a GitRepo object
     * for the newly created local repo
     *
     * Accepts a creation path and a remote to clone from
     *
     * @param   string $repo_path repository path
     * @param   string $remote remote source
     * @param   string $reference reference path
     *
     * @return  GitRepo
     **/
    public static function cloneRemote($repo_path, $remote, $reference = null) {
        return GitRepo::create_new($repo_path, $remote, true, $reference);
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

if (DIRECTORY_SEPARATOR === '\\') {
    Git::windowsMode();
}
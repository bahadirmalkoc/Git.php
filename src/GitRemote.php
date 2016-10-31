<?php

namespace Pub\Git;

/**
 * A remote value object
 */
class GitRemote {

    /**
     * PUSH and FETCH repository
     */
    const PUSH_FETCH = 0;

    /**
     * PUSH repository
     */
    const PUSH = 1;

    /**
     * FETCH repository
     */
    const FETCH = 2;


    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $url;

    /**
     * @var int
     */
    private $type;

    /**
     * GitRemote constructor.
     *
     * @param string $url Url of the remote repository
     * @param string $name Name of the remote repository
     * @param int    $type Type of the repository. Either GitRemote::PUSH or GitRemote::FETCH or GitRemote::PUSH_FETCH
     */
    public function __construct($url, $name = 'origin', $type = self::PUSH_FETCH) {
        $this->name = $name;
        $this->url  = $url;
        $this->type = $type;
    }

    /**
     * Get the name of the remote repository
     *
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Set the name of the remote repository
     *
     * @param string $name
     */
    public function setName(string $name) {
        $this->name = $name;
    }

    /**
     * Get the url of the remote repository
     *
     * @return string
     */
    public function getUrl(): string {
        return $this->url;
    }

    /**
     * Set the url of the remote repository
     *
     * @param string $url
     */
    public function setUrl(string $url) {
        $this->url = $url;
    }

    /**
     * Can the repository be used for pushing?
     *
     * @return boolean
     */
    public function isPush(): bool {
        return $this->type === static::PUSH;
    }

    /**
     * Can the repository be used for fetching?
     *
     * @return boolean
     */
    public function isFetch(): bool {
        return $this->type === static::FETCH;
    }

    /**
     * Is repository both for push and fetch?
     *
     * @return bool
     */
    public function isPushFetch():bool {
        return $this->type === static::PUSH_FETCH;
    }

    /**
     * Set the type of repository.
     *
     * @param int $type Type of the repository. Either GitRemote::PUSH or GitRemote::FETCH or GitRemote::PUSH_FETCH.
     */
    public function setType(int $type = null) {
        $this->type = $type;
    }
}
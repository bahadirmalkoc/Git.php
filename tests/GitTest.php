<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Pub\Git\Git;
use Pub\Git\GitException;
use Symfony\Component\Filesystem\Filesystem;

class GitTest extends TestCase {


    const REMOTE_REPOSITORY = '';

    const TEST_DESCRIPTION = 'Git test description';

    const TEST_FILENAME = 'test_file_87631.txt';

    const TEST_BRANCH = 'some-test-branch321';

    const TEST_TAG = 'v6.6.6-rc';

    /**
     * @var string
     */
    private static $repoDirectory;

    /**
     * @var string
     */
    private static $secondaryRepoDirectory;

    /**
     * @var string
     */
    private static $bogusRepoDirectory;

    /**
     * @var Filesystem
     */
    private static $fs;

    /**
     *
     */
    public static function setUpBeforeClass() {
        static::$fs = new Filesystem();

        // Generate real git directory
        $tempFolder = tempnam(sys_get_temp_dir(), 'git');
        static::$fs->remove($tempFolder);
        static::$repoDirectory = $tempFolder;

        // Generate secondary git directory
        $tempFolder2 = tempnam(sys_get_temp_dir(), 'git');
        static::$fs->remove($tempFolder2);
        static::$secondaryRepoDirectory = $tempFolder2;

        // Generate bogus git directory
        $tempFile                   = tempnam(sys_get_temp_dir(), 'git');
        static::$bogusRepoDirectory = $tempFile;
    }

    /**
     * Open non existent repository
     */
    public function testOpenWithoutDir() {
        $this->expectException(GitException::class);

        // There should be no repository creation
        new Git(static::$repoDirectory);
    }

    /**
     * Open directory without any .git
     */
    public function testOpenWithoutInit() {
        $this->expectException(GitException::class);
        static::$fs->mkdir(static::$repoDirectory);

        try {
            new Git(static::$repoDirectory);
        } finally {
            static::$fs->remove(static::$repoDirectory);
        }
    }

    /**
     * Try to create a repository on a filename
     */
    public function testOpenOnBogusDirectory() {
        $this->expectException(GitException::class);

        new Git(static::$bogusRepoDirectory, true);
    }

    /**
     * Test repository creation
     *
     * @return Git
     */
    public function testCreate() {

        return new Git(static::$repoDirectory, true);
    }

    /**
     * Test created repo and other alternatives
     *
     * @param Git $repo
     *
     * @depends testCreate
     */
    public function testIsRepo(Git $repo) {
        $this->assertTrue(Git::isRepo($repo));

        $data = [
            0,
            new \stdClass(),
            'test',
            curl_init(),
            1.25,
            null,
            []
        ];

        foreach ($data as $item) {
            $this->assertFalse(Git::isRepo($item));
        }
    }

    /**
     * Opens a repo
     *
     * @param Git $repo
     *
     * @return Git
     * @depends testCreate
     */
    public function testOpen(Git $repo) {
        unset($repo);
        $repo = new Git(static::$repoDirectory);

        return $repo;
    }

    /**
     * @depends testOpen
     *
     * @param Git $repo
     */
    public function testSetAndGetDescription(Git $repo) {
        $repo->setDescription(static::TEST_DESCRIPTION);
        $this->assertTrue($repo->getDescription() === static::TEST_DESCRIPTION);
    }

    /**
     * @depends testOpen
     *
     * @param Git $repo
     */
    public function testCloneRemote(Git $repo) {
        $repo->cloneRemote(static::REMOTE_REPOSITORY);


    }

    /**
     * @depends testOpen
     *
     * @param Git $repo
     */
    public function testStatus(Git $repo) {
        $this->expectOutputRegex('/^on branch*/i');

        echo $repo->status();
    }

    /**
     * @depends testOpen
     *
     * @param Git $repo
     */
    public function testCloneLocalTo(Git $repo) {
        $repo->cloneTo(static::$secondaryRepoDirectory);

        $secondaryRepo = new Git(static::$secondaryRepoDirectory);
        $this->assertTrue($secondaryRepo->getDescription() === static::TEST_DESCRIPTION);
    }

    /**
     * @depends testOpen
     *
     * @param Git $repo
     */
    public function testCloneLocalFrom(Git $repo) {
        $secondaryRepo = new Git(static::$secondaryRepoDirectory, true);
        $secondaryRepo->cloneFrom($repo->gitDirectoryPath());

        $this->assertTrue($secondaryRepo->getDescription() === static::TEST_DESCRIPTION);
    }

    /**
     * @depends testOpen
     *
     * @param Git $repo
     */
    public function testAdd(Git $repo) {
        $testFile = $repo->getRepoPath() . DIRECTORY_SEPARATOR . static::TEST_FILENAME;
        static::$fs->dumpFile($testFile, 'testContent');
        $repo->add(static::TEST_FILENAME);

        $this->assertContains(static::TEST_FILENAME, $repo->status(true));
    }

    /**
     * @depends testOpen
     *
     * @param Git $repo
     */
    public function testRemove(Git $repo) {
        $repo->rm(static::TEST_FILENAME, true);

        $this->assertNotContains(static::TEST_FILENAME, $repo->status(true));
    }

    /**
     * @depends testOpen
     *
     * @param Git $repo
     */
    public function testCommit(Git $repo) {
        $this->testAdd($repo);
        $repo->commit('local test commit');

        $this->assertNotContains(static::TEST_FILENAME, $repo->status(true));
    }

    /**
     * Tests following: createBranch, deleteBranch, listBranches, checkout, getActiveBranch, merge
     *
     * @depends testOpen
     *
     * @param Git $repo
     *
     * @return Git
     */
    public function testBranches(Git $repo) {
        $testBranch = static::TEST_BRANCH;
        $repo->createBranch($testBranch);
        $this->assertContains($testBranch, $repo->listBranches());

        $testFile = $repo->getRepoPath() . DIRECTORY_SEPARATOR . static::TEST_FILENAME;
        static::$fs->dumpFile($testFile, 'testContent');
        $repo->add(static::TEST_FILENAME);
        $repo->commit('some commit message');

        $repo->checkout($testBranch);
        $activeBranch = $repo->activeBranch();
        $this->assertTrue($activeBranch === $testBranch, "'$activeBranch' is not equal to '$testBranch'");

        $repo->checkout('master');
        $this->assertFileNotExists($testFile);

        $repo->merge($testBranch);

        $this->assertTrue($activeBranch === 'master', "'$activeBranch' is not equal to 'master'");
        $this->assertFileExists($testFile);

        return $repo;
    }

    /**
     * Tests deleting a branch
     *
     * @param Git $repo
     *
     * @depends testBranches
     */
    public function testDeleteBranch(Git $repo) {
        $repo->deleteBranch(static::TEST_BRANCH);
        $this->assertArrayNotHasKey(static::TEST_BRANCH, $repo->listBranches());
    }

    /**
     * Tests adding tags
     *
     * TODO: Test delete tag
     *
     * @param Git $repo
     *
     * @depends testOpen
     */
    public function testTags(Git $repo) {
        $repo->addTag(static::TEST_TAG);
        $this->assertContains(static::TEST_TAG, $repo->listTags());
    }

    public function testPull() {
        $this->assertTrue(false, 'Test will be implemented later');
    }

    public function testPush() {
        $this->assertTrue(false, 'Test will be implemented later');
    }

    public function testListRemoteBranches() {
        $this->assertTrue(false, 'Test will be implemented later');
    }

    public function testLog() {
        $this->assertTrue(false, 'Test will be implemented later');
    }

    protected function tearDown() {
        static::$fs->remove(static::$secondaryRepoDirectory);
    }

    public static function tearDownAfterClass() {
        try {
            static::$fs->remove([
                static::$bogusRepoDirectory,
                static::$secondaryRepoDirectory,
                static::$repoDirectory
            ]);
        } catch (\Exception $e) {
            // ignore exceptions
        }
    }


}

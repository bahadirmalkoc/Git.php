<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Pub\Git\Git;
use Pub\Git\GitException;
use Pub\Git\GitRemote;
use Symfony\Component\Filesystem\Filesystem;

class GitTest extends TestCase {


    const REMOTE_REPOSITORY = 'https://github.com/bahadirmalkoc/GitLib.git';

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
    private static $bogusRepoDirectory;

    /**
     * @var Filesystem
     */
    private static $fs;

    /**
     * Directories to clean up
     *
     * @var array
     */
    private static $directories = [];

    public static function setUpBeforeClass() {
        static::$fs = new Filesystem();

        // Generate real git directory
        $tempFolder = tempnam(sys_get_temp_dir(), 'git');
        static::$fs->remove($tempFolder);
        static::$repoDirectory = $tempFolder;


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
     * @dataProvider randomPathProvider
     *
     * @param string $repoPath
     */
    public function testCreateWithEmptyDirectory(string $repoPath) {
        mkdir($repoPath);
        new Git($repoPath, true);
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
        $repo     = new Git(static::$repoDirectory);
        $testFile = $repo->getRepoPath() . DIRECTORY_SEPARATOR . 'initial_commit_test.txt';
        static::$fs->dumpFile($testFile, 'testContent');
        $repo->add('initial_commit_test.txt');
        $repo->commit('first commit');

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
     * @dataProvider randomPathProvider
     *
     * @param string $repoPath
     */
    public function testCloneRemote(string $repoPath) {
        $repo = new Git($repoPath, true, static::REMOTE_REPOSITORY);
        $this->assertFileExists($repo->getRepoPath() . DIRECTORY_SEPARATOR . 'phpunit.xml');

        $this->assertNotEmpty($repo->getLastStdError());

        $remoteBranches = $repo->listRemoteBranches();
        $this->assertNotEmpty($remoteBranches);
    }

    /**
     * @dataProvider randomPathProvider
     *
     * @param string $repoPath
     */
    public function testCloneRemoteWithEmptyDirectory(string $repoPath) {
        mkdir($repoPath);
        new Git($repoPath, true, static::REMOTE_REPOSITORY);
    }

    /**
     * @dataProvider randomPathProvider
     *
     * @param string $repoPath
     */
    public function testCloneRemoteBare(string $repoPath) {
        $repo = new Git($repoPath, true, static::REMOTE_REPOSITORY, true);
        $this->assertFileExists($repo->getRepoPath() . DIRECTORY_SEPARATOR . 'config');

        // Bare open test
        $repo = new Git($repoPath);
        $this->assertTrue($repo->isBare());
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
     * @depends      testOpen
     * @dataProvider randomPathProvider
     *
     * @param Git    $repo
     * @param string $repoPath
     */
    public function testCloneLocalTo(string $repoPath, Git $repo) {
        $repo->cloneTo($repoPath);

        $secondaryRepo = new Git($repoPath);
        $this->assertFileExists($secondaryRepo->getRepoPath() . DIRECTORY_SEPARATOR . 'initial_commit_test.txt');
    }

    /**
     * @depends testOpen
     *
     * @param Git $repo
     *
     * @return Git
     */
    public function testAdd(Git $repo) {
        $testFile = $repo->getRepoPath() . DIRECTORY_SEPARATOR . static::TEST_FILENAME;
        static::$fs->dumpFile($testFile, 'testContent');
        $repo->add(static::TEST_FILENAME);


        $this->assertContains(static::TEST_FILENAME, $repo->status(true));

        return $repo;
    }

    /**
     * @depends testAdd
     *
     * @param Git $repo
     *
     * @return Git
     */
    public function testRemove(Git $repo) {
        $repo->rm(static::TEST_FILENAME, true);
        $repo->commit('test removal commit');

        $this->assertNotContains(static::TEST_FILENAME, $repo->status(true));

        return $repo;
    }

    /**
     * @depends testOpen
     *
     * @param Git $repo
     */
    public function testAddRemoveMultiple(Git $repo) {
        $fs = static::$fs;

        $filename1   = basename(static::randomPathProvider()[0][0]);
        $randomFile1 = $repo->getRepoPath() . DIRECTORY_SEPARATOR . $filename1;
        $fs->dumpFile($randomFile1, 'test content');

        $filename2   = basename(static::randomPathProvider()[0][0]);
        $randomFile2 = $repo->getRepoPath() . DIRECTORY_SEPARATOR . $filename2;
        $fs->dumpFile($randomFile2, 'test content');

        $filename3   = basename(static::randomPathProvider()[0][0]);
        $randomFile3 = $repo->getRepoPath() . DIRECTORY_SEPARATOR . $filename3;
        $fs->dumpFile($randomFile3, 'test content');

        $filename4   = basename(static::randomPathProvider()[0][0]);
        $randomFile4 = $repo->getRepoPath() . DIRECTORY_SEPARATOR . $filename4;
        $fs->dumpFile($randomFile4, 'test content');

        // Try adding all
        $repo->add();
        $this->assertContains($filename1, $repo->status(true));
        $this->assertContains($filename2, $repo->status(true));
        $this->assertContains($filename3, $repo->status(true));
        $this->assertContains($filename4, $repo->status(true));

        // Try removing some
        $repo->rm([$filename1, $filename2, $filename3], true);
        $this->assertNotContains($filename1, $repo->status(true));
        $this->assertNotContains($filename2, $repo->status(true));
        $this->assertNotContains($filename3, $repo->status(true));
        $this->assertContains($filename4, $repo->status(true));

        // Try adding some
        $repo->add([$filename1, $filename3]);
        $this->assertContains($filename1, $repo->status(true));
        $this->assertContains($filename3, $repo->status(true));

        // Remove untracked files
        $repo->clean(true, true);
        $this->assertFileNotExists($randomFile2);
    }

    /**
     * @depends testRemove
     *
     * @param Git $repo
     *
     * @return Git
     */
    public function testCommit(Git $repo) {
        $this->testAdd($repo);
        $repo->commit('local test commit');

        $this->assertNotContains(static::TEST_FILENAME, $repo->status(true));

        return $repo;
    }

    /**
     * Tests following: createBranch, deleteBranch, listBranches, checkout, getActiveBranch, merge, deleteBranch
     *
     * @depends      testOpen
     * @dataProvider randomPathProvider
     *
     * @param string $file
     * @param Git    $repo
     */
    public function testBranches(string $file, Git $repo) {
        $testBranch = static::TEST_BRANCH;
        $repo->createBranch($testBranch);
        $this->assertContains($testBranch, $repo->listBranches());
        $repo->checkout($testBranch);

        $fileName = basename($file);
        $testFile = $repo->getRepoPath() . DIRECTORY_SEPARATOR . $fileName;
        static::$fs->dumpFile($testFile, 'testContent');
        $repo->add($fileName);
        $repo->commit('some commit message');

        $activeBranch = $repo->activeBranch();
        $this->assertTrue($activeBranch === $testBranch, "'$activeBranch' is not equal to '$testBranch'");

        $repo->checkout('master');
        $this->assertFileNotExists($testFile);

        $repo->merge($testBranch);

        $activeBranch = $repo->activeBranch();
        $this->assertTrue($activeBranch === 'master', "'$activeBranch' is not equal to 'master'");
        $this->assertFileExists($testFile);

        $repo->deleteBranch(static::TEST_BRANCH);
        $this->assertArrayNotHasKey(static::TEST_BRANCH, $repo->listBranches());
    }

    /**
     * Tests adding tags
     *
     * @param Git $repo
     *
     * @depends testOpen
     */
    public function testTags(Git $repo) {
        $repo->addTag(static::TEST_TAG);
        $this->assertContains(static::TEST_TAG, $repo->listTags());

        $repo->deleteTag(static::TEST_TAG);
        $this->assertNotContains(static::TEST_TAG, $repo->listTags());
    }

    /**
     * @dataProvider randomPathProvider
     *
     * @param string $repoPath
     */
    public function testListRemotes(string $repoPath) {
        $repo = Git::cloneRepository($repoPath, static::REMOTE_REPOSITORY);
        $this->assertFileExists($repo->getRepoPath() . DIRECTORY_SEPARATOR . 'phpunit.xml');

        $remotes = $repo->listRemotes();
        $this->assertNotEmpty($remotes);
    }

    /**
     * Test a remote repo with the repo we deal with before
     *
     * @depends testCommit
     *
     * @param Git $repo
     *
     * @return Git
     */
    public function testRemotes(Git $repo) {
        $newRepoPath = static::randomPathProvider()[0][0];
        $cloneRepo   = new Git($newRepoPath, true);
        $this->assertEmpty($cloneRepo->listRemotes());

        $remote = new GitRemote($repo->getRepoPath());
        $cloneRepo->addRemote($remote);
        $remotes = $cloneRepo->listRemotes();
        $this->assertNotEmpty($remotes);
        $remote = $remotes[0];
        $this->assertEquals($remote->getUrl(), $repo->getRepoPath());
        $this->assertEquals($remote->getName(), 'origin');

        $cloneRepo->deleteRemote($remote->getName());
        $this->assertEmpty($cloneRepo->listRemotes());

        $cloneRepo->addRemote($remote);
        $this->assertNotEmpty($cloneRepo->listRemotes());

        // without fetch there shouldn't be a file on the repo
        $this->assertFileNotExists($cloneRepo->getRepoPath() . DIRECTORY_SEPARATOR . static::TEST_FILENAME);

        // add a tag to original repo and check if it is received with fetch
        $repo->addTag('v1.2.3');
        $cloneRepo->fetch();
        $this->assertContains('v1.2.3', $cloneRepo->listTags());

        return $cloneRepo;
    }

    /**
     * @depends testCommit
     * @depends testRemotes
     *
     * @param Git $repo
     * @param Git $cloneRepo
     */
    public function testPullPush(Git $repo, Git $cloneRepo) {
        // pull all changes from repo
        $cloneRepo->pull('origin', 'HEAD');
        $this->assertFileExists($cloneRepo->getRepoPath() . DIRECTORY_SEPARATOR . static::TEST_FILENAME);

        // bare repo for pushing
        $bareRepoPath = static::randomPathProvider()[0][0];
        $bareRepo = new Git($bareRepoPath, true, $repo->getRepoPath(), true);
        $bareRepo->fetch();
        $bareRepo->deleteRemote('origin');
        $bareRemote = new GitRemote($bareRepoPath, 'origin');
        
        $cloneRepo->deleteRemote('origin');
        $cloneRepo->addRemote($bareRemote);
        $cloneRepo->fetch();
        $repo->addRemote($bareRemote);
        
        // create a random file and push all changes 
        $filename = basename(static::randomPathProvider()[0][0]);
        static::$fs->dumpFile($cloneRepo->getRepoPath() . DIRECTORY_SEPARATOR . $filename, 'file content');
        $cloneRepo->add();
        $cloneRepo->commit('commit for push test');
        $cloneRepo->push('origin', 'master', true);

        $repo->pull('origin', 'HEAD');
        $this->assertFileExists($repo->getRepoPath() . DIRECTORY_SEPARATOR . $filename);
    }

    /**
     * @depends testOpen
     *
     * @param Git $repo
     */
    public function testLog(Git $repo) {
        $repo->log();
    }

    public static function tearDownAfterClass() {
        try {
            static::$fs->remove([
                static::$bogusRepoDirectory,
                static::$repoDirectory
            ]);
        } catch (\Exception $e) {
            // ignore exceptions
        }

        foreach (static::$directories as $directory) {
            try {

                static::$fs->remove($directory);
            } catch (\Exception $e) {
            }
        }
    }

    public static function randomPathProvider() {
        // Generate secondary git directory
        $tempFolder = tempnam(sys_get_temp_dir(), 'git');
        $fs         = new Filesystem();
        $fs->remove($tempFolder);
        static::$directories[] = $tempFolder;

        return [
            [$tempFolder]
        ];
    }

}

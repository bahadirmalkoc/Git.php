# Git Library for PHP7

## Description

A PHP git repository control library.

Allows the running of any git command from a PHP class.

## Requirements

* PHP7
* symfony/process
* A system with [git](http://git-scm.com/) installed

_Tested on PHP 7.0 with a 2.10 git client_

## Installation

At this time, add to your composer like following:

````json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/bahadirmalkoc/GitLib"
    }
  ],
  "require": {
    "bahadirmalkoc/GitLib": "^1.0"
  }
}
````

## Security

Arguments (and command) passed to command line is escaped.
The file paths given (like repositories, files to be added, etc.) are not filtered.

## Usage

#### Open an available repo

````php
require_once('vendor/autoloader.php'); // composer autoloader

use Pub\Git\Git;

$repo = new Git('/path/to/repo');

$repo->add();
$repo->commit('Some commit message');
$repo->push('origin', 'master');
````


#### Clone a remote repo

````php
require_once('vendor/autoloader.php'); // composer autoloader

use Pub\Git\Git;

$repo = new Git('/path/to/repo', true, 'https://github.com/user/repo.git');

file_put_contents($repo->getRepoPath() . DIRECTORY_SEPERATOR . 'somefile.txt', 'test content');
$repo->add(); // add all files
$repo->commit('Some commit message');
$repo->push('origin', 'master', true);
````

#### Create a repo

````php
require_once('vendor/autoloader.php'); // composer autoloader

use Pub\Git\Git;

$repo = new Git('/path/to/repo', true);

file_put_contents($repo->getRepoPath() . DIRECTORY_SEPERATOR . 'somefile.txt', 'test content');
$repo->add('somefile.txt');
$repo->commit('Some commit message');
````

## Settings

#### Timeout

You can choose a timeout for a single git process by using `setTimeout`. By default there is no timeout.

#### Graceful Fail

You can set if the git commands gracefully fail. In other means, the commands will not only fail with
a non zero error code but also the STDERR should also not be empty. However if both STDERR and STDOUT is empty
then it is again a fatal error. By default this is on.

One example this helps is commits that does nothing. Git process then returns an error code of 1 while there is 
nothing on the stdErr.


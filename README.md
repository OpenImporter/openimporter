# This is the OpenImporter development repository.

OpenImporter is a forum converter engine and is intended to "convert" data between different forum systems. This importer assumes you have already installed one of the supported destination systems and that your installation of this system is working properly. It copies data from a source system into the destination plattform, so it won't work without an installation of the selected destination. 

## Supported Source systems

* IPB 3.4.x
* MyBB [https://www.mybb.com/](https://www.mybb.com/)
* phpBB3 [https://www.phpbb.com/](https://www.phpbb.com/)\
* PHPBoost 3.x [https://www.phpboost.com](https://www.phpboost.com)
* SeoBoards 1.x
* SMF [https://www.simplemachines.org/](https://www.simplemachines.org/)
* UBB Threads [https://www.ubbcentral.com](https://www.ubbcentral.com)
* vBulletin 4 [https://www.vbulletin.com/](https://www.vbulletin.com/)
* Viscacha 0.8 [https://www.viscacha.org/](https://www.viscacha.org/)
* Woltlab Burning Board [http://www.woltlab.com/](https://www.woltlab.com/)
* Wedge [https://wedge.org](https://wedge.org)
* Wordpress [https://wordpress.org/](https://wordpress.org/)
* Xenforo [https://xenforo.com](https://xenforo.com)

## Supported destination systems

* ElkArte [https://www.elkarte.net](https://www.elkarte.net)
* Wedge [https://wedge.org](https://wedge.org)

The software is licensed under [BSD 3-clause license](http://www.opensource.org/licenses/BSD-3-Clause).

Contributions to documentation are licensed under [CC-by-SA 3](http://creativecommons.org/licenses/by-sa/3.0). Third party libraries or sets of images, are under their own licenses.

## Development notes

The development happens on two branches: *master* and *development*.

The *master* branch holds the current most stable version of OpenImporter. If you want to do a conversion this is the one to use (unless your system is not supported).
The *development* branch contains the most "advanced" (in terms of refactoring and "moving-forward" ideas) code, but it's very unstable and can be badly broken.

### Using master code

In order to use the code from the master branch, download it and upload the files to your host. Point the browser to the path where OpenImporter is to *import.php* and follow the instructions.

### Using development code

The code in the development branch relies on a number of external dependencies, that must be installed before using OpenImporter.
External dependencies are handled by [Composer](https://getcomposer.org/), follow this procedure to have OpenImporter up and running:
- Install Composer ([instructions](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx))
- download OpenImporter package (either downloading the HEAD from github, or cloning the repository)
- from the command line go to the OpenImporter directory
- run the command ```composer install```
- OpenImporter is ready to be used

## Notes

Feel free to fork this repository and make your desired changes.

Please see the [Developer's Certificate of Origin](https://raw.github.com/OpenImporter/openimporter/master/DCO.txt) in the repository:
by signing off your contributions, you acknowledge that you can and do license your submissions under the license of the project.

Please see [How to contribute](https://github.com/openimporter/openimporter/blob/master/CONTRIBUTING.md) for information on how to contribute to the development process.

## Site

Project site: http://openimporter.github.io/openimporter/

[![Build Status](https://travis-ci.org/OpenImporter/openimporter.png?branch=master)](https://travis-ci.org/OpenImporter/openimporter)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/OpenImporter/openimporter/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/OpenImporter/openimporter/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/OpenImporter/openimporter/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/OpenImporter/openimporter/?branch=master)

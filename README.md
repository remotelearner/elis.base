
ELIS 2.3 System Requirements
============================

The requirements for ELIS are the same as Moodle -- [Moodle 2.3 System requirements][moodle_requirements]

*	*NOTE:* ELIS is currently not fully compatible with PostgreSQL.

Alfresco
--------

Remote-Learner works exclusively with the Enterprise version of Alfresco. Our
integration should work with the community release but has not been tested against
that codebase. Currently the Alfresco integration is only compatible with the
3.2 or 3.4 release series of Alfresco.


General Structure
=================

Each repository is structured this way:

1.	`addons` subdirectory - specific plug-ins used by the integration
2.	`core` subdirectory - files and subdirectories added directly to the Moodle
	application that do not  overwrite existing Moodle files
3.	`patches` subdirectory - diff patches to be applied to specific Moodle core
	files
4.	`version.php` - the specific version of Moodle this archive was applied to

The patches _should_ work against your version of Moodle, but there may be other
differences that could affect it. It may be necessary to examine your code with
the patch file to correctly apply it.


ELIS Documentation
==================

*	[ELIS 2013 Manual][elis_docs_home]
*	[ELIS release notes][elis_release_notes]


Component Repositories
======================

[elis.base][elis_repo_branch_base]
---------------------------

This repository contains some base modifications to Moodle which are required for
other ELIS components.

[elis.cm][elis_repo_branch_cm]
------------------------------

This repository contains the ELIS Program Management component.

#### [Component documentation][elis_docs_home]

[moodle-block\_php\_report][elis_repo_branch_reporting]
--------------------

This repository contains the ELIS Reporting component.

#### [Component documentation][elis_docs_reporting]

[elis.alfresco][elis_repo_branch_alfresco]
------------------------------------------

This repository contains the ELIS Files (Alfresco integration) component.

#### [Component documentation][elis_files_docs]

### Alfresco special setup instructions

The Remote-Learner Alfresco integration requires some custom web scripts to be
deployed into the Alfresco repository. These scripts are found within the
elis.alfresco repository in the following path:

*	`/core/file/repository/alfresco/webscripts/`

All of those files need to be installed within the Alfresco repository. There are
two ways of doing this current:

1. Install directly into the Alfresco application file structure:
	1.	Shutdown the Alfresco instance
	2.	Copy all of the web scripts into the following location on the filesystem
		(assuming Alfresco is installed at `/opt/alfresco/`):
		`/opt/alfresco/tomcat/shared/classes/alfresco/extension/templates/webscripts/moodle/`
	3.	Start the Alfresco instance
	4.	Visit the following URL on your Alfresco install (assuming the Alfresco
		web application is accessible via `http://myalfrescourl:8080/alfresco/`):
		`http://myalfrescourl:8080/alfresco/s/`
	5.	Click on the Refresh Web Scripts button
	6.	When this process has finished it should report no errors and tell you
		that it has found more web scripts than were previously already there
2. Install into the repository itself
	1.	Log into the Alfresco web application with an administrator account
	2.	Navigate to the following path in the repository:
		`/Company Home/Data Dictionary/Web Scripts Extensions/`
	3.	Create the the following directory structure within the *Web Scripts
		Extensions* folder: `org/moodle` (so that you end up with the following
		hierarchy: `Web Scripts Extensions/org/moodle`)
	4.	Upload all of the webscripts files into the new *moodle* folder
	5.	Visit the following URL on your Alfresco install (assuming the Alfresco
		web application is accessible via `http://myalfrescourl:8080/alfresco/`):
		*	`http://myalfrescourl:8080/alfresco/s/`
	6.	Click on the *Refresh Web Scripts* button
	7.	When this process has finished it should report no errors and tell you
		that it has found more web scripts than were previously already there

[moodle-block\_rlip][elis_repo_branch_block_rlip] (optional)
------------------------------------------------------

This repository contains the ELIS Files (Alfresco integration) component.

#### Component documentation

*	[Standard (non-ELIS) plugin][rldh_docs_basic]
*	[ELIS plugin][rldh_docs_elis]

OpenID (optional)
-----------------

We supply two plugins for Moodle to allow users to authenticate into Moodle via OpenID:
1.	An authentication plugin -- [browse code][elis_repo_branch_auth_openid]
2.	A block which allows users not currently authenticated via OpenID to switch
	their authentication method to use a valid OpenID source -- [browse_code][elis_repo_branch_block_openid]

#### [Component documentation][elis_docs_openid]

*NOTE:* This work is based off of an existing but abandoned project for Moodle 1.8
and Moodle 1.9 by [Stuart Metcalfe][stuart_metcalfe]

*	[Authentication Method: OpenID plugin][moodle_org_openid]
*	[Openid for Moodle][openid_original_source]

Dependencies
============

The OpenID component does not depend on anything other than having a functioning
Moodle install. The dependency chart below explains how each of the components
depends on one another and Moodle itself.

![ELIS Community Dependencies][img_depdencies]


How to get the code
===================

The code is currently available in Remote-Learner's Github repositories. You can
browse the code via our Github account here -- [https://github.com/remotelearner][github_remotelearner]

Direct access to each of the Remote-Learner ELIS Community repositories is
available at the following URLs:

*	*elis.base* --- [https://github.com/remotelearner/elis.base][elis_repo_base]
*	*elis.cm* --- [https://github.com/remotelearner/elis.cm][elis_repo_cm]
*	*elis.alfresco* --- [https://github.com/remotelearner/elis.alfresco][elis_repo_alfresco]
*	*moodle-block_php_report* --- [https://github.com/remotelearner/moodle-block\_php\_report][elis_repo_reporting]

Optional:

*	*moodle-block_rlip* --  [https://github.com/remotelearner/moodle-block\_rlip][elis_repo_block_rlip]
*	*moodle-auth_openid* --- [https://github.com/remotelearner/moodle-auth\_openid][elis_repo_auth_openid]
*	*moodle-block_openid* --- [https://github.com/remotelearner/moodle-block\_openid][elis_repo_block_openid]

Each repository includes the ability to both fork or clone the code via Git itself
or download a zip or tarball package of the code.

The direct download zip archive links for the latest version of the code in each
ELIS community repository are as follows:

*	*elis.base* --- [MOODLE\_23\_STABLE][zipdl_elis_base]
*	*elis.cm* --- [MOODLE\_23\_STABLE][zipdl_elis_cm]
*	*moodle-block_php_report* --- [MOODLE\_23\_STABLE][zipdl_elis_reporting]
*	*elis.alfresco* --- [MOODLE\_23\_STABLE][zipdl_elis_alfresco]

Optional:

*	*moodle-block_rlip* --- [MOODLE\_23\_STABLE][zipdl_block_rlip]
*	*moodle-auth_openid* --- [MOODLE\_23\_STABLE][zipdl_auth_openid]
*	*moodle-block_openid* --- [MOODLE\_23\_STABLE][zipdl_block_openid]


[moodle_requirements]: http://docs.moodle.org/dev/Moodle_2.3_release_notes#Requirements
[elis_docs_home]: http://rlcommunity.remote-learner.net/mod/book/view.php?id=69
[elis_release_notes]: http://rlcommunity.remote-learner.net/course/view.php?id=2
[elis_files_docs]: http://rlcommunity.remote-learner.net/mod/book/view.php?id=65
[rldh_docs_basic]: http://rlcommunity.remote-learner.net/mod/book/view.php?id=59
[rldh_docs_elis]: http://rlcommunity.remote-learner.net/mod/book/view.php?id=69&chapterid=928
[elis_docs_reporting]: http://rlcommunity.remote-learner.net/mod/book/view.php?id=69&chapterid=902
[elis_docs_openid]: http://rlcommunity.remote-learner.net/mod/book/view.php?id=26
[stuart_metcalfe]: https://launchpad.net/~stuartmetcalfe
[moodle_org_openid]: https://moodle.org/mod/data/view.php?d=13&rid=928]
[openid_original_source]: https://launchpad.net/moodle-openid
[img_depdencies]: elis_community_dependencies.png
[github_remotelearner]: https://github.com/remotelearner
[elis_repo_base]: https://github.com/remotelearner/elis.base
[elis_repo_cm]: https://github.com/remotelearner/elis.cm
[elis_repo_alfresco]: https://github.com/remotelearner/elis.alfresco
[elis_repo_reporting]: https://github.com/remotelearner/moodle-block_php_report
[elis_repo_auth_openid]: https://github.com/remotelearner/moodle-auth_openid
[elis_repo_block_openid]: https://github.com/remotelearner/moodle-block_openid
[elis_repo_branch_base]: https://github.com/remotelearner/elis.base/tree/MOODLE_23_STABLE
[elis_repo_branch_cm]: https://github.com/remotelearner/elis.cm/tree/MOODLE_23_STABLE
[elis_repo_branch_alfresco]: https://github.com/remotelearner/elis.alfresco/tree/MOODLE_23_STABLE
[elis_repo_branch_reporting]: https://github.com/remotelearner/moodle-block_php_report/tree/MOODLE_23_STABLE
[elis_repo_branch_block_rlip]: https://github.com/remotelearner/moodle-block_rlip/tree/MOODLE_23_STABLE
[elis_repo_branch_auth_openid]: https://github.com/remotelearner/moodle-auth_openid/tree/MOODLE_23_STABLE
[elis_repo_branch_block_openid]: https://github.com/remotelearner/moodle-block_openid/tree/MOODLE_23_STABLE
[zipdl_elis_base]: https://github.com/remotelearner/elis.base/zipball/MOODLE_23_STABLE
[zipdl_elis_cm]: https://github.com/remotelearner/elis.cm/zipball/MOODLE_23_STABLE
[zipdl_elis_reporting]: https://github.com/remotelearner/moodle-block_php_report/zipball/MOODLE_23_STABLE
[zipdl_elis_alfresco]: https://github.com/remotelearner/elis.alfresco/zipball/MOODLE_23_STABLE
[zipdl_block_rlip]: https://github.com/remotelearner/moodle-block_rlip/zipball/MOODLE_23_STABLE
[zipdl_auth_openid]: https://github.com/remotelearner/moodle-auth_openid/zipball/MOODLE_23_STABLE
[zipdl_block_openid]: https://github.com/remotelearner/moodle-block_openid/zipball/MOODLE_23_STABLE


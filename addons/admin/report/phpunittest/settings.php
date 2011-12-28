<?php  //$Id$
$ADMIN->add('reports', new admin_externalpage('report_phpunittest', get_string('pluginname', 'report_phpunittest'), "$CFG->wwwroot/$CFG->admin/report/phpunittest/index.php", 'report/phpunittest:view'));


<?php

$ADMIN->add('reports', new admin_externalpage('tool_phpunittest', get_string('pluginname', 'tool_phpunittest'),
            "$CFG->wwwroot/$CFG->admin/tool/phpunittest/index.php", 'tool/phpunittest:view'));

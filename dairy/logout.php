<?php
session_start();
session_destroy();
header('Location: /dairy/index.php');
exit;

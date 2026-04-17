<?php
session_start();
echo "session ok";
echo "<br>user_id: " . ($_SESSION['user_id'] ?? 'not set');
echo "<br>role: " . ($_SESSION['role'] ?? 'not set');
?>
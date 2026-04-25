<?php
/**
 * TEMPORARY UTILITY FILE
 * Use only for development, delete in production
 */

$password = '112233'; // change this

echo password_hash($password, PASSWORD_DEFAULT);

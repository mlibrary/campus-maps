If you somehow get locked out of your site, you can temporarily disable the module functionality
By editing settings.php and adding the following line:

$config['force_password_change.settings']['enabled'] = FALSE;

Do what you need to do, then remove the above line to re-enable the module functionality
